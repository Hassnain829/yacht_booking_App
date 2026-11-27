<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\BookingConfirmed;
use App\Notifications\NewBookingReceived;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Payments\SavedCards;
use App\Services\PromoService;
use App\Services\SettingsService;
use App\Support\Payments\Intent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The provider says what happened to a payment (PLAN §7.3, §8.4) — from its webhook, or our server asking it on
 * the guest's return; never the browser's word. Idempotent: the same success reported twice changes nothing.
 *
 * A success confirms the held booking (paid in full, D22), records the promo redemption and, after the commit,
 * emails the guest (with the .ics) and the business. If the hold had run out and someone else took the time
 * meanwhile, the booking stays expired, marked paid, with a note to refund it (the admin refunds it, 5.4).
 *
 * Since 5.3 the provider's processing fee and the paying card are kept on the payment, and a card the guest asked
 * to save is added to their saved cards.
 */
class ConfirmPaidBooking
{
    public function __construct(
        private AvailabilityService $availability,
        private BookingService $bookings,
        private PromoService $promos,
        private SettingsService $settings,
        private SavedCards $cards,
    ) {}

    public function handle(Payment $payment, Intent $intent): Booking
    {
        $confirmed = false;

        $booking = DB::transaction(function () use ($payment, $intent, &$confirmed) {
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentIntentStatus::Succeeded) {
                return $booking; // already handled
            }
            if ($intent->status !== PaymentIntentStatus::Succeeded) {
                $payment->forceFill([
                    'status' => $intent->status,
                    'failure_code' => $intent->failureCode,
                    'failure_message' => $intent->failureMessage === null ? null : mb_substr($intent->failureMessage, 0, 255),
                ])->save();

                return $booking;
            }

            $payment->forceFill([
                'status' => PaymentIntentStatus::Succeeded,
                'amount_cents' => $intent->amountCents,
                'provider_charge_id' => $intent->chargeId,
                'card_brand' => $intent->cardBrand === null ? null : mb_substr($intent->cardBrand, 0, 20),
                'card_last4' => $intent->cardLast4,
                'provider_payment_method_id' => $intent->paymentMethodId ?? $payment->provider_payment_method_id,
                'fee_cents' => $intent->feeCents ?? $payment->fee_cents,
                'failure_code' => null,
                'failure_message' => null,
                'paid_at' => now(),
            ])->save();
            if ($payment->save_card && $intent->card() !== null && $payment->user !== null) {
                $this->cards->remember($payment->user, $intent->card());
            }
            $booking->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'amount_paid_cents' => $booking->amount_paid_cents + $intent->amountCents,
            ])->save();

            if (! in_array($booking->status, [BookingStatus::Hold, BookingStatus::Expired], true)) {
                return $booking; // e.g. cancelled by the admin meanwhile: the payment is recorded, nothing else changes
            }

            $card = $intent->cardLast4 ? ' ('.ucfirst((string) $intent->cardBrand).' •••• '.$intent->cardLast4.')' : '';
            if ($booking->takesTime() || $this->timeStillFree($booking)) {
                $this->bookings->transition($booking, BookingStatus::Confirmed, $booking->customer, 'Paid in full'.$card, [
                    'confirmed_at' => now(),
                    'hold_expires_at' => null,
                ]);
                if ($booking->promo_code_id && $booking->discount_cents > 0) {
                    $this->promos->redeem($booking->promoCode, $booking, $booking->discount_cents);
                }
                $confirmed = true;
            } else {
                if ($booking->status === BookingStatus::Hold) {
                    $this->bookings->transition($booking, BookingStatus::Expired, null, 'The hold ran out before the payment');
                }
                $booking->forceFill(['admin_notes' => trim(($booking->admin_notes ?? '')."\nPaid".$card.' after its hold ran out, and the time was taken meanwhile: refund the guest.')])->save();
            }

            return $booking;
        });

        if ($confirmed) {
            $this->notify($booking);
        }

        return $booking;
    }

    /** A hold that ran out (or was let go by the expiry job) can still be confirmed when nobody took its time. */
    private function timeStillFree(Booking $booking): bool
    {
        $boat = $booking->boat()->with(['availabilityRules', 'experiences', 'cancellationPolicy'])->first();
        $experience = $booking->experience_id ? $booking->experience : null;

        return $boat !== null && $this->availability->problem($boat, $booking->starts_at, $booking->hours, $experience, $booking->guests, $booking, checkNotice: false) === null;
    }

    /** After the commit: the guest's confirmation (with the .ics) and the business's copy. */
    private function notify(Booking $booking): void
    {
        DB::afterCommit(function () use ($booking) {
            if ($booking->contact_email) {
                Notification::route('mail', [$booking->contact_email => trim($booking->contact_first_name.' '.$booking->contact_last_name)])
                    ->notify(new BookingConfirmed($booking));
            }
            $business = (string) ($this->settings->all()['business_email'] ?? '');
            if ($business !== '') {
                Notification::route('mail', $business)->notify(new NewBookingReceived($booking));
            }
        });
    }
}
