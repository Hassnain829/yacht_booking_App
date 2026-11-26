<?php

namespace App\Http\Controllers\Account;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PublicCatalog;
use App\Services\SettingsService;
use App\Support\Booking\RefundPreview;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "My trips" + trip detail (PLAN 4.7): the signed-in guest's own bookings. Someone else's trip is a 404, never a
 * 403 (BookingPolicy). A hold that can still be paid shows with "Finish checkout"; one that ran out is not a trip.
 * Online cancellation and refunds come with Phase 5 and the problem report with Phase 7 — until then the trip page
 * shows the refund the policy gives and how to reach the team.
 */
class TripController extends Controller
{
    public function index(Request $request, PublicCatalog $catalog): View
    {
        $now = CarbonImmutable::now();
        $trips = Booking::query()
            ->where('customer_id', $request->user()->getKey())
            ->whereIn('status', [BookingStatus::Hold, BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow])
            ->with(['boat.harbor', 'experience', 'payments' => fn ($query) => $query->where('status', PaymentIntentStatus::Succeeded)])
            ->orderBy('starts_at')
            ->get()
            ->reject(fn (Booking $booking) => $booking->status === BookingStatus::Hold && ! $booking->isPayable($now));

        $isUpcoming = fn (Booking $booking) => in_array($booking->status, [BookingStatus::Hold, BookingStatus::Confirmed], true) && $booking->ends_at->greaterThan($now);

        return view('account.trips', [
            'upcoming' => $trips->filter($isUpcoming)->values(),
            'past' => $trips->reject($isUpcoming)->sortByDesc(fn (Booking $booking) => $booking->starts_at->getTimestamp())->values(),
            'covers' => $trips->mapWithKeys(fn (Booking $booking) => [$booking->ulid => $this->cover($booking, $catalog)])->all(),
            'complaintUntil' => $this->complaintWindow(...),
        ]);
    }

    public function show(Booking $booking, PublicCatalog $catalog): View|RedirectResponse
    {
        Gate::authorize('view', $booking);
        if ($booking->status === BookingStatus::Hold) {
            abort_unless($booking->isPayable(), 404);

            return redirect()->route('checkout.show', $booking);
        }
        abort_if($booking->status === BookingStatus::Expired, 404);

        $booking->load(['boat.harbor', 'experience', 'items', 'captain', 'payments' => fn ($query) => $query->where('status', PaymentIntentStatus::Succeeded)]);
        $now = CarbonImmutable::now();
        $upcoming = $booking->status === BookingStatus::Confirmed && $booking->ends_at->greaterThan($now);
        $complaintUntil = $this->complaintWindow($booking);

        return view('account.trip', [
            'trip' => $booking,
            'cover' => $this->cover($booking, $catalog),
            'upcoming' => $upcoming,
            'refund' => $upcoming ? RefundPreview::for($booking, $now) : null,
            'payment' => $booking->payments->last(),
            'complaintUntil' => $complaintUntil,
            'canReport' => $booking->status === BookingStatus::Completed && $complaintUntil->greaterThan($now),
            'business' => config('site.business'),
        ]);
    }

    /** Problems can be reported until `complaint_window_hours` (Settings) after the trip ends (PLAN §7.6). */
    private function complaintWindow(Booking $booking): CarbonImmutable
    {
        return $booking->ends_at->addHours((int) (app(SettingsService::class)->all()['complaint_window_hours'] ?? 72));
    }

    /** The boat's cover from the public catalog, or the placeholder when the boat isn't listed any more. */
    private function cover(Booking $booking, PublicCatalog $catalog): string
    {
        return $catalog->yacht((string) $booking->boat?->slug)?->cover ?? PublicCatalog::PLACEHOLDER;
    }
}
