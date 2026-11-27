<?php

namespace App\Http\Requests\Admin;

use App\Models\Booking;
use App\Models\Refund;

/**
 * The admin decides a guest's refund (PLAN 5.4, D23): a reason and the password. The refund must belong to the
 * booking in the URL (else 404). ApproveRefundRequest / DeclineRefundRequest.
 */
abstract class RefundDecisionRequest extends SensitiveActionRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');
        $refund = $this->route('refund');
        abort_unless($booking instanceof Booking && $refund instanceof Refund && $refund->booking_id === $booking->getKey(), 404);

        return $this->user()->can('issue-refunds');
    }
}
