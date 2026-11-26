<?php

namespace App\Actions\Booking;

use App\Enums\PaymentIntentStatus;
use App\Exceptions\QuoteException;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Payments\PaymentGateway;
use App\Services\PromoService;
use App\Services\QuoteService;
use App\Support\Payments\Intent;
use App\Support\Pricing\QuoteRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Pay" on the checkout page (PLAN 4.5, D22: the full amount, no deposit). Inside a transaction that locks the
 * booking: the hold must still be open, the trip is checked again (a block may have been added meanwhile), the
 * guest's final choices — add-ons, gratuity, the promo code they applied, their details — are priced by
 * QuoteService and put on the booking, and the payment for the total is created (or its open one set to the new
 * total). The browser then confirms the card with the provider; the booking is confirmed by ConfirmPaidBooking
 * only when the provider says the money came in.
 */
class PrepareCheckoutPayment
{
    public function __construct(
        private AvailabilityService $availability,
        private QuoteService $quotes,
        private BookingService $bookings,
        private PromoService $promos,
        private PaymentGateway $gateway,
    ) {}

    /**
     * @param  array{addons: list<Addon>, gratuity: int, first_name: string, last_name: string, email: string, phone: string, occasion: ?string, notes: ?string, heard: ?string}  $choices
     * @return array{0: Payment, 1: Intent}
     *
     * @throws ValidationException when the hold ran out, the trip isn't free or can't be priced
     */
    public function handle(Booking $booking, User $user, array $choices, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($booking, $user, $choices, $idempotencyKey) {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if (! $booking->isPayable()) {
                throw QuoteException::on('hold', 'Your hold on this time ran out. Go back to the yacht and pick a time again.');
            }

            $boat = $booking->boat()->with(['availabilityRules', 'experiences', 'cancellationPolicy'])->firstOrFail();
            $experience = $booking->experience_id ? $booking->experience : null; // still offered? both services check
            $this->availability->ensureFree($boat, $booking->starts_at, $booking->hours, $experience, $booking->guests, $booking);

            $promo = $booking->promo_code_id ? $booking->promoCode : null;
            if ($promo !== null) {
                $this->promos->check($promo->code, $user); // still valid (dates, limits)
            }
            $quote = $this->quotes->quote(new QuoteRequest($boat, $experience, $booking->starts_at, $booking->hours, $booking->guests, $choices['addons'], $promo?->terms(), $choices['gratuity']));

            $booking->forceFill([
                'contact_first_name' => $choices['first_name'],
                'contact_last_name' => $choices['last_name'],
                'contact_email' => $choices['email'],
                'contact_phone' => $choices['phone'],
                'occasion' => $choices['occasion'],
                'customer_notes' => $choices['notes'],
                'heard_from' => $choices['heard'],
                'promo_code_id' => $quote->discountCents > 0 ? $promo?->getKey() : null,
            ])->save();
            $this->bookings->applyQuote($booking, $quote);

            $payment = $booking->payments()->where('idempotency_key', $idempotencyKey)->first() // the same click sent twice
                ?? $booking->payments()->whereIn('status', [PaymentIntentStatus::RequiresPayment, PaymentIntentStatus::RequiresAction])->latest('id')->first();
            if ($payment === null) {
                $payment = (new Payment)->forceFill([
                    'booking_id' => $booking->getKey(),
                    'user_id' => $user->getKey(),
                    'provider' => $this->gateway->name(),
                    'kind' => 'full',
                    'amount_cents' => $booking->total_cents,
                    'currency' => config('payments.currency', 'usd'),
                    'status' => PaymentIntentStatus::RequiresPayment,
                    'idempotency_key' => $idempotencyKey,
                ]);
                $payment->save();
                $intent = $this->gateway->createIntent($payment, $booking);
                $payment->forceFill(['provider_intent_id' => $intent->id])->save();

                return [$payment, $intent];
            }

            if ($payment->amount_cents !== $booking->total_cents) {
                $payment->forceFill(['amount_cents' => $booking->total_cents])->save();

                return [$payment, $this->gateway->updateAmount($payment)];
            }

            return [$payment, $this->gateway->retrieve($payment)];
        });
    }
}
