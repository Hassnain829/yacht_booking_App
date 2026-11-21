<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoOwner;
use Illuminate\View\View;

/** Guest complaints about the owner's trips (PLAN §7.6). Phase 7: ComplaintPolicy + PATCH owner_response. */
class ComplaintController extends Controller
{
    public function index(DemoOwner $owner): View
    {
        return view('owner.complaints', [
            'complaints' => $owner->complaints(),
            'money' => $owner->money(),
        ]);
    }
}
