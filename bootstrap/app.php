<?php

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

        // Signed-in users who open /login or /register. Phase 2.5: by role (admin → /admin, owner → /owner).
        $middleware->redirectUsersTo('/account/trips');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
