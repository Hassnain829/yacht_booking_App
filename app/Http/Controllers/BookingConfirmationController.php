<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\PublicCatalog;
use App\Support\Calendar\TripCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "You're booked" (PLAN 4.6): the paid booking's confirmation page and its calendar file. Only the guest's own
 * booking (else 404). A booking still on hold goes back to its checkout.
 */
class BookingConfirmationController extends Controller
{
    public function show(Booking $booking, PublicCatalog $catalog): View|RedirectResponse
    {
        Gate::authorize('view', $booking);
        if ($booking->status === BookingStatus::Hold) {
            return redirect()->route('checkout.show', $booking);
        }
        abort_unless(in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Completed], true), 404);
        $booking->load(['boat.harbor', 'experience', 'items', 'payments' => fn ($query) => $query->where('status', 'succeeded')]);

        return view('booking.confirmation', [
            'booking' => $booking,
            'yacht' => $catalog->yacht($booking->boat->slug),
            'payment' => $booking->payments->last(),
            'business' => config('site.business'),
        ]);
    }

    /** The trip as an .ics file (the same one the confirmation email carries). */
    public function calendar(Booking $booking): Response
    {
        Gate::authorize('view', $booking);
        abort_unless(in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Completed], true), 404);

        return response(TripCalendar::ics($booking), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.TripCalendar::filename($booking).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
