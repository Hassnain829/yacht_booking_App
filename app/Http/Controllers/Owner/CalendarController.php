<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/**
 * Calendar of one approved boat: trips + owner blocks (rendered by pages/owner-calendar.js).
 * Phase 1: blocks added here stay in the browser. Phase 3: POST/DELETE /owner/boats/{ulid}/blocks.
 */
class CalendarController extends Controller
{
    public function index(Request $request, DemoOwner $owner): View
    {
        $boats = $owner->boats()->where('status', 'approved')->values();
        $wanted = $request->query('boat');
        $boat = (is_string($wanted) ? $boats->firstWhere('ulid', $wanted) : null) ?? $boats->first();

        $calendar = $boat ? [
            'boat' => $boat->ulid,
            'today' => $owner->now()->toDateString(),
            'bookings' => $owner->bookings()
                ->filter(fn (Fluent $b) => $b->boat->ulid === $boat->ulid && $b->status !== 'cancelled')
                ->map(fn (Fluent $b) => [
                    'date' => $b->starts_at->toDateString(),
                    'start' => $b->starts_at->format('H:i'),
                    'end' => $b->ends_at->format('H:i'),
                    'ref' => $b->ref,
                    'experience' => $b->experience,
                    'guests' => $b->guests,
                    'customer' => $b->customer,
                    'captain' => $b->captain,
                ])->values(),
            'blocks' => $owner->blocks()
                ->filter(fn (Fluent $block) => $block->boat === $boat->index)
                ->flatMap(function (Fluent $block) {
                    $days = [];
                    for ($d = $block->from_date; $d->lte($block->to_date); $d = $d->addDay()) {
                        $days[] = ['date' => $d->toDateString(), 'reason' => $block->reason];
                    }

                    return $days;
                })->values(),
            'bookingsUrl' => route('owner.bookings.index'),
        ] : null;

        return view('owner.calendar', [
            'boats' => $boats,
            'boat' => $boat,
            'calendar' => $calendar,
        ]);
    }
}
