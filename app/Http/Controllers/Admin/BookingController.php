<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoAdmin;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/**
 * Admin bookings: list (search, filters, sort, pagination) and detail (captain assignment, refund).
 * Every filter and sort value is checked against a whitelist (PLAN §8.2 rule 5). Phase 4: Eloquent.
 */
class BookingController extends Controller
{
    public const STATUSES = ['confirmed', 'completed', 'cancelled'];

    public const SORTS = ['date' => 'starts_at', 'total' => 'total_cents', 'guests' => 'guests', 'ref' => 'ref'];

    public const PER_PAGE = 10;

    public function index(Request $request, DemoAdmin $admin): View
    {
        $pick = fn (string $key, array $allowed, ?string $default = null) => is_string($request->query($key)) && in_array($request->query($key), $allowed, true) ? $request->query($key) : $default;
        $q = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 60) : '';
        $status = $pick('status', self::STATUSES);
        $kind = $pick('kind', ['house', 'owner']);
        $captain = $pick('captain', ['none', 'assigned']);
        $sort = $pick('sort', array_keys(self::SORTS), 'date');
        $dir = $pick('dir', ['asc', 'desc'], 'desc');

        $rows = $admin->bookings()
            ->when($q !== '', fn ($c) => $c->filter(fn (Fluent $b) => str_contains(mb_strtolower($b->ref.' '.$b->customer.' '.$b->boat), mb_strtolower($q))))
            ->when($status, fn ($c) => $c->where('status', $status))
            ->when($kind, fn ($c) => $c->where('kind', $kind))
            ->when($captain === 'none', fn ($c) => $c->where('is_upcoming', true)->whereNull('captain'))
            ->when($captain === 'assigned', fn ($c) => $c->whereNotNull('captain'))
            ->sortBy(fn (Fluent $b) => $sort === 'date' ? $b->starts_at->getTimestamp() : $b->{self::SORTS[$sort]}, SORT_REGULAR, $dir === 'desc')
            ->values();

        $page = max(1, min((int) $request->query('page', 1), max(1, (int) ceil($rows->count() / self::PER_PAGE))));
        $paginator = new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page, [
            'path' => $request->url(),
            'query' => array_filter(compact('q', 'status', 'kind', 'captain') + ['sort' => $sort === 'date' ? null : $sort, 'dir' => $dir === 'desc' ? null : $dir]),
        ]);

        return view('admin.bookings.index', [
            'bookings' => $paginator,
            'filters' => compact('q', 'status', 'kind', 'captain'),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function show(string $ref, DemoAdmin $admin): View
    {
        $booking = $admin->booking($ref);
        abort_unless($booking, 404);

        $captains = $admin->captains()->where('active', true)->values()->map(function (Fluent $captain) use ($admin, $booking) {
            $captain->checks = $admin->captainChecks($booking, $captain);
            $captain->eligible = collect($captain->checks)->every(fn ($check) => $check[1]);

            return $captain;
        });

        return view('admin.bookings.show', [
            'booking' => $booking,
            'captains' => $captains,
            'current' => $captains->firstWhere('id', $booking->captain_id),
        ]);
    }
}
