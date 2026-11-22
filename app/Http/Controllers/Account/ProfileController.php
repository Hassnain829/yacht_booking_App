<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Services\SessionService;
use App\Services\UserAccountService;
use App\Support\Demo\DemoCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Profile & security: details, password, two-factor, devices, saved cards, delete account.
 * Real since Phase 2.5: details + email change and password (Fortify forms), delete account;
 * two-factor and devices since 2.4. Demo until Phase 5: saved cards (Stripe).
 */
class ProfileController extends Controller
{
    public function show(Request $request, DemoCustomer $customer, SessionService $sessions, UserAccountService $accounts): View
    {
        $user = $request->user();

        return view('account.profile', [
            'user' => $user,
            'twoFactorOn' => $user->hasEnabledTwoFactorAuthentication(),
            'isAdmin' => $user->isAdmin(),
            'deletionBlockers' => $accounts->deletionBlockers($user),
            'cards' => $customer->cards(),
            'sessions' => $sessions->devicesFor($user, $request),
        ]);
    }

    public function destroy(DeleteAccountRequest $request, UserAccountService $accounts): RedirectResponse
    {
        $user = $request->user();
        $blockers = $accounts->deletionBlockers($user);
        if ($blockers !== []) {
            return redirect()->to(route('account.profile').'#delete')->withErrors(['password' => $blockers[0]], 'deleteAccount');
        }

        $accounts->deleteAccount($user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your account has been deleted. A confirmation is on its way to your email.');
    }
}
