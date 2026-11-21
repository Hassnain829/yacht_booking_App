<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/** Bookings on the owner's boats. Phase 4: BookingPolicy (own boats only). */
class BookingController extends Controller
{
    /** Allowed ?tab= values (whitelist). */
    public const TABS = ['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'];

    public function index(Request $request, DemoOwner $owner): View
    {
        $tab = $request->query('tab');
        $tab = is_string($tab) && array_key_exists($tab, self::TABS) ? $tab : 'upcoming';
        $bookings = $owner->bookings();

        $groups = [
            'upcoming' => $bookings->where('is_upcoming', true)->sortBy(fn (Fluent $b) => $b->starts_at->getTimestamp())->values(),
            'past' => $bookings->where('status', 'completed')->values(),
            'cancelled' => $bookings->where('status', 'cancelled')->values(),
        ];

        return view('owner.bookings', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'groups' => $groups,
            'list' => $groups[$tab],
            'balances' => $owner->balances(),
        ]);
    }
}
