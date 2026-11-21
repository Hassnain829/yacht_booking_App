<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoOwner;
use Illuminate\View\View;

/**
 * Withdrawals + payout method (PLAN §7.6, D9).
 * Phase 7: POST /owner/payouts (RequestWithdrawal: available ≥ min, Stripe Connect, 2FA, password confirm,
 * payouts not paused, idempotency key) and the Stripe Connect onboarding link.
 */
class PayoutController extends Controller
{
    public function index(DemoOwner $owner): View
    {
        $profile = $owner->profile();
        $balances = $owner->balances();
        $money = $owner->money();

        return view('owner.payouts', [
            'profile' => $profile,
            'balances' => $balances,
            'payouts' => $owner->payouts(),
            'money' => $money,
            'canWithdraw' => $profile->payout_account && $profile->two_factor && ! $profile->payouts_paused && $balances['available'] >= $money['min_withdrawal_cents'],
        ]);
    }
}
