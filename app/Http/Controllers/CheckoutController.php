<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\PromoCodeRequest;
use App\Support\Demo\BookingSelection;
use App\Support\Demo\DemoCatalog;
use App\Support\Demo\DemoQuote;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Checkout + confirmation (Phase 1 demo: no payment is taken, nothing is stored in the database).
 *
 * Phase 4: GET /checkout/{booking:ulid} for a held booking. Phase 5: Stripe Payment Element,
 * webhook-confirmed bookings, receipts. The page structure and validation stay the same.
 */
class CheckoutController extends Controller
{
    public function create(Request $request, DemoCatalog $catalog): View
    {
        $selection = BookingSelection::from($request->query(), $catalog);
        $isDemo = ! $selection->isComplete();
        if ($isDemo) {
            $selection = $this->demoSelection($catalog);
        }

        $pricing = $catalog->pricing();
        $yacht = $selection->yachtModel();
        $experience = $selection->experienceModel();
        $balanceDate = $this->balanceDate($selection->date, $pricing['balance_due_days']);
        $plan = $balanceDate ? 'deposit' : 'full';
        $quote = DemoQuote::fromConfig()->quote($yacht, $experience, [
            'date' => $selection->date,
            'hours' => $selection->hours,
            'guests' => $selection->guests,
            'addons' => $selection->addonModels(),
            'gratuity' => $pricing['default_gratuity'],
            'plan' => $plan,
        ]);

        return view('booking.checkout', [
            'selection' => $selection,
            'yacht' => $yacht,
            'experience' => $experience,
            'quote' => $quote,
            'pricing' => $pricing,
            'plan' => $plan,
            'balanceDate' => $balanceDate,
            'isDemo' => $isDemo,
            'editUrl' => route('booking.create', $selection->toQuery()),
            'checkoutData' => [
                'yacht' => collect($yacht->toArray())->only(['slug', 'min_hours', 'hourly_rate_cents', 'captain_rate_cents', 'crew_count', 'cleaning_fee_cents']),
                'experience' => collect($experience->toArray())->only(['slug', 'name', 'package_fee_cents']),
                'addons' => collect($selection->addonModels())->map(fn ($a) => collect($a->toArray())->only(['slug', 'name', 'price_cents', 'unit']))->values(),
                'selection' => ['date' => $selection->date, 'hours' => $selection->hours, 'guests' => $selection->guests],
                'pricing' => collect($pricing)->only(['weekend_multiplier_bp', 'tax_rate_bp', 'deposit_bp', 'default_gratuity']),
                'balanceDate' => $balanceDate,
                'promoUrl' => route('checkout.promo'),
            ],
        ]);
    }

    /** One promo code at a time (rate-limited). Returns the discount rule, never the list of codes. */
    public function promo(PromoCodeRequest $request, DemoCatalog $catalog): JsonResponse
    {
        $code = strtoupper($request->validated('code'));
        $promo = collect($catalog->pricing()['promos'])->firstWhere('code', $code);

        if (! $promo) {
            return response()->json(['message' => 'That promo code is not valid.', 'errors' => ['code' => ['That promo code is not valid.']]], 422);
        }

        return response()->json(collect($promo)->only(['code', 'type', 'value', 'label']));
    }

    /** PHASE 1 DEMO: validates, recalculates the price on the server, then shows the confirmation. */
    public function store(CheckoutRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $selection = $request->selection();
        $pricing = config('demo.pricing');
        $balanceDate = $this->balanceDate($selection->date, $pricing['balance_due_days']);
        $plan = $balanceDate ? $data['plan'] : 'full'; // trips inside the balance window are paid in full

        $quote = DemoQuote::fromConfig()->quote($selection->yachtModel(), $selection->experienceModel(), [
            'date' => $selection->date,
            'hours' => $selection->hours,
            'guests' => $selection->guests,
            'addons' => $selection->addonModels(),
            'promo' => $data['promo'] ?? null,
            'gratuity' => (int) $data['gratuity'],
            'plan' => $plan,
        ]);

        $request->session()->put('booking.confirmation', [
            'ref' => 'CYB-'.Str::upper(Str::random(6)),
            'selection' => $selection->toQuery(),
            'contact' => ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'], 'phone' => $data['phone']],
            'occasion' => $data['occasion'] ?? null,
            'payment' => ['plan' => $plan, 'gratuity' => (int) $data['gratuity'], 'promo' => $quote['promo']['code'] ?? null, 'brand' => $data['card_brand'], 'last4' => $data['card_last4']],
            'totals' => ['total' => $quote['total'], 'paid' => $quote['dueNow'], 'balance' => $quote['balance'], 'balance_date' => $plan === 'deposit' ? $balanceDate : null],
        ]);

        return redirect()->route('booking.confirmation');
    }

    public function confirmation(Request $request, DemoCatalog $catalog): View
    {
        $confirmation = $request->session()->get('booking.confirmation');
        $isDemo = ! is_array($confirmation);

        if ($isDemo) {
            $selection = $this->demoSelection($catalog, 14);
            $quote = DemoQuote::fromConfig()->quote($selection->yachtModel(), $selection->experienceModel(), [
                'date' => $selection->date, 'hours' => 3, 'guests' => 8, 'addons' => $selection->addonModels(), 'gratuity' => 18,
            ]);
            $confirmation = [
                'ref' => 'CYB-DEMO42',
                'selection' => $selection->toQuery(),
                'contact' => ['first_name' => 'Alex', 'last_name' => 'Morgan', 'email' => 'alex@example.com', 'phone' => '(312) 555-0100'],
                'occasion' => 'Birthday',
                'payment' => ['plan' => 'deposit', 'gratuity' => 18, 'promo' => null, 'brand' => 'Visa', 'last4' => '4242'],
                'totals' => ['total' => $quote['total'], 'paid' => $quote['deposit'], 'balance' => $quote['balance'], 'balance_date' => $this->balanceDate($selection->date, 7)],
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

    /** The balance is charged N days before the trip; null when that day has already passed (pay in full). */
    private function balanceDate(string $tripDate, int $daysBefore): ?string
    {
        $due = CarbonImmutable::createFromFormat('!Y-m-d', $tripDate, config('app.display_timezone'))->subDays($daysBefore);

        return $due->gt(CarbonImmutable::now(config('app.display_timezone'))->startOfDay()) ? $due->toDateString() : null;
    }

    /** Sample booking for the demo when no (valid) booking was passed in. */
    private function demoSelection(DemoCatalog $catalog, int $daysAhead = 10): BookingSelection
    {
        $experience = $catalog->experience('sunset');
        $yacht = $catalog->yachts()->first(fn ($y) => $y->booking_enabled && in_array('sunset', $y->experiences, true));

        return BookingSelection::from([
            'experience' => 'sunset',
            'yacht' => $yacht?->slug,
            'guests' => '8',
            'date' => CarbonImmutable::now(config('app.display_timezone'))->addDays($daysAhead)->toDateString(),
            'time' => $experience->time_slots[1] ?? $experience->time_slots[0],
            'hours' => '3',
            'addons' => 'champagne',
            'occasion' => 'Birthday',
        ], $catalog);
    }
}
