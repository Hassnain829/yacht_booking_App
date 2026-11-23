<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoCatalog;
use Illuminate\View\View;

/** Promo codes (demo data until 4.7). Settings and the audit log have their own controllers (3.4.5, 3.4.7). */
class SystemController extends Controller
{
    /** Promo codes: demo data until the booking engine (Phase 4.7). */
    public function promos(DemoCatalog $catalog): View
    {
        return view('admin.system.promos', ['promos' => $catalog->pricing()['promos']]);
    }
}
