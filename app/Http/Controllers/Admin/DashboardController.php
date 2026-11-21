<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoAdmin;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/** Admin home: numbers, action list, next trips, recent activity (PLAN §12). */
class DashboardController extends Controller
{
    public function __invoke(DemoAdmin $admin): View
    {
        return view('admin.dashboard', [
            'stats' => $admin->stats(),
            'upcoming' => $admin->bookings()->where('is_upcoming', true)->sortBy(fn (Fluent $b) => $b->starts_at->getTimestamp())->take(6)->values(),
            'audit' => $admin->audit()->sortByDesc(fn (Fluent $a) => $a->at->getTimestamp())->take(5)->values(),
            'withdrawals' => $admin->withdrawals(),
            'now' => $admin->now(),
        ]);
    }
}
