<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoCustomer;
use Illuminate\View\View;

/**
 * Profile & security: details, password, two-factor, browser sessions, saved cards.
 * Phase 2: Fortify (update profile / password, 2FA), database sessions; Phase 5: Stripe saved cards.
 */
class ProfileController extends Controller
{
    public function show(DemoCustomer $customer): View
    {
        return view('account.profile', [
            'profile' => $customer->profile(),
            'cards' => $customer->cards(),
            'sessions' => $customer->sessions(),
        ]);
    }
}
