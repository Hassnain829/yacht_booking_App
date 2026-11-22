<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * `admin.idle`: the admin is logged out after 30 minutes without using the admin area (PLAN §8.1).
 * The limit is config('session.admin_idle_minutes').
 */
class AdminIdleTimeout
{
    public const SESSION_KEY = 'admin.last_activity';

    public const MESSAGE = 'For your security, you were logged out after 30 minutes without activity. Please log in again.';

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $last = $session->get(self::SESSION_KEY);
        $limit = (int) config('session.admin_idle_minutes', 30) * 60;

        if (is_int($last) && time() - $last > $limit) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => self::MESSAGE], 401)
                : redirect()->route('login')->with('status', self::MESSAGE);
        }

        $session->put(self::SESSION_KEY, time());

        return $next($request);
    }
}
