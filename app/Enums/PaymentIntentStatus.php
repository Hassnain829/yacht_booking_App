<?php

namespace App\Enums;

/**
 * Where one attempt to pay a booking stands (payments.status, PLAN §6.6), mirrored from the provider's intent.
 * The booking's own `payment_status` (PaymentStatus) only turns `paid` when an attempt succeeds.
 */
enum PaymentIntentStatus: string
{
    case RequiresPayment = 'requires_payment';
    case RequiresAction = 'requires_action'; // 3-D Secure
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';

    /** Still waiting for the guest or the bank. */
    public function isOpen(): bool
    {
        return in_array($this, [self::RequiresPayment, self::RequiresAction, self::Processing], true);
    }

    /** Stripe's PaymentIntent status → ours. */
    public static function fromStripe(string $status): self
    {
        return match ($status) {
            'succeeded' => self::Succeeded,
            'processing' => self::Processing,
            'requires_action', 'requires_confirmation' => self::RequiresAction,
            'canceled' => self::Canceled,
            default => self::RequiresPayment, // requires_payment_method (also after a decline), requires_capture
        };
    }
}
