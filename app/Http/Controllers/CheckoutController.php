<?php

namespace App\Http\Controllers;

use App\Actions\Booking\ConfirmPaidBooking;
use App\Actions\Booking\HoldTrip;
use App\Actions\Booking\PrepareCheckoutPayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Exceptions\QuoteException;
use App\Http\Requests\Checkout\ApplyPromoRequest;
use App\Http\Requests\Checkout\BookingActionRequest;
use App\Http\Requests\Checkout\CheckoutPayRequest;
use App\Http\Requests\Checkout\CheckoutPriceRequest;
use App\Http\Requests\Checkout\HoldRequest;
use App\Http\Requests\Checkout\TestCardRequest;
use App\Http\Requests\Checkout\TripChoicesRequest;
use App\Http\Resources\Checkout\BookingStatusResource;
use App\Http\Resources\Checkout\CheckoutQuoteResource;
use App\Http\Resources\Checkout\PaymentStartResource;
use App\Http\Resources\Checkout\PromoResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\AvailabilityService;
use App\Services\BoatQuotes;
use App\Services\CheckoutPricing;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SavedCards;
use App\Services\PromoService;
use App\Services\PublicCatalog;
use App\Services\SettingsService;
use App\Support\Checkout\CheckoutOptions;
use App\Support\Payments\Intent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Checkout (PLAN 4.5, D22: paid in full at checkout, no deposit; the payment is taken in 4.5, user decision
 * 2026-11-26). The boat page's booking card → `start` (guests sign in or create an account right there) →
 * `hold` (the time is kept for the guest, HoldTrip) → `show` (add-ons, details, gratuity, promo, card) → `pay`
 * (the final price + a payment intent for the total) → the card is confirmed with the provider in the browser →
 * `complete` / the webhook → ConfirmPaidBooking → the confirmation page (BookingConfirmationController, 4.6).
 * Every price is QuoteService's; the booking is confirmed only on the provider's word, never the browser's.
 */
class CheckoutController extends Controller
{
    /** The trip summary before it is held; a guest signs in or creates an account here first. */
    public function start(TripChoicesRequest $request, BoatQuotes $boats, AvailabilityService $availability, PublicCatalog $catalog): View|RedirectResponse
    {
        try {
            $trip = $request->trip($boats);
        } catch (ValidationException $exception) {
            return $this->backToCard((string) $request->validated('yacht'), $request->tripQuery(), collect($exception->errors())->flatten()->first());
        }
        $problem = $availability->problem($trip['boat'], $trip['startsAt'], $trip['hours'], $trip['experience'], $trip['guests']);
        if ($problem !== null) {
            return $this->backToCard($trip['boat']->slug, $request->tripQuery(), $problem['message']);
        }
        $quote = $boats->quote($trip['boat'], $trip['experience'], (string) $request->validated('date'), (string) $request->validated('time'), $trip['hours'], $trip['guests'], $trip['addons']);

        if ($request->user() === null) {
            $request->session()->put('url.intended', $request->fullUrl()); // back here after signing in or signing up
        }

        return view('booking.checkout-start', [
            'trip' => $trip,
            'yacht' => $catalog->yacht($trip['boat']->slug),
            'quote' => $quote,
            'holdFields' => ['yacht' => $trip['boat']->slug] + $request->tripQuery(),
            'editUrl' => route('yachts.show', [$trip['boat']->slug, ...collect($request->tripQuery())->only(['experience', 'date', 'hours', 'guests', 'addons'])->all()]).'#book',
        ]);
    }

    /** "Book now" / "Continue": hold the trip for the guest (the double-booking lock is in HoldTrip). */
    public function hold(HoldRequest $request, BoatQuotes $boats, HoldTrip $hold): RedirectResponse
    {
        try {
            $trip = $request->trip($boats);
            $booking = $hold->handle($request->user(), $trip['boat'], $trip['experience'], $trip['startsAt'], $trip['hours'], $trip['guests'], $trip['addons'], (string) $request->validated('idempotency_key'));
        } catch (ValidationException $exception) {
            return $this->backToCard((string) $request->validated('yacht'), $request->tripQuery(), collect($exception->errors())->flatten()->first());
        }

        return redirect()->route('checkout.show', $booking);
    }

    /** The checkout page of a held booking. */
    public function show(Booking $booking, PublicCatalog $catalog, CheckoutPricing $pricing, SettingsService $settings, PaymentGateway $gateway, SavedCards $cards): View|RedirectResponse
    {
        Gate::authorize('view', $booking);
        if (in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Completed], true)) {
            return redirect()->route('bookings.confirmation', $booking);
        }
        $booking->load(['boat.harbor', 'experience', 'items', 'promoCode']);
        $experience = $booking->experience ? $catalog->experience($booking->experience->slug) : null;
        $suggested = collect($experience?->extras ?? [])->pluck('slug')->all();
        $addons = $catalog->addons()
            ->sortBy(fn (Fluent $addon) => [in_array($addon->slug, $suggested, true) ? array_search($addon->slug, $suggested, true) : 99, $addon->name])
            ->map(fn (Fluent $addon) => new Fluent($addon->toArray() + ['suggested' => in_array($addon->slug, $suggested, true)]))
            ->values();
        $local = $booking->starts_at->setTimezone(config('app.display_timezone'));
        $gratuityOptions = array_map('intval', $settings->all()['gratuity_options']);

        return view('booking.checkout', [
            'booking' => $booking,
            'yacht' => $catalog->yacht($booking->boat->slug),
            'payable' => $booking->isPayable(),
            'addons' => $addons,
            'chosenAddons' => array_map(fn ($addon) => $addon->slug, $pricing->chosenAddons($booking)),
            'gratuityOptions' => $gratuityOptions,
            'defaultGratuity' => (int) $settings->all()['default_gratuity'],
            'gratuityAmounts' => $booking->isPayable() ? rescue(fn () => $pricing->gratuityAmounts($booking, $gratuityOptions), [], report: false) : [],
            'gratuity' => intdiv((int) $booking->gratuity_bp, 100),
            'savedCards' => $cards->for(request()->user()),
            'occasions' => CheckoutOptions::OCCASIONS,
            'heard' => CheckoutOptions::HEARD,
            'editUrl' => route('yachts.show', array_filter([$booking->boat->slug, 'experience' => $booking->experience?->slug, 'date' => $local->toDateString(), 'hours' => $booking->hours, 'guests' => $booking->guests])).'#book',
            'checkoutData' => [
                'urls' => [
                    'price' => route('checkout.price', $booking),
                    'promo' => route('checkout.promo', $booking),
                    'pay' => route('checkout.pay', $booking),
                ],
                'provider' => $gateway->name(),
                'publishableKey' => $gateway->publishableKey(),
                'holdExpiresAt' => $booking->hold_expires_at?->toIso8601ZuluString(),
                'serverNow' => now()->toIso8601ZuluString(),
                'amountCents' => (int) $booking->total_cents,
                'currency' => config('payments.currency', 'usd'),
                'promo' => $booking->promoCode ? (new PromoResource($booking->promoCode))->resolve() : null,
            ],
        ]);
    }

    /** The live price for the add-ons and gratuity the guest is trying out. */
    public function price(CheckoutPriceRequest $request, Booking $booking, CheckoutPricing $pricing): CheckoutQuoteResource
    {
        $this->ensurePayable($booking);

        return new CheckoutQuoteResource($pricing->quote($booking, $request->addons(), $request->gratuity()));
    }

    public function applyPromo(ApplyPromoRequest $request, Booking $booking, PromoService $promos): PromoResource
    {
        $this->ensurePayable($booking);
        $promo = $promos->check((string) $request->validated('code'), $request->user());
        $booking->forceFill(['promo_code_id' => $promo->getKey()])->save();

        return new PromoResource($promo);
    }

    public function removePromo(BookingActionRequest $request, Booking $booking): Response
    {
        $this->ensurePayable($booking);
        $booking->forceFill(['promo_code_id' => null])->save();

        return response()->noContent();
    }

    /** "Pay": the final price on the booking and a payment for its total (the browser then confirms the card). */
    public function pay(CheckoutPayRequest $request, Booking $booking, PrepareCheckoutPayment $prepare): PaymentStartResource
    {
        $key = (string) ($request->header('Idempotency-Key') ?? $request->input('idempotency_key'));
        [$payment, $intent] = $prepare->handle($booking, $request->user(), $request->choices(), $key);

        return new PaymentStartResource(['payment' => $payment->setRelation('booking', $booking), 'intent' => $intent]);
    }

    /** Back from the card step (Stripe's return URL): ask the provider — never trust the redirect itself. */
    public function complete(BookingActionRequest $request, Booking $booking, PaymentGateway $gateway, ConfirmPaidBooking $confirm): View|RedirectResponse
    {
        $booking = $this->syncWithProvider($booking, $gateway, $confirm);
        if ($booking->status === BookingStatus::Confirmed) {
            return redirect()->route('bookings.confirmation', $booking);
        }
        $payment = $booking->payments()->latest('id')->first();

        return view('booking.complete', [
            'booking' => $booking->load('boat'),
            'state' => match (true) {
                $booking->status !== BookingStatus::Hold => 'closed',
                $payment?->status === PaymentIntentStatus::Processing => 'processing',
                $payment?->failure_message !== null => 'failed',
                default => 'processing',
            },
            'failure' => $payment?->failure_message,
            'statusUrl' => route('checkout.status', $booking),
        ]);
    }

    /** Polled while the payment is being confirmed. */
    public function status(BookingActionRequest $request, Booking $booking, PaymentGateway $gateway, ConfirmPaidBooking $confirm): BookingStatusResource
    {
        return new BookingStatusResource($this->syncWithProvider($booking, $gateway, $confirm));
    }

    /**
     * The fake gateway's test card (local development and tests only: 404 with Stripe or in production). 4242…
     * pays; 4000 0000 0000 0002 is declined.
     */
    public function testCard(TestCardRequest $request, Booking $booking, PaymentGateway $gateway, ConfirmPaidBooking $confirm): JsonResponse
    {
        abort_if($gateway->name() !== 'fake' || app()->isProduction(), 404);
        $payment = $booking->payments()->whereIn('status', [PaymentIntentStatus::RequiresPayment, PaymentIntentStatus::RequiresAction])->latest('id')->first();
        if ($payment === null) {
            throw QuoteException::on('card', 'Start the payment again: press Pay.');
        }
        if ($request->boolean('decline')) {
            $confirm->handle($payment, new Intent((string) $payment->provider_intent_id, PaymentIntentStatus::RequiresPayment, $payment->amount_cents, failureCode: 'card_declined', failureMessage: 'Your card was declined.'));

            throw QuoteException::on('card', 'Your card was declined. Try another card.');
        }

        $saved = $payment->provider_payment_method_id
            ? $booking->customer?->paymentMethods()->where('provider_payment_method_id', $payment->provider_payment_method_id)->first()
            : null;
        $booking = $confirm->handle($payment, new Intent(
            (string) $payment->provider_intent_id, PaymentIntentStatus::Succeeded, $payment->amount_cents,
            chargeId: 'fake_ch_'.FakeGateway::id(),
            cardBrand: $saved?->brand ?? strtolower((string) $request->validated('brand')),
            cardLast4: $saved?->last4 ?? (string) $request->validated('last4'),
            paymentMethodId: $saved?->provider_payment_method_id ?? ($payment->save_card ? 'fake_pm_'.FakeGateway::id() : null),
            cardExpMonth: $saved?->exp_month ?? (int) ($request->validated('exp_month') ?? 12),
            cardExpYear: $saved?->exp_year ?? (int) ($request->validated('exp_year') ?? now()->addYears(2)->year),
        ));

        return response()->json(['data' => ['redirect' => $booking->status === BookingStatus::Confirmed ? route('bookings.confirmation', $booking) : route('checkout.complete', $booking)]]);
    }

    /** An open Stripe payment is checked with Stripe (a webhook may not have arrived yet, or locally not at all). */
    private function syncWithProvider(Booking $booking, PaymentGateway $gateway, ConfirmPaidBooking $confirm): Booking
    {
        $booking->refresh();
        $payment = $booking->payments()->latest('id')->first();
        if ($payment instanceof Payment && $gateway->name() === 'stripe' && $payment->provider === 'stripe'
            && $payment->status !== PaymentIntentStatus::Succeeded && $payment->status !== PaymentIntentStatus::Canceled && $payment->provider_intent_id) {
            $intent = $gateway->retrieve($payment);
            if ($intent->status !== $payment->status) {
                $confirm->handle($payment, $intent);
            }
        }

        return $booking->refresh();
    }

    /** @throws QuoteException when the hold ran out (or the booking is no longer open) */
    private function ensurePayable(Booking $booking): void
    {
        if (! $booking->isPayable()) {
            throw QuoteException::on('hold', 'Your hold on this time ran out. Go back to the yacht and pick a time again.');
        }
    }

    /** @param  array<string, mixed>  $query */
    private function backToCard(string $slug, array $query, ?string $message): RedirectResponse
    {
        if (! preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
            return redirect()->route('yachts.index');
        }

        return redirect()->to(route('yachts.show', [$slug, ...collect($query)->only(['experience', 'date', 'hours', 'guests', 'addons'])->all()]).'#book')
            ->with('booking_card_error', $message ?? 'That time is no longer available. Pick another one.');
    }
}
