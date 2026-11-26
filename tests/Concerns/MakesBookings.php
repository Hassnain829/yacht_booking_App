<?php

namespace Tests\Concerns;

use App\Actions\Booking\ConfirmPaidBooking;
use App\Actions\Booking\HoldTrip;
use App\Enums\PaymentIntentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\BoatQuotes;
use App\Support\Payments\Intent;
use App\Support\Pricing\QuoteRequest;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Bookings made the real way (4.5): HoldTrip, then a payment the fake gateway confirms through ConfirmPaidBooking.
 * Needs the seeded catalog (SeedsPublicCatalog) and a boat with start times. Emails are faked.
 */
trait MakesBookings
{
    protected function holdTrip(User $user, string $boat, string $date, string $time = '12:00', int $hours = 3, int $guests = 6): Booking
    {
        Notification::fake();

        return app(HoldTrip::class)->handle($user, app(BoatQuotes::class)->find($boat), null, QuoteRequest::localStart($date, $time), $hours, $guests, [], (string) Str::uuid());
    }

    protected function paidTrip(User $user, string $boat, string $date, string $time = '12:00', int $hours = 3, int $guests = 6): Booking
    {
        $booking = $this->holdTrip($user, $boat, $date, $time, $hours, $guests);
        $payment = tap((new Payment)->forceFill([
            'booking_id' => $booking->id, 'user_id' => $user->id, 'provider' => 'fake', 'provider_intent_id' => 'fake_pi_'.Str::lower((string) Str::ulid()),
            'amount_cents' => $booking->total_cents, 'status' => PaymentIntentStatus::RequiresPayment, 'idempotency_key' => (string) Str::uuid(),
        ]))->save();

        return app(ConfirmPaidBooking::class)->handle($payment, new Intent((string) $payment->provider_intent_id, PaymentIntentStatus::Succeeded, $payment->amount_cents, cardBrand: 'visa', cardLast4: '4242'));
    }
}
