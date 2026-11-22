<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\LogOutOtherDevicesRequest;
use App\Services\SessionService;
use Illuminate\Http\RedirectResponse;

/** Devices on Profile & security (PLAN §8.1): "Log out other devices" (needs a recent password confirmation). */
class SessionController extends Controller
{
    public function destroyOthers(LogOutOtherDevicesRequest $request, SessionService $sessions): RedirectResponse
    {
        $ended = $sessions->logOutOtherDevices($request->user(), $request);

        $message = match ($ended) {
            0 => 'No other device had an open session. "Remember me" logins on other browsers have been ended too.',
            1 => 'Logged out of 1 other device.',
            default => "Logged out of {$ended} other devices.",
        };

        return redirect()->to(route('account.profile').'#sessions')->with('sessions_status', $message);
    }
}
