<?php

namespace App\Providers;

use App\Listeners\SecurityEventSubscriber;
use App\Models\User;
use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configurePasswords();

        // Production links, redirects and signed URLs are always https:// (PLAN §8.3; HTTP requests are redirected by ForceHttps).
        URL::forceHttps($this->app->environment('production'));

        // Logins, failures, 2FA and password events → login_events + security emails (PLAN §8.1, §8.5).
        Event::subscribe(SecurityEventSubscriber::class);

        // Polymorphic columns (audit_logs.subject_type) store these short names, never class names.
        Relation::enforceMorphMap([
            'user' => User::class,
        ]);
    }

    /**
     * Password rules (PLAN §8.1): 12+ characters, upper + lower case, a number, and not in a known data breach
     * (Have I Been Pwned, k-anonymity — only 5 characters of the hash leave the server). The breach check is
     * skipped in automated tests so they never call the internet.
     */
    private function configurePasswords(): void
    {
        Password::defaults(function () {
            $rule = Password::min(12)->max(128)->mixedCase()->numbers();

            return $this->app->runningUnitTests() ? $rule : $rule->uncompromised();
        });
    }

    /**
     * Named rate limiters — every form / JSON endpoint uses one (PLAN §8.2).
     * Phase 2.6 adds the rest (login, contact, holds, payments); keys move to the user id once logged in.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('promo', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        // Security settings of a signed-in account (log out other devices, …).
        RateLimiter::for('account-security', fn (Request $request) => Limit::perMinute(10)->by('user:'.$request->user()?->getKey()));
    }
}
