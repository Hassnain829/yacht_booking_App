<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\PromoCodeRequest;
use App\Http\Resources\PromoCodeResource;
use App\Services\PublicCatalog;
use App\Support\Demo\BookingSelection;
use App\Support\Demo\DemoQuote;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Checkout + confirmation (Phase 1 demo: no payment is taken, nothing is stored in the database).
 *
 * Since 4.3 it opens from the boat page's booking card (the experience is optional, D21) and the whole price is
 * paid at checkout (D22: no deposit). Add-ons are optional and picked here, the experience's suggested extras
 * first (2026-11-25). 4.5: GET /checkout/{booking:ulid} for a held booking. Phase 5: Stripe Payment Element,
 * webhook-confirmed bookings, receipts.
 */
class CheckoutController extends Controller
{
    public function create(Request $request, PublicCatalog $catalog): View|RedirectResponse
    {
        $selection = BookingSelection::from($request->query(), $catalog);
        $isDemo = ! $selection->isComplete();
        if ($isDemo && $selection->yacht !== null) {
            // A real boat, but no date or start time that fits the choices (e.g. a time outside the experience's
            // window): back to its booking card instead of a sample booking.
            return redirect()->to(route('yachts.show', [$selection->yacht, ...collect($selection->toQuery())->only(['experience', 'date', 'hours', 'guests', 'addons'])->all()]).'#book')
                ->with('booking_card_error', $selection->date ? 'That start time isn\'t available for your choices. Pick one of the times shown.' : 'Pick a date and a start time for your trip.');
        }
        if ($isDemo) {
            $selection = $this->demoSelection($catalog);
            if ($selection === null) {
                return redirect()->route('yachts.index'); // nothing bookable to show as a sample
            }
        }

        $pricing = $catalog->pricing();
        $yacht = $selection->yachtModel();
        $experience = $selection->experienceModel();
        $quote = DemoQuote::fromSettings()->quote($yacht, $experience, [
            'date' => $selection->date,
            'hours' => $selection->hours,
            'guests' => $selection->guests,
            'addons' => $selection->addonModels(),
            'gratuity' => $pricing['default_gratuity'],
        ]);

        // Every add-on is optional; the experience's suggested extras come first (none is ticked unless chosen).
        $suggested = collect($experience?->extras ?? [])->pluck('slug')->all();
        $addonChoices = $catalog->addons()
            ->sortBy(fn ($addon) => [in_array($addon->slug, $suggested, true) ? array_search($addon->slug, $suggested, true) : 99, $addon->name])
            ->map(fn ($addon) => new Fluent($addon->toArray() + ['suggested' => in_array($addon->slug, $suggested, true)]))
            ->values();

        return view('booking.checkout', [
            'selection' => $selection,
            'yacht' => $yacht,
            'experience' => $experience,
            'quote' => $quote,
            'pricing' => $pricing,
            'isDemo' => $isDemo,
            'addonChoices' => $addonChoices,
            'editUrl' => route('yachts.show', [$yacht->slug, ...collect($selection->toQuery())->only(['experience', 'date', 'hours', 'guests', 'addons'])->all()]).'#book',
            'checkoutData' => [
                'yacht' => collect($yacht->toArray())->only(['slug', 'min_hours', 'hourly_rate_cents', 'captain_rate_cents', 'crew_count', 'cleaning_fee_cents']),
                'experience' => $experience ? collect($experience->toArray())->only(['slug', 'name']) : null,
                'addons' => $addonChoices->map(fn ($a) => collect($a->toArray())->only(['slug', 'name', 'price_cents', 'unit']))->values(),
                'selection' => ['date' => $selection->date, 'hours' => $selection->hours, 'guests' => $selection->guests],
                'pricing' => collect($pricing)->only(['weekend_multiplier_bp', 'tax_rate_bp', 'default_gratuity']),
                'promoUrl' => route('checkout.promo'),
            ],
        ]);
    }

    /** One promo code at a time (rate-limited). Returns the discount rule, never the list of codes. */
    public function promo(PromoCodeRequest $request, PublicCatalog $catalog): JsonResponse|PromoCodeResource
    {
        $code = strtoupper($request->validated('code'));
        $promo = collect($catalog->pricing()['promos'])->firstWhere('code', $code);

        if (! $promo) {
            return response()->json(['message' => 'That promo code is not valid.', 'errors' => ['code' => ['That promo code is not valid.']]], 422);
        }

        return new PromoCodeResource($promo);
    }

    /** PHASE 1 DEMO: validates, recalculates the price on the server, then shows the confirmation. */
    public function store(CheckoutRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $selection = $request->selection();

        $quote = DemoQuote::fromSettings()->quote($selection->yachtModel(), $selection->experienceModel(), [
            'date' => $selection->date,
            'hours' => $selection->hours,
            'guests' => $selection->guests,
            'addons' => $selection->addonModels(),
            'promo' => $data['promo'] ?? null,
            'gratuity' => (int) $data['gratuity'],
        ]);

        $request->session()->put('booking.confirmation', [
            'ref' => 'CYB-'.Str::upper(Str::random(6)),
            'selection' => $selection->toQuery(),
            'contact' => ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'], 'phone' => $data['phone']],
            'occasion' => $data['occasion'] ?? null,
            'payment' => ['gratuity' => (int) $data['gratuity'], 'promo' => $quote['promo']['code'] ?? null, 'brand' => $data['card_brand'], 'last4' => $data['card_last4']],
            'totals' => ['total' => $quote['total'], 'paid' => $quote['total']], // paid in full (D22)
        ]);

        return redirect()->route('booking.confirmation');
    }

    public function confirmation(Request $request, PublicCatalog $catalog): View|RedirectResponse
    {
        $confirmation = $request->session()->get('booking.confirmation');
        $isDemo = ! is_array($confirmation);

        if ($isDemo) {
            $selection = $this->demoSelection($catalog, 14);
            if ($selection === null) {
                return redirect()->route('yachts.index');
            }
            $quote = DemoQuote::fromSettings()->quote($selection->yachtModel(), $selection->experienceModel(), [
                'date' => $selection->date, 'hours' => 3, 'guests' => 8, 'addons' => $selection->addonModels(), 'gratuity' => 18,
            ]);
            $confirmation = [
                'ref' => 'CYB-DEMO42',
                'selection' => $selection->toQuery(),
                'contact' => ['first_name' => 'Alex', 'last_name' => 'Morgan', 'email' => 'alex@example.com', 'phone' => '(312) 555-0100'],
                'occasion' => 'Birthday',
                'payment' => ['gratuity' => 18, 'promo' => null, 'brand' => 'Visa', 'last4' => '4242'],
                'totals' => ['total' => $quote['total'], 'paid' => $quote['total']],
            ];
        }

        $selection = BookingSelection::from($confirmation['selection'], $catalog);
        abort_unless($selection->isComplete(), 404);

        return view('booking.confirmation', [
            'c' => $confirmation,
            'selection' => $selection,
            'yacht' => $selection->yachtModel(),
            'experience' => $selection->experienceModel(),
            'addons' => $selection->addonModels(),
            'isDemo' => $isDemo,
        ]);
    }

    /**
     * Sample booking for the demo when no (valid) booking was passed in: the first bookable boat + experience
     * the admin offers (the Sunset cruise and champagne first, as in the mockup), on the first allowed day from
     * `$daysAhead` on. Null when nothing is bookable (no live, bookable boat offers a live experience).
     */
    private function demoSelection(PublicCatalog $catalog, int $daysAhead = 10): ?BookingSelection
    {
        $today = CarbonImmutable::now(config('app.display_timezone'));
        $addon = $catalog->addons()->firstWhere('slug', 'champagne') ?? $catalog->addons()->first();

        foreach ($catalog->yachts()->where('booking_enabled', true)->sortBy(fn ($y) => in_array('sunset', $y->experiences, true) ? 0 : 1) as $yacht) {
            foreach (collect($yacht->experiences)->sortBy(fn (string $slug) => $slug === 'sunset' ? 0 : 1) as $slug) {
                $experience = $catalog->experience($slug);
                $durations = $yacht->lengths;
                for ($day = $daysAhead; $experience && $day < $daysAhead + 60; $day++) {
                    $date = BookingSelection::validDate($today->addDays($day)->toDateString(), $experience);
                    if ($date === null) {
                        continue;
                    }
                    $selection = BookingSelection::from([
                        'experience' => $slug,
                        'yacht' => $yacht->slug,
                        'guests' => (string) min(8, $yacht->max_guests),
                        'date' => $date,
                        'time' => $experience->time_slots[1] ?? $experience->time_slots[0],
                        'hours' => (string) (in_array(3, $durations, true) ? 3 : $durations[0]),
                        'addons' => $addon?->slug ?? '',
                        'occasion' => 'Birthday',
                    ], $catalog);
                    if ($selection->isComplete()) {
                        return $selection;
                    }
                    break;
                }
            }
        }

        return null;
    }
}
