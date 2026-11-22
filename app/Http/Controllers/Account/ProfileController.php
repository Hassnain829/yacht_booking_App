<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\SessionService;
use App\Support\Demo\DemoCustomer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profile & security: details, password, two-factor, devices, saved cards.
 * Real since Phase 2.4: two-factor status and devices (database sessions).
 * Demo until Phase 2.5: details and password (Fortify update forms); Phase 5: saved cards (Stripe).
 */
class ProfileController extends Controller
{
    public function show(Request $request, DemoCustomer $customer, SessionService $sessions): View
    {
        $user = $request->user();

        return view('account.profile', [
            'profile' => $customer->profile(),
            'twoFactorOn' => $user->hasEnabledTwoFactorAuthentication(),
            'isAdmin' => $user->isAdmin(),
            'cards' => $customer->cards(),
            'sessions' => $sessions->devicesFor($user, $request),
        ]);
    }
}
