<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The listing review queue and one listing's review page (demo data until Phase 6). Boats: Admin\BoatController. */
class FleetController extends Controller
{
    public function listings(Request $request, DemoAdmin $admin): View
    {
        $status = $request->query('status');
        $status = is_string($status) && in_array($status, ['pending', 'draft', 'approved'], true) ? $status : 'pending';

        return view('admin.fleet.listings', [
            'status' => $status,
            'listings' => $admin->listings()->where('status', $status)->values(),
            'counts' => $admin->listings()->countBy('status')->all(),
        ]);
    }

    public function listing(string $ulid, DemoAdmin $admin): View
    {
        $listing = $admin->listings()->firstWhere('ulid', $ulid);
        abort_unless($listing, 404);

        return view('admin.fleet.listing', [
            'listing' => $listing,
            'commissionBp' => config('demo_owner.money.commission_bp'),
            'policy' => collect(config('listing.cancellation'))->firstWhere('id', $listing->cancellation),
        ]);
    }
}
