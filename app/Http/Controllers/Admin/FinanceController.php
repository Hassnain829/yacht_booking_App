<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoAdmin;
use Illuminate\View\View;

/**
 * Withdrawals (approve / reject / hold, pause owner payouts), payments and complaints.
 * Phase 7: PayoutService / ComplaintService; every action needs password.confirm + reason + audit entry.
 */
class FinanceController extends Controller
{
    public function payouts(DemoAdmin $admin): View
    {
        return view('admin.finance.payouts', [
            'withdrawals' => $admin->withdrawals(),
            'money' => config('demo_owner.money'),
        ]);
    }

    public function payments(DemoAdmin $admin): View
    {
        return view('admin.finance.payments', ['payments' => $admin->payments()]);
    }

    public function complaints(DemoAdmin $admin): View
    {
        return view('admin.finance.complaints', [
            'complaints' => $admin->complaints(),
            'money' => config('demo_owner.money'),
        ]);
    }
}
