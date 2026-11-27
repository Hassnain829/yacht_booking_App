<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Enums\RefundReason;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\GuestCancelledTrip;
use App\Notifications\TripCancelled;
use App\Services\AuditService;
use App\Services\BookingService;
use App\Services\CancellationPolicyService;
use App\Services\RefundService;
use App\Support\Booking\CancelReasons;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelling a confirmed trip (PLAN §7.4, 5.4). The time is freed at once (a cancelled booking doesn't take the
 * boat's time); the money follows D23:
 *
 *  - **the guest** cancels online before the trip: the refund the booking's policy gives, less the card fee, is
 *    asked for — it waits for the admin's approval (RefundService::approve / reject);
 *  - **the admin** cancels (the guest asked by phone, weather, the owner, …) with a reason and the password, and
 *    chooses the refund — the policy's, the full amount, a custom amount or none, keeping the card fee back or
 *    not — which is sent at once.
 *
 * Inside a transaction that locks the booking; the history line, the audit entry and the emails go with it.
 */
class CancelBooking
{
    public function __construct(
        private BookingService $bookings,
        private CancellationPolicyService $policy,
        private RefundService $refunds,
        private AuditService $audit,
    ) {}

    /**
     * @return array{0: Booking, 1: Refund|null}
     *
     * @throws ValidationException on `cancel` when the trip can't be cancelled online any more
     */
    public function byGuest(Booking $booking, User $guest, string $reason, ?string $note, string $idempotencyKey): array
    {
        $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null && $existing->booking_id === $booking->getKey()) {
            return [$booking->refresh(), $existing]; // the same request sent twice
        }

        [$booking, $refund] = DB::transaction(function () use ($booking, $guest, $reason, $note, $idempotencyKey) {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if ($booking->status !== BookingStatus::Confirmed || $booking->starts_at->lessThanOrEqualTo(CarbonImmutable::now())) {
                throw ValidationException::withMessages(['cancel' => 'This trip can\'t be cancelled online any more. Please call us.']);
            }
            $preview = $this->policy->preview($booking);
            $label = CancelReasons::label($reason);
            $this->bookings->transition($booking, BookingStatus::Cancelled, $guest, 'Cancelled by the guest: '.$label, [
                'cancelled_at' => now(),
                'cancelled_by_role' => 'customer',
                'cancellation_reason' => mb_substr($label.($note ? ' — '.$note : ''), 0, 1000),
            ]);

            return [$booking, $this->refunds->request($booking, $guest, $preview, $reason, $note, $idempotencyKey)];
        });

        $this->refunds->notifyGuest($booking, new TripCancelled($booking, $refund, byGuest: true));
        $this->refunds->notifyBusiness(new GuestCancelledTrip($booking, $refund));

        return [$booking, $refund];
    }

    /**
     * @param  'policy'|'full'|'custom'|'none'  $refundMode
     * @return array{0: Booking, 1: Refund|null}
     *
     * @throws ValidationException on the "sensitive" bag when it can't be cancelled or the amount is wrong
     */
    public function byAdmin(Booking $booking, User $admin, RefundReason $cause, string $refundMode, ?int $customCents, bool $keepFee, string $reason, string $idempotencyKey): array
    {
        if (Refund::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return [$booking->refresh(), Refund::query()->where('idempotency_key', $idempotencyKey)->first()];
        }

        [$booking, $refund] = DB::transaction(function () use ($booking, $admin, $cause, $refundMode, $customCents, $keepFee, $reason, $idempotencyKey) {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if ($booking->status !== BookingStatus::Confirmed || $booking->ends_at->lessThanOrEqualTo(CarbonImmutable::now())) {
                throw ValidationException::withMessages(['action' => 'Only a confirmed trip that hasn\'t ended can be cancelled. Refund a past trip instead.'])->errorBag('sensitive');
            }
            $preview = $this->policy->preview($booking);
            $amount = match ($refundMode) {
                'policy' => $preview['policy_cents'],
                'full' => $this->policy->refundableCents($booking),
                'custom' => (int) $customCents,
                default => 0,
            };
            $fee = $keepFee && in_array($refundMode, ['policy', 'full'], true) ? min($amount, $this->policy->feeToKeep($booking)) : 0;
            $amount -= $fee;

            $this->bookings->transition($booking, BookingStatus::Cancelled, $admin, $cause->label().': '.$reason, [
                'cancelled_at' => now(),
                'cancelled_by_role' => 'admin',
                'cancellation_reason' => mb_substr($cause->label().' — '.$reason, 0, 1000),
            ]);
            $this->audit->record('booking.cancelled', $booking, ['status' => BookingStatus::Confirmed->value], ['status' => BookingStatus::Cancelled->value, 'refund' => $refundMode, 'refund_cents' => $amount], $reason, $admin);
            $refund = $amount > 0 || $refundMode === 'custom'
                ? $this->refunds->open($booking, $admin, $amount, $fee, $cause, $reason, $idempotencyKey, $refundMode === 'policy' ? $preview['percent'] : null)
                : null;

            return [$booking, $refund];
        });

        if ($refund !== null) {
            $refund = $this->refunds->send($refund, notify: false);
        }
        $this->refunds->notifyGuest($booking, new TripCancelled($booking, $refund, byGuest: false));

        return [$booking, $refund];
    }
}
