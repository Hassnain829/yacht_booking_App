<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoAdmin;
use App\Support\Demo\DemoCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/** Catalog (experiences, add-ons, promo codes), settings and the audit log. Phase 3/10: Eloquent + SettingsService. */
class SystemController extends Controller
{
    public function catalog(DemoCatalog $catalog): View
    {
        return view('admin.system.catalog', [
            'experiences' => $catalog->experiences(),
            'addons' => $catalog->addons(),
            'promos' => $catalog->pricing()['promos'],
            'harbors' => $catalog->harbors(),
        ]);
    }

    public function settings(Request $request, DemoCatalog $catalog): View
    {
        return view('admin.system.settings', [
            'twoFactorOn' => $request->user()->hasEnabledTwoFactorAuthentication(), // real (Phase 2.4); the rest is demo until Phase 3
            'pricing' => $catalog->pricing(),
            'money' => config('demo_owner.money'),
            'business' => config('site.business'),
        ]);
    }

    public function audit(DemoAdmin $admin): View
    {
        return view('admin.system.audit', [
            'entries' => $admin->audit()->sortByDesc(fn (Fluent $a) => $a->at->getTimestamp())->values(),
        ]);
    }
}
