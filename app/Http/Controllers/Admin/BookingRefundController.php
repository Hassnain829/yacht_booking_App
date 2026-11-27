<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Booking\CancelBooking;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveRefundRequest;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Http\Requests\Admin\DeclineRefundRequest;
use App\Http\Requests\Admin\IssueRefundRequest;
use App\Models\Booking;
use App\Models\Refund;
use App\Services\RefundService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;

/**
 * Cancellations and refunds on a booking's admin page (PLAN 5.4, D23: no money goes back without the admin). Every
 * action takes a reason and the password (SensitiveActionRequest), is audit-logged and limited by
 * `throttle:admin-sensitive`; the forms carry an idempotency key (a double submit never refunds twice).
 *
 *  - cancel: a confirmed trip (the guest asked by phone, weather, the owner, …) with the refund the admin chooses;
 *  - store: a refund without cancelling (a complaint, a late payment, goodwill);
 *  - approve / decline: the refund a guest's online cancellation asked for.
 */
class BookingRefundController extends Controller
{
    public function cancel(CancelBookingRequest $request, Booking $booking, CancelBooking $cancel): RedirectResponse
    {
        [, $refund] = $cancel->byAdmin($booking, $request->user(), $request->cause(), $request->mode(), $request->customCents(), $request->boolean('keep_fee'), $request->reason(), (string) $request->validated('idempotency_key'));

        return $this->back($booking, $refund, "{$booking->reference} is cancelled".($refund ? ' and '.Money::format($refund->amount_cents, 'always').' is refunded.' : ' with no refund.'));
    }

    public function store(IssueRefundRequest $request, Booking $booking, RefundService $refunds): RedirectResponse
    {
        $refund = $refunds->issue($booking, $request->user(), $request->amountCents(), $request->cause(), $request->reason(), (string) $request->validated('idempotency_key'));

        return $this->back($booking, $refund, Money::format($refund->amount_cents, 'always').' is refunded to the guest.');
    }

    public function approve(ApproveRefundRequest $request, Booking $booking, Refund $refund, RefundService $refunds): RedirectResponse
    {
        $refund = $refunds->approve($refund, $request->user(), $request->amountCents(), $request->reason());

        return $this->back($booking, $refund, 'Refund approved: '.Money::format($refund->amount_cents, 'always').' is on its way to the guest.');
    }

    public function decline(DeclineRefundRequest $request, Booking $booking, Refund $refund, RefundService $refunds): RedirectResponse
    {
        $refunds->reject($refund, $request->user(), $request->reason());

        return redirect()->route('admin.bookings.show', $booking->reference)->with('status', 'Refund declined. The guest was emailed your reason.');
    }

    /** Back to the booking: done, or the provider's reason when the refund failed. */
    private function back(Booking $booking, ?Refund $refund, string $done): RedirectResponse
    {
        $redirect = redirect()->route('admin.bookings.show', $booking->reference);

        return $refund !== null && $refund->status === RefundStatus::Failed
            ? $redirect->with('error', 'The refund failed: '.$refund->failure_reason)
            : $redirect->with('status', $done);
    }
}
