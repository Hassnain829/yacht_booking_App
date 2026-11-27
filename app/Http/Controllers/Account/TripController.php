<?php

namespace App\Http\Controllers\Account;

use App\Actions\Booking\CancelBooking;
use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CancelTripRequest;
use App\Models\Booking;
use App\Services\CancellationPolicyService;
use App\Services\PublicCatalog;
use App\Services\SettingsService;
use App\Support\Booking\CancelReasons;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "My trips" + trip detail (PLAN 4.7): the signed-in guest's own bookings. Someone else's trip is a 404, never a
 * 403 (BookingPolicy). A hold that can still be paid shows with "Finish checkout"; one that ran out is not a trip.
 *
 * Since 5.4 the guest cancels a confirmed trip here (CancelBooking): the time is freed at once and the refund its
 * policy gives — less the card fee — waits for the admin's approval (D23). The problem report comes with Phase 7.
 */
class TripController extends Controller
{
    public function index(Request $request, PublicCatalog $catalog): View
    {
        $now = CarbonImmutable::now();
        $trips = Booking::query()
            ->where('customer_id', $request->user()->getKey())
            ->whereIn('status', [BookingStatus::Hold, BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow])
            ->with(['boat.harbor', 'experience', 'refunds', 'payments' => fn ($query) => $query->where('status', PaymentIntentStatus::Succeeded)])
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

    public function show(Booking $booking, PublicCatalog $catalog, CancellationPolicyService $policy): View|RedirectResponse
    {
        Gate::authorize('view', $booking);
        if ($booking->status === BookingStatus::Hold) {
            abort_unless($booking->isPayable(), 404);

            return redirect()->route('checkout.show', $booking);
        }
        abort_if($booking->status === BookingStatus::Expired, 404);

        $booking->load(['boat.harbor', 'experience', 'items', 'captain', 'refunds', 'payments' => fn ($query) => $query->where('status', PaymentIntentStatus::Succeeded)]);
        $now = CarbonImmutable::now();
        $upcoming = $booking->status === BookingStatus::Confirmed && $booking->ends_at->greaterThan($now);
        $complaintUntil = $this->complaintWindow($booking);

        return view('account.trip', [
            'trip' => $booking,
            'cover' => $this->cover($booking, $catalog),
            'upcoming' => $upcoming,
            'refund' => $upcoming && $booking->starts_at->greaterThan($now) ? $policy->preview($booking, $now) : null,
            'cancelReasons' => CancelReasons::OPTIONS,
            'payment' => $booking->payments->last(),
            'complaintUntil' => $complaintUntil,
            'canReport' => $booking->status === BookingStatus::Completed && $complaintUntil->greaterThan($now),
            'business' => config('site.business'),
        ]);
    }

    /** "Cancel trip": the time is freed; the refund the policy gives is asked for and waits for the admin (D23). */
    public function cancel(CancelTripRequest $request, Booking $booking, CancelBooking $cancel): RedirectResponse
    {
        [, $refund] = $cancel->byGuest($booking, $request->user(), (string) $request->validated('reason'), $request->note(), (string) $request->validated('idempotency_key'));

        return redirect()->route('account.trips.show', $booking)->with('status', $refund ? 'cancelled-refund-requested' : 'cancelled');
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
