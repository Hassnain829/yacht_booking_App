<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoOwner;
use Illuminate\View\View;

/** Earnings statement (PLAN §7.6). Phase 7: ledger_entries via LedgerService, CSV export. */
class EarningsController extends Controller
{
    public function index(DemoOwner $owner): View
    {
        return view('owner.earnings', [
            'balances' => $owner->balances(),
            'ledger' => $owner->ledger(),
            'money' => $owner->money(),
        ]);
    }
}
