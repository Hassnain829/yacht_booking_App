<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoatStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\Captain;
use App\Models\Experience;
use App\Support\Demo\DemoAdmin;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/**
 * Admin home (PLAN §12). Real since 3.4.7: the fleet and catalog numbers and the recent activity (audit log).
 * Trips, revenue and withdrawals stay demo data until the booking engine and finance (Phases 4–7).
 */
class DashboardController extends Controller
{
    public function __invoke(DemoAdmin $admin): View
    {
        return view('admin.dashboard', [
            'stats' => $admin->stats(),
            'fleet' => [
                'live' => Boat::query()->where('status', BoatStatus::Published)->count(),
                'unavailable' => Boat::query()->where('status', BoatStatus::Published)->where('booking_enabled', false)->count(),
                'hidden' => Boat::query()->whereNotIn('status', [BoatStatus::Published, BoatStatus::PendingReview, BoatStatus::ChangesRequested])->count(),
                'review' => Boat::query()->whereIn('status', [BoatStatus::PendingReview, BoatStatus::ChangesRequested])->count(),
                'experiences' => Experience::query()->where('is_active', true)->count(),
                'captains' => Captain::query()->where('is_active', true)->count(),
                'licenses' => Captain::query()->where('is_active', true)->whereNotNull('license_expires_on')->whereDate('license_expires_on', '<=', today()->addDays(30))->count(),
            ],
            'upcoming' => $admin->bookings()->where('is_upcoming', true)->sortBy(fn (Fluent $b) => $b->starts_at->getTimestamp())->take(6)->values(),
            'activity' => AuditLog::query()->with('actor')->latest('id')->limit(6)->get(),
            'withdrawals' => $admin->withdrawals(),
            'now' => $admin->now(),
        ]);
    }
}
