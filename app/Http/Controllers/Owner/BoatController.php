<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoOwner;
use Illuminate\View\View;

/** "My boats" (mockup my-boats.html). Phase 3: $request->user()->boats() with BoatPolicy. */
class BoatController extends Controller
{
    public function index(DemoOwner $owner): View
    {
        return view('owner.boats', [
            'boats' => $owner->boats(),
        ]);
    }
}
