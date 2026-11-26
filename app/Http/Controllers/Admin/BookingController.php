<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoatOwnership;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingNotesRequest;
use App\Http\Requests\Admin\BookingStatusRequest;
use App\Models\Booking;
use App\Services\BookingService;
use App\Support\Admin\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Bookings (PLAN 4.8): the list (search, whitelisted filters and sort, pages), a month calendar, and a
 * booking's page — guest contact (admin only), every price line, payments, history, notes and the status changes
 * allowed now (BookingService). Expired holds are left out unless asked for. Cancellations with refunds and
 * captain assignment come with Phases 5 and 8.
 */
class BookingController extends Controller
{
    private const SORTS = ['date' => 'starts_at', 'total' => 'total_cents', 'guests' => 'guests', 'ref' => 'reference', 'booked' => 'created_at'];

    public function index(Request $request): View
    {
        Gate::authorize('manage-bookings');

        $list = new ListQuery($request);
        $q = $list->search();
        $status = $list->pick('status', array_map(fn (BookingStatus $s) => $s->value, BookingStatus::cases()));
        $when = $list->pick('when', ['upcoming', 'past']);
        $kind = $list->pick('kind', [BoatOwnership::House->value, BoatOwnership::Owner->value]);
        $captain = $list->pick('captain', ['none', 'assigned']);
        [$sort, $column, $dir] = $list->sort(self::SORTS, 'date', 'desc');
        $like = '%'.addcslashes($q, '%_\\').'%';

        $bookings = Booking::query()
            ->with(['boat:id,title,ownership', 'experience:id,name', 'captain:id,name'])
            ->when($status, fn ($query) => $query->where('status', $status), fn ($query) => $query->where('status', '!=', BookingStatus::Expired))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('reference', 'like', $like)
                ->orWhere('contact_first_name', 'like', $like)
                ->orWhere('contact_last_name', 'like', $like)
                ->orWhere('contact_email', 'like', $like)
                ->orWhereHas('boat', fn ($boat) => $boat->where('title', 'like', $like))))
            ->when($when === 'upcoming', fn ($query) => $query->where('ends_at', '>', now()))
            ->when($when === 'past', fn ($query) => $query->where('ends_at', '<=', now()))
            ->when($kind, fn ($query) => $query->whereHas('boat', fn ($boat) => $boat->where('ownership', $kind)))
            ->when($captain === 'none', fn ($query) => $query->whereNull('captain_id'))
            ->when($captain === 'assigned', fn ($query) => $query->whereNotNull('captain_id'))
            ->orderBy($column, $dir)
            ->orderBy('id', $dir)
            ->paginate(ListQuery::PER_PAGE)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'filters' => compact('q', 'status', 'when', 'kind', 'captain'),
            'statuses' => collect(BookingStatus::cases())->mapWithKeys(fn (BookingStatus $s) => [$s->value => $s->label()])->all(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /** A month of trips, day by day (Chicago dates): held, confirmed and past ones. `?month=2027-06`. */
    public function calendar(Request $request): View
    {
        Gate::authorize('manage-bookings');

        $tz = config('app.display_timezone');
        $today = CarbonImmutable::now($tz);
        $month = $request->query('month');
        $first = is_string($month) && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month) ? CarbonImmutable::createFromFormat('!Y-m', $month, $tz) : $today->startOfMonth();
        if ($first->lessThan($today->startOfMonth()->subMonths(24)) || $first->greaterThan($today->startOfMonth()->addMonths(24))) {
            $first = $today->startOfMonth();
        }

        $trips = Booking::query()
            ->with(['boat:id,title', 'experience:id,name'])
            ->whereIn('status', [BookingStatus::Hold, BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow])
            ->where('starts_at', '>=', $first->utc())
            ->where('starts_at', '<', $first->addMonth()->utc())
            ->orderBy('starts_at')
            ->get()
            ->reject(fn (Booking $booking) => $booking->status === BookingStatus::Hold && ! $booking->isPayable())
            ->groupBy(fn (Booking $booking) => $booking->starts_at->setTimezone($tz)->toDateString());

        return view('admin.bookings.calendar', [
            'first' => $first,
            'today' => $today->toDateString(),
            'trips' => $trips,
            'previous' => $first->subMonth()->format('Y-m'),
            'next' => $first->addMonth()->format('Y-m'),
        ]);
    }

    public function show(Booking $booking, BookingService $bookings): View
    {
        Gate::authorize('manage-bookings');
        $booking->load(['boat.harbor', 'experience', 'items', 'captain', 'customer', 'payments', 'promoCode', 'statusHistory.actor']);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'targets' => $bookings->adminTargets($booking),
        ]);
    }

    public function notes(BookingNotesRequest $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        $bookings->updateNotes($booking, $request->notes(), $request->user());

        return redirect()->route('admin.bookings.show', $booking->reference)->with('status', 'Notes saved.');
    }

    public function status(BookingStatusRequest $request, Booking $booking, BookingService $bookings): RedirectResponse
    {
        $to = $request->status();
        $bookings->changeStatusByAdmin($booking, $to, $request->user(), $request->reason());

        return redirect()->route('admin.bookings.show', $booking->reference)->with('status', "{$booking->reference} is now {$to->label()}.");
    }
}
