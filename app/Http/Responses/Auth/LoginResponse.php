<?php

namespace App\Http\Responses\Auth;

use App\Support\Home;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * After the password (or the 2FA code): back to the page they wanted, otherwise their role's home
 * (PLAN §4.2: admin → /admin, owner → /owner, everyone else → My trips).
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    /** @param  Request  $request */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended(Home::for($request->user()));
    }
}
