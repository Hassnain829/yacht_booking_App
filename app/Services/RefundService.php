<?php

namespace App\Services;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundDeclined;
use App\Notifications\RefundSent;
use App\Services\Payments\PaymentGateway;
use App\Support\Money;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Refunds (PLAN §7.4, 5.4). **No money goes back without the admin (D23, user decision 2026-11-26):** a guest's
 * cancellation only asks for the refund its policy gives (`requested`); the admin approves it — the amount can be
 * changed — or declines it. The admin's own refunds (a phone request, weather, the owner, a complaint, a payment
 * that came in after the time was taken) go out at once: the admin is the approver.
 *
 * An approved refund goes to the provider against the payment with money left, with the refund's own idempotency
 * key (a retry never refunds twice). The money is counted on the payment and the booking once, when the provider
 * says it succeeded (its answer, or the signed webhook). Every admin decision is audit-logged.
 */
class RefundService
{
    public function __construct(
        private PaymentGateway $gateway,
        private CancellationPolicyService $policy,
        private AuditService $audit,
        private SettingsService $settings,
    ) {}

    /**
     * A refund asked for by the guest's cancellation (CancelBooking, inside its transaction). Nothing is sent.
     *
     * @param  array{percent: int, fee_cents: int, refund_cents: int}  $preview
     */
    public function request(Booking $booking, User $guest, array $preview, string $guestReason, ?string $note, string $idempotencyKey): ?Refund
    {
        if ($preview['refund_cents'] <= 0) {
            return null;
        }

        return tap((new Refund)->forceFill([
            'booking_id' => $booking->getKey(),
            'amount_cents' => $preview['refund_cents'],
            'fee_cents' => $preview['fee_cents'],
            'policy_pct' => $preview['percent'],
            'reason' => RefundReason::CustomerCancel,
            'status' => RefundStatus::Requested,
            'guest_reason' => $guestReason,
            'note' => $note === null ? null : mb_substr($note, 0, 1000),
            'idempotency_key' => $idempotencyKey,
            'requested_by' => $guest->getKey(),
        ]))->save();
    }

    /**
     * The admin's own refund, approved by them (inside the caller's transaction, the booking locked). Send it with
     * send() after the commit.
     *
     * @throws ValidationException on `amount` (the "sensitive" bag) when it is more than can be refunded
     */
    public function open(Booking $booking, User $admin, int $amountCents, int $feeCents, RefundReason $reason, string $note, string $idempotencyKey, ?int $policyPct = null): Refund
    {
        $this->ensureRefundable($booking, $amountCents);
        $refund = tap((new Refund)->forceFill([
            'booking_id' => $booking->getKey(),
            'amount_cents' => $amountCents,
            'fee_cents' => $feeCents,
            'policy_pct' => $policyPct,
            'reason' => $reason,
            'status' => RefundStatus::Pending,
            'note' => mb_substr($note, 0, 1000),
            'idempotency_key' => $idempotencyKey,
            'requested_by' => $admin->getKey(),
            'decided_by' => $admin->getKey(),
            'decided_at' => now(),
        ]))->save();
        $this->audit->record('refund.issued', $booking, [], ['refund' => $refund->ulid, 'amount_cents' => $amountCents, 'reason' => $reason->value], $note, $admin);

        return $refund;
    }

    /** The admin refunds a booking without cancelling it (a complaint, a late payment, goodwill) — sent at once. */
    public function issue(Booking $booking, User $admin, int $amountCents, RefundReason $reason, string $note, string $idempotencyKey): Refund
    {
        $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing; // the same form sent twice
        }
        $refund = DB::transaction(function () use ($booking, $admin, $amountCents, $reason, $note, $idempotencyKey) {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            return $this->open($booking, $admin, $amountCents, 0, $reason, $note, $idempotencyKey);
        });

        return $this->send($refund, notify: true);
    }

    /**
     * The admin approves a guest's refund, for the amount they choose (the policy's by default), and it is sent.
     *
     * @throws ValidationException on the "sensitive" bag when it was decided already or the amount is too high
     */
    public function approve(Refund $refund, User $admin, int $amountCents, string $reason): Refund
    {
        $refund = DB::transaction(function () use ($refund, $admin, $amountCents, $reason) {
            $booking = Booking::query()->whereKey($refund->booking_id)->lockForUpdate()->firstOrFail();
            $refund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            if ($refund->status !== RefundStatus::Requested) {
                throw ValidationException::withMessages(['action' => 'This refund was decided already.'])->errorBag('sensitive');
            }
            $this->ensureRefundable($booking, $amountCents, $refund);
            $before = ['status' => $refund->status->value, 'amount_cents' => $refund->amount_cents];
            $refund->forceFill([
                'status' => RefundStatus::Pending,
                'amount_cents' => $amountCents,
                'decision_note' => mb_substr($reason, 0, 500),
                'decided_by' => $admin->getKey(),
                'decided_at' => now(),
            ])->save();
            $this->audit->record('refund.approved', $booking, $before, ['status' => RefundStatus::Pending->value, 'amount_cents' => $amountCents, 'refund' => $refund->ulid], $reason, $admin);

            return $refund;
        });

        return $this->send($refund, notify: true);
    }

    /**
     * The admin declines a guest's refund, with the reason the guest is sent.
     *
     * @throws ValidationException on the "sensitive" bag when it was decided already
     */
    public function reject(Refund $refund, User $admin, string $reason): Refund
    {
        $refund = DB::transaction(function () use ($refund, $admin, $reason) {
            $refund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            if ($refund->status !== RefundStatus::Requested) {
                throw ValidationException::withMessages(['action' => 'This refund was decided already.'])->errorBag('sensitive');
            }
            $refund->forceFill([
                'status' => RefundStatus::Rejected,
                'decision_note' => mb_substr($reason, 0, 500),
                'decided_by' => $admin->getKey(),
                'decided_at' => now(),
            ])->save();
            $this->audit->record('refund.declined', $refund->booking, ['status' => RefundStatus::Requested->value], ['status' => RefundStatus::Rejected->value, 'refund' => $refund->ulid], $reason, $admin);

            return $refund;
        });
        $this->notifyGuest($refund->booking, new RefundDeclined($refund));

        return $refund;
    }

    /**
     * An approved refund to the provider, against the paid payment with enough money left. A provider error marks
     * it failed (the admin sees why on the booking); the idempotency key makes a retry safe.
     */
    public function send(Refund $refund, bool $notify): Refund
    {
        $refund->refresh();
        if ($refund->status !== RefundStatus::Pending || $refund->provider_refund_id !== null) {
            return $refund;
        }
        $payment = $refund->booking->payments()->where('status', PaymentIntentStatus::Succeeded)->get()
            ->filter(fn (Payment $payment) => $payment->refundableCents() >= $refund->amount_cents)
            ->sortByDesc(fn (Payment $payment) => $payment->refundableCents())
            ->first();
        if ($payment === null) {
            return $this->fail($refund, 'No single payment on this booking has '.Money::format($refund->amount_cents, 'always').' left to refund.');
        }

        try {
            $result = $this->gateway->refund($payment, $refund->amount_cents, $refund->idempotency_key, [
                'booking' => $refund->booking->reference,
                'refund' => $refund->ulid,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Refund failed at the provider', ['refund' => $refund->ulid, 'error' => $exception->getMessage()]);

            return $this->fail($refund->forceFill(['payment_id' => $payment->getKey()]), 'The payment provider refused the refund: '.$exception->getMessage());
        }

        $refund->forceFill(['payment_id' => $payment->getKey(), 'provider_refund_id' => $result->id])->save();
        $refund = $this->applyStatus($refund, $result->status, $result->failureReason);
        if ($notify && $refund->status !== RefundStatus::Failed && $refund->status !== RefundStatus::Canceled) {
            $this->notifyGuest($refund->booking, new RefundSent($refund));
        }

        return $refund;
    }

    /**
     * The provider's word on a refund (its answer, or a signed webhook). The money is counted on the payment and
     * the booking once, when it succeeds; a refund that already succeeded doesn't change any more.
     */
    public function applyStatus(Refund $refund, RefundStatus $status, ?string $failureReason = null): Refund
    {
        return DB::transaction(function () use ($refund, $status, $failureReason) {
            $refund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            if ($refund->status === RefundStatus::Succeeded || $refund->status === $status || ! in_array($status, [RefundStatus::Pending, RefundStatus::Succeeded, RefundStatus::Failed, RefundStatus::Canceled], true)) {
                return $refund;
            }
            $refund->forceFill([
                'status' => $status,
                'failure_reason' => $failureReason === null ? null : mb_substr($failureReason, 0, 255),
                'refunded_at' => $status === RefundStatus::Succeeded ? now() : null,
            ])->save();

            if ($status === RefundStatus::Succeeded) {
                $booking = Booking::query()->whereKey($refund->booking_id)->lockForUpdate()->firstOrFail();
                $payment = $refund->payment_id ? Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->first() : null;
                $payment?->forceFill(['amount_refunded_cents' => $payment->amount_refunded_cents + $refund->amount_cents])->save();
                $refunded = $booking->amount_refunded_cents + $refund->amount_cents;
                $booking->forceFill([
                    'amount_refunded_cents' => $refunded,
                    'payment_status' => $refunded >= $booking->amount_paid_cents ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
                ])->save();
            }

            return $refund;
        });
    }

    /**
     * A refund event from the provider's webhook (refund.created / updated / failed): found by its id, or by our
     * refund's id in its metadata when the webhook came before we stored the provider's id.
     *
     * @param  array<string, mixed>  $object
     */
    public function syncFromProvider(array $object): void
    {
        $id = is_string($object['id'] ?? null) ? $object['id'] : null;
        $ours = is_string($object['metadata']['refund'] ?? null) ? $object['metadata']['refund'] : null;
        $refund = ($id ? Refund::query()->where('provider_refund_id', $id)->first() : null)
            ?? ($ours ? Refund::query()->where('ulid', $ours)->first() : null);
        if ($refund === null) {
            return; // made in the Stripe dashboard: not ours
        }
        if ($refund->provider_refund_id === null && $id !== null) {
            $refund->forceFill(['provider_refund_id' => $id])->save();
        }
        $this->applyStatus($refund, RefundStatus::fromStripe(is_string($object['status'] ?? null) ? $object['status'] : null), is_string($object['failure_reason'] ?? null) ? $object['failure_reason'] : null);
    }

    /**
     * Refund at least a cent, and never more than can still be refunded.
     *
     * @throws ValidationException on `amount` (the "sensitive" bag)
     */
    private function ensureRefundable(Booking $booking, int $amountCents, ?Refund $except = null): void
    {
        $max = $this->policy->refundableCents($booking, $except);
        if ($amountCents < 1 || $amountCents > $max) {
            throw ValidationException::withMessages(['amount' => $max > 0
                ? 'Refund between $0.01 and '.Money::format($max, 'always').' (what is left of the payment).'
                : 'Nothing is left to refund on this booking.'])->errorBag('sensitive');
        }
    }

    private function fail(Refund $refund, string $why): Refund
    {
        $refund->forceFill(['status' => RefundStatus::Failed, 'failure_reason' => mb_substr($why, 0, 255)])->save();

        return $refund;
    }

    /** Mail to the booking's contact, after the commit. */
    public function notifyGuest(Booking $booking, BaseNotification $notification): void
    {
        if (! $booking->contact_email) {
            return;
        }
        DB::afterCommit(fn () => Notification::route('mail', [$booking->contact_email => trim($booking->contact_first_name.' '.$booking->contact_last_name)])->notify($notification));
    }

    /** Mail to Settings → business email, after the commit. */
    public function notifyBusiness(BaseNotification $notification): void
    {
        $business = (string) ($this->settings->all()['business_email'] ?? '');
        if ($business !== '') {
            DB::afterCommit(fn () => Notification::route('mail', $business)->notify($notification));
        }
    }
}
