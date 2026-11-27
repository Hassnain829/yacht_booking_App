<?php

namespace App\Enums;

/**
 * Where a refund stands (refunds.status, PLAN §7.4, D23). A guest's cancellation asks for one (`requested`); no
 * money moves until the admin approves it — then it goes to the provider (`pending` → `succeeded` / `failed`).
 * The admin's own refunds go to the provider straight away.
 *
 *   requested ──approve──▶ pending ──provider──▶ succeeded · failed · canceled
 *   requested ──decline──▶ rejected
 */
enum RefundStatus: string
{
    case Requested = 'requested';
    case Rejected = 'rejected';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';

    /** What the admin sees. */
    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting approval',
            self::Rejected => 'Declined',
            self::Pending => 'Processing',
            self::Succeeded => 'Refunded',
            self::Failed => 'Failed',
            self::Canceled => 'Cancelled',
        };
    }

    /** The admin pill's tone (x-admin.pill). */
    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Pending => 'brass',
            self::Succeeded => 'success',
            self::Rejected, self::Canceled => 'muted',
            self::Failed => 'danger',
        };
    }

    /** Money that is on its way back or may be (counts against what can still be refunded). */
    public function holdsMoney(): bool
    {
        return in_array($this, [self::Requested, self::Pending, self::Succeeded], true);
    }

    /** Stripe's refund status → ours. */
    public static function fromStripe(?string $status): self
    {
        return match ($status) {
            'succeeded' => self::Succeeded,
            'failed' => self::Failed,
            'canceled' => self::Canceled,
            default => self::Pending, // pending, requires_action
        };
    }
}
