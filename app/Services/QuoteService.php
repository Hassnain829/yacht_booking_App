<?php

namespace App\Services;

use App\Enums\AddonUnit;
use App\Enums\BookingItemType;
use App\Enums\CaptainSource;
use App\Enums\Payee;
use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Exceptions\QuoteException;
use App\Models\Addon;
use App\Models\Boat;
use App\Support\Admin\BoatOptions;
use App\Support\Money;
use App\Support\Pricing\PromoTerms;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteLine;
use App\Support\Pricing\QuoteRequest;
use Illuminate\Support\Str;

/**
 * The price of a trip, worked out on the server only (PLAN §7.1), in cents. Ported from the mockup's
 * CYB.quote() and extended with each boat's own pricing:
 *
 *   charter  = rate × hours, rate = hourly × weekend multiplier (Fri–Sun, Chicago time) rounded to whole dollars
 *              — or the boat's fixed price for that length, which replaces it on every day
 *   captain  = captain rate × hours
 *   add-ons  = the ones the guest picked: flat, or price × guests (optional — an experience is free and its
 *              suggested extras are charged only when picked, user decisions 2026-11-25)
 *   cleaning = fuel & cleaning fee
 *   discount = promo: % of the charter or subtotal, or a fixed amount; never more than its base or its cap
 *   tax      = tax rate × (subtotal − discount)      gratuity = % × (charter + captain)
 *   total    = subtotal − discount + tax + gratuity, paid in full at checkout (D22: no deposit)
 *
 * Rates come from the boat, falling back to Admin → Settings (weekend multiplier, tax, gratuity options).
 * The JS never calculates a price: it shows what this service returns. Money split (payee per line) per §7.5.
 */
class QuoteService
{
    public function __construct(private SettingsService $settings) {}

    /** With no lengths ticked, a boat offers every standard length from its minimum up to a full day. */
    public const DEFAULT_MAX_HOURS = 8;

    /**
     * The trip lengths (hours) a boat offers, never below its minimum: the ones ticked on the boat, or else every
     * standard length from its minimum up to DEFAULT_MAX_HOURS. An experience has no length of its own: the guest
     * books the boat by the hour and gets the experience for that time (user decision 2026-11-25).
     *
     * @return list<int>
     */
    public function durations(Boat $boat): array
    {
        $minimum = (int) ($boat->min_hours ?? 1);
        $lengths = array_map('intval', $boat->durations ?? []);
        if ($lengths === []) {
            $lengths = array_filter(BoatOptions::DURATIONS, fn (int $hours) => $hours <= max($minimum, self::DEFAULT_MAX_HOURS));
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
        $multiplierBp = $boat->weekend_multiplier_bp ?? (int) $settings['weekend_multiplier_bp'];

        if ($request->experience && $boat->offering($request->experience) === null) {
            throw QuoteException::on('experience', 'This boat doesn\'t offer that experience.');
        }
        $this->check($request, $settings['gratuity_options']);

        $hours = $request->hours;
        $guests = $request->guests;
        $ownerBoat = ! $boat->isHouse();
        $ownerCaptains = $boat->captain_source === CaptainSource::Owner;

        // Charter: the weekend hourly rate is rounded to whole dollars (as in the mockup). A fixed price for this
        // length replaces rate × hours; on Fri–Sun it gets the weekend rate too when the admin switched that on
        // for the boat (user decision 2026-11-24), also rounded to whole dollars.
        $weekend = in_array($request->startsAt->setTimezone(config('app.display_timezone'))->dayOfWeek, [0, 5, 6], true);
        $rate = $weekend ? $this->weekendAmount((int) $boat->hourly_rate_cents, $multiplierBp) : (int) $boat->hourly_rate_cents;
        $fixedPrice = $this->fixedPrice($boat, $hours);
        $fixedWeekend = $fixedPrice !== null && $weekend && $boat->weekend_fixed_prices;
        if ($fixedWeekend) {
            $fixedPrice = $this->weekendAmount($fixedPrice, $multiplierBp);
        }
        $charter = $fixedPrice ?? $rate * $hours;

        $lines = [new QuoteLine(
            BookingItemType::Charter, 'charter', 'Yacht charter',
            $fixedPrice !== null
                ? "Fixed price for {$hours} ".Str::plural('hr', $hours).($fixedWeekend ? ' · weekend rate' : '')
                : Money::format($rate).'/hr × '.$hours.' '.Str::plural('hr', $hours).($weekend ? ' · weekend rate' : ''),
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

        // The experience itself is free and adds nothing; add-ons (its suggested extras too) only when the guest
        // picked them (user decisions 2026-11-25).
        foreach ($this->uniqueAddons($request->addons) as $addon) {
            $lines[] = $this->addonLine($addon, $guests);
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
            pricingSnapshot: [
                'hourly_rate_cents' => (int) $boat->hourly_rate_cents,
                'weekend' => $weekend,
                'weekend_multiplier_bp' => $multiplierBp,
                'rate_cents' => $rate,
                'fixed_price_cents' => $fixedPrice,
                'weekend_fixed_prices' => (bool) $boat->weekend_fixed_prices,
                'captain_rate_cents' => $captainRate,
                'crew_count' => (int) $boat->crew_count,
                'cleaning_fee_cents' => $cleaning,
                'tax_rate_bp' => $taxRateBp,
                'gratuity_pct' => $request->gratuityPct,
                'promo' => $discount > 0 ? $request->promo?->toArray() : null,
                'ownership' => $boat->ownership->value,
                'captain_source' => $boat->captain_source->value,
            ],
        );
    }

    /** An amount with the weekend multiplier, rounded to whole dollars (as the mockup does for the hourly rate). */
    private function weekendAmount(int $cents, int $multiplierBp): int
    {
        return (int) round($cents * $multiplierBp / 10000 / 100) * 100;
    }

    /**
     * Lengths, guest count, add-ons and gratuity must fit the boat and Admin → Settings.
     *
     * @param  list<int|string>  $gratuityOptions
     */
    private function check(QuoteRequest $request, array $gratuityOptions): void
    {
        $lengths = $this->durations($request->boat);
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

    /** A flat add-on once, a per-guest one × guests. */
    private function addonLine(Addon $addon, int $guests): QuoteLine
    {
        $perGuest = $addon->unit === AddonUnit::PerGuest;
        $quantity = $perGuest ? $guests : 1;
        $description = $perGuest ? Money::format($addon->price_cents).' × '.$guests.' '.Str::plural('guest', $guests) : 'Add-on';

        return new QuoteLine(BookingItemType::Addon, 'addon-'.$addon->slug, $addon->name, $description, $quantity, (int) $addon->price_cents, $addon->price_cents * $quantity, Payee::Platform, $addon->getKey());
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
