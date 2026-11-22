<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `2fa` (PLAN §4.5): the area needs two-factor login turned on — owner money actions (Phase 7).
 * (Not the admin area: there it is the admin's choice, user decision 2026-11-22.)
 * Without it the user is sent to set it up; JSON requests get 403.
 */
class EnsureTwoFactorEnabled
{
    public const MESSAGE = 'Turn on two-factor login to continue. It is required for this area.';

    /** Kept in the session (not flashed): it must survive the password confirmation before the 2FA page. */
    public const SESSION_KEY = 'two_factor.required';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasEnabledTwoFactorAuthentication()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => self::MESSAGE], 403);
            }
            $request->session()->put(self::SESSION_KEY, true);

            return redirect()->route('account.two-factor');
        }

        return $next($request);
    }
}
