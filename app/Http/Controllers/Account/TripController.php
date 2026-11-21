<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoCustomer;
use App\Support\Demo\DemoOwner;
use Illuminate\View\View;

/**
 * "My trips" + trip detail (cancel with a refund preview, receipt, calendar, complaint form, captain card).
 * Phase 4: $request->user()->bookings() + BookingPolicy — someone else's trip is a 404, never a 403.
 */
class TripController extends Controller
{
    public function index(DemoCustomer $customer): View
    {
        $trips = $customer->trips();

        return view('account.trips', [
            'upcoming' => $trips->where('is_upcoming', true)->values(),
            'past' => $trips->where('is_upcoming', false)->values(),
        ]);
    }

    public function show(string $ulid, DemoCustomer $customer): View
    {
        $trip = $customer->trip($ulid);
        abort_unless($trip, 404);

        return view('account.trip', [
            'trip' => $trip,
            'policy' => collect(config('listing.cancellation'))->firstWhere('id', $trip->policy),
            'categories' => DemoOwner::COMPLAINT_CATEGORIES,
            'business' => config('site.business'),
        ]);
    }
}
