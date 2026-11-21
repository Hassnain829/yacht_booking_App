<?php

namespace App\Providers;

use App\Models\User;
use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        // Remember the last successful login (shown to the admin). Phase 2.4 adds the full login_events log.
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                DB::table('users')->where('id', $event->user->getKey())->update([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ]);
            }
        });
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
    }
}
