<?php

use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Home;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Set by JS when a visitor closes the promo strip. It only holds the promo id
        // (compared exactly in the header), so it doesn't need to be encrypted.
        $middleware->encryptCookies(except: ['promo_hidden']);

        // Signed-in users who open /login or /register go to their role's home (PLAN §4.2).
        $middleware->redirectUsersTo(fn (Request $request) => Home::for($request->user()));

        // Security headers + CSP on every response, error pages included (PLAN §8.3).
        $middleware->append(SecurityHeaders::class);

        // Production: HTTP → HTTPS. After TrustProxies (config/trustedproxy.php), inside SecurityHeaders
        // so the redirect carries the headers too.
        $middleware->append(ForceHttps::class);

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'admin.idle' => AdminIdleTimeout::class,
            '2fa' => EnsureTwoFactorEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
