<?php

namespace App\Providers;

use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.footer', FooterComposer::class);
        View::composer(['partials.header', 'partials.mobile-menu'], HeaderComposer::class);

        $this->configureRateLimiting();
    }

    /**
     * Named rate limiters — every form / JSON endpoint uses one (PLAN §8.2).
     * Phase 2.6 adds the rest (login, contact, holds, payments); keys move to the user id once logged in.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('promo', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
