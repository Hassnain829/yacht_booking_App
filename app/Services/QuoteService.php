<?php

namespace App\Services;

use App\Enums\AddonUnit;
use App\Enums\BookingItemType;
use App\Enums\CaptainSource;
use App\Enums\Payee;
use App\Enums\PaymentPlan;
use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Exceptions\QuoteException;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use App\Support\Money;
use App\Support\Pricing\PromoTerms;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteLine;
use App\Support\Pricing\QuoteRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The price of a trip, worked out on the server only (PLAN §7.1), in cents. Ported from the mockup's
 * CYB.quote() and extended with each boat's own pricing:
 *
 *   charter  = rate × hours, rate = hourly × weekend multiplier (Fri–Sun, Chicago time) rounded to whole dollars
 *              — or the boat's fixed price for that length, which replaces it on every day
 *   captain  = captain rate × hours                  package = the boat's override, else the experience fee
 *   add-ons  = flat, or price × guests               cleaning = fuel & cleaning fee
 *   discount = promo: % of the charter or subtotal, or a fixed amount; never more than its base or its cap
 *   tax      = tax rate × (subtotal − discount)      gratuity = % × (charter + captain)
 *   total    = subtotal − discount + tax + gratuity
 *   deposit  = deposit % of the total; the balance is due `balance_due_days` before the trip.
 *              A trip starting sooner than that can only be paid in full.
 *
 * Rates come from the boat, falling back to Admin → Settings (weekend multiplier, tax, deposit, gratuity options).
 * The JS never calculates a price: it shows what this service returns. Money split (payee per line) per §7.5.
 */
class QuoteService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * The trip lengths (hours) a boat offers for an experience: the experience's lengths, narrowed to the boat's
     * own when the boat lists some that match, never below the boat's minimum.
     *
     * @return list<int>
     */
    public function durations(Boat $boat, ?Experience $experience): array
    {
        $minimum = (int) ($boat->min_hours ?? 1);
        $boatLengths = array_map('intval', $boat->durations ?? []);
        $lengths = $experience && $experience->durations ? array_map('intval', $experience->durations) : $boatLengths;

        if ($experience && $boatLengths !== []) {
            $both = array_values(array_intersect($lengths, $boatLengths));
            $lengths = $both !== [] ? $both : $lengths;
        }

        $lengths = array_values(array_unique(array_filter($lengths, fn (int $hours) => $hours >= $minimum)));
        sort($lengths);

        return $lengths !== [] ? $lengths : [$minimum];
    }

    /**
     * @throws QuoteException when the choices don't fit the boat (field: hours, guests, experience, addons or gratuity)
     */
    public function quote(QuoteRequest $request): Quote
    {
        $boat = $request->boat;
        $settings = $this->settings->all();
        $taxRateBp = (int) $settings['tax_rate_bp'];
        $depositBp = (int) $settings['deposit_bp'];
        $balanceDueDays = (int) $settings['balance_due_days'];
        $multiplierBp = $boat->weekend_multiplier_bp ?? (int) $settings['weekend_multiplier_bp'];

        $experience = $request->experience ? $this->offeredExperience($boat, $request->experience) : null;
        $this->check($request, $settings['gratuity_options']);

        $hours = $request->hours;
        $guests = $request->guests;
        $ownerBoat = ! $boat->isHouse();
        $ownerCaptains = $boat->captain_source === CaptainSource::Owner;

        // Charter: the weekend hourly rate is rounded to whole dollars (as in the mockup); a fixed price for this
        // length replaces rate × hours on every day.
        $weekend = in_array($request->startsAt->setTimezone(config('app.display_timezone'))->dayOfWeek, [0, 5, 6], true);
        $rate = $weekend ? (int) round($boat->hourly_rate_cents * $multiplierBp / 10000 / 100) * 100 : (int) $boat->hourly_rate_cents;
        $fixedPrice = $this->fixedPrice($boat, $hours);
        $charter = $fixedPrice ?? $rate * $hours;

        $lines = [new QuoteLine(
            BookingItemType::Charter, 'charter', 'Yacht charter',
            $fixedPrice !== null ? "Fixed price for {$hours} ".Str::plural('hr', $hours) : Money::format($rate).'/hr × '.$hours.' '.Str::plural('hr', $hours).($weekend ? ' · weekend rate' : ''),
            $fixedPrice !== null ? 1 : $hours, $fixedPrice ?? $rate, $charter, $ownerBoat ? Payee::Owner : Payee::Platform,
        )];

        $captainRate = (int) $boat->captain_rate_cents;
        $captain = $captainRate * $hours;
        if ($captain > 0) {
            $lines[] = new QuoteLine(
                BookingItemType::Captain, 'captain', $boat->crew_count > 1 ? "Captain & crew ({$boat->crew_count})" : 'Licensed USCG captain',
                Money::format($captainRate).'/hr × '.$hours.' '.Str::plural('hr', $hours).' · included',
                $hours, $captainRate, $captain, $ownerCaptains ? Payee::Owner : Payee::Platform,
            );
        }

        $packageFee = $experience ? (int) ($experience->pivot->package_fee_override_cents ?? $experience->package_fee_cents) : 0;
        if ($packageFee > 0) {
            $lines[] = new QuoteLine(BookingItemType::Package, 'package', "{$experience->name} package", 'Experience extras', 1, $packageFee, $packageFee, Payee::Platform);
        }

        foreach ($this->uniqueAddons($request->addons) as $addon) {
            $perGuest = $addon->unit === AddonUnit::PerGuest;
            $quantity = $perGuest ? $guests : 1;
            $lines[] = new QuoteLine(
                BookingItemType::Addon, 'addon-'.$addon->slug, $addon->name,
                $perGuest ? Money::format($addon->price_cents).' × '.$guests.' '.Str::plural('guest', $guests) : 'Add-on',
                $quantity, (int) $addon->price_cents, $addon->price_cents * $quantity, Payee::Platform, $addon->getKey(),
            );
        }

        $cleaning = (int) $boat->cleaning_fee_cents;
        if ($cleaning > 0) {
            $lines[] = new QuoteLine(BookingItemType::Cleaning, 'cleaning', 'Fuel & cleaning', 'Flat fee', 1, $cleaning, $cleaning, $ownerBoat ? Payee::Owner : Payee::Platform);
        }

        $subtotal = array_sum(array_map(fn (QuoteLine $line) => $line->amountCents, $lines));
        [$discount, $promoRejection] = $request->promo ? $this->discount($request->promo, $charter, $subtotal) : [0, null];

        $tax = (int) round(max(0, $subtotal - $discount) * $taxRateBp / 10000);
        $gratuity = (int) round(($charter + $captain) * $request->gratuityPct / 100);
        $total = max(0, $subtotal - $discount) + $tax + $gratuity;

        // Deposit or full: a trip closer than the balance date has nothing left to charge later.
        $depositAllowed = $request->startsAt->greaterThanOrEqualTo(CarbonImmutable::now()->addDays($balanceDueDays));
        $plan = $depositAllowed ? $request->plan : PaymentPlan::Full;
        $deposit = (int) round($total * $depositBp / 10000);
        $dueNow = $plan === PaymentPlan::Full ? $total : $deposit;
        $balance = $total - $dueNow;

        $adjustments = [];
        if ($discount > 0) {
            $adjustments[] = new QuoteLine(BookingItemType::Discount, 'discount', "Discount ({$request->promo->code})", $request->promo->label(), 1, -$discount, -$discount, Payee::Platform);
        }
        if ($tax > 0) {
            $adjustments[] = new QuoteLine(BookingItemType::Tax, 'tax', 'Taxes', Money::percent($taxRateBp).' of '.Money::format(max(0, $subtotal - $discount)), 1, $tax, $tax, Payee::Platform);
        }
        if ($gratuity > 0) {
            $adjustments[] = new QuoteLine(BookingItemType::Gratuity, 'gratuity', "Crew gratuity ({$request->gratuityPct}%)", 'Of the charter and captain', 1, $gratuity, $gratuity, $ownerCaptains ? Payee::Owner : Payee::Captain);
        }

        return new Quote(
            hours: $hours,
            guests: $guests,
            weekend: $weekend,
            hourlyRateCents: $rate,
            fixedPriceCents: $fixedPrice,
            lines: $lines,
            adjustments: $adjustments,
            charterCents: $charter,
            captainCents: $captain,
            subtotalCents: $subtotal,
            promoCode: $discount > 0 ? $request->promo?->code : null,
            promoRejection: $promoRejection,
            discountCents: $discount,
            taxCents: $tax,
            gratuityPct: $request->gratuityPct,
            gratuityCents: $gratuity,
            totalCents: $total,
            plan: $plan,
            depositAllowed: $depositAllowed,
            depositCents: $deposit,
            dueNowCents: $dueNow,
            balanceCents: $balance,
            balanceDueAt: $balance > 0 ? $request->startsAt->subDays($balanceDueDays) : null,
            pricingSnapshot: [
                'hourly_rate_cents' => (int) $boat->hourly_rate_cents,
                'weekend' => $weekend,
                'weekend_multiplier_bp' => $multiplierBp,
                'rate_cents' => $rate,
                'fixed_price_cents' => $fixedPrice,
                'captain_rate_cents' => $captainRate,
                'crew_count' => (int) $boat->crew_count,
                'package_fee_cents' => $packageFee,
                'cleaning_fee_cents' => $cleaning,
                'tax_rate_bp' => $taxRateBp,
                'gratuity_pct' => $request->gratuityPct,
                'deposit_bp' => $depositBp,
                'balance_due_days' => $balanceDueDays,
                'promo' => $discount > 0 ? $request->promo?->toArray() : null,
                'ownership' => $boat->ownership->value,
                'captain_source' => $boat->captain_source->value,
            ],
        );
    }

    /** The experience as this boat offers it (with the pivot's package fee override), or a 422 on "experience". */
    private function offeredExperience(Boat $boat, Experience $experience): Experience
    {
        $offered = $boat->relationLoaded('experiences')
            ? $boat->experiences->firstWhere('id', $experience->getKey())
            : $boat->experiences()->whereKey($experience->getKey())->first();

        if (! $offered instanceof Experience || ! $offered->is_active || ! (bool) $offered->pivot->is_active) {
            throw QuoteException::on('experience', 'This boat doesn\'t offer that experience.');
        }

        return $offered;
    }

    /**
     * Lengths, guest count, add-ons and gratuity must fit the boat and Admin → Settings.
     *
     * @param  list<int|string>  $gratuityOptions
     */
    private function check(QuoteRequest $request, array $gratuityOptions): void
    {
        $lengths = $this->durations($request->boat, $request->experience);
        if (! in_array($request->hours, $lengths, true)) {
            throw QuoteException::on('hours', 'Choose one of the trip lengths offered: '.implode(', ', $lengths).' hours.');
        }

        if ($request->guests < 1 || $request->guests > (int) $request->boat->max_guests) {
            throw QuoteException::on('guests', "This boat takes 1 to {$request->boat->max_guests} guests.");
        }

        foreach ($request->addons as $addon) {
            if (! $addon instanceof Addon || ! $addon->is_active || $addon->trashed()) {
                throw QuoteException::on('addons', 'One of the add-ons is no longer offered. Remove it and try again.');
            }
        }

        if ($request->gratuityPct !== 0 && ! in_array($request->gratuityPct, array_map('intval', $gratuityOptions), true)) {
            throw QuoteException::on('gratuity', 'Choose one of the gratuity options.');
        }
    }

    /** The boat's fixed price for this length (cents), if the owner or admin set one. */
    private function fixedPrice(Boat $boat, int $hours): ?int
    {
        $price = ($boat->duration_prices ?? [])[$hours] ?? null; // JSON keys "3" come back as int 3

        return $price === null ? null : (int) $price;
    }

    /**
     * Each add-on once, in the order chosen.
     *
     * @param  list<Addon>  $addons
     * @return list<Addon>
     */
    private function uniqueAddons(array $addons): array
    {
        return collect($addons)->unique(fn (Addon $addon) => $addon->getKey())->values()->all();
    }

    /**
     * The discount in cents, or 0 + the reason the code gives nothing here.
     *
     * @return array{0: int, 1: ?string}
     */
    private function discount(PromoTerms $promo, int $charter, int $subtotal): array
    {
        if ($promo->minSubtotalCents !== null && $subtotal < $promo->minSubtotalCents) {
            return [0, "{$promo->code} needs a booking of at least ".Money::format($promo->minSubtotalCents).'.'];
        }

        $base = $promo->appliesTo === PromoAppliesTo::Charter ? $charter : $subtotal;
        $amount = $promo->type === PromoType::Percent ? (int) round($base * $promo->value / 10000) : $promo->value;
        $amount = min($amount, $base, $promo->maxDiscountCents ?? PHP_INT_MAX);

        return [max(0, $amount), null];
    }
}
