<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoOwner;
use Illuminate\View\View;

/** Owner dashboard (mockup owner-dashboard.html). Phase 2: auth + owner role. Phase 7: real ledger. */
class DashboardController extends Controller
{
    public function __invoke(DemoOwner $owner): View
    {
        $boats = $owner->boats();
        $bookings = $owner->bookings();

        return view('owner.dashboard', [
            'profile' => $owner->profile(),
            'boats' => $boats,
            'drafts' => $boats->where('status', 'draft')->values(),
            'pending' => $boats->where('status', 'pending')->values(),
            'openComplaints' => $owner->complaints()->where('is_open', true)->values(),
            'upcomingCount' => $bookings->where('is_upcoming', true)->count(),
            'completedCount' => $bookings->where('status', 'completed')->count(),
            'balances' => $owner->balances(),
        ]);
    }
}
