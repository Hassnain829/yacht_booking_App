<?php

namespace App\Providers;

use App\Listeners\SecurityEventSubscriber;
use App\Models\Addon;
use App\Models\Amenity;
use App\Models\Boat;
use App\Models\CancellationPolicy;
use App\Models\Captain;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\Setting;
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

        // Polymorphic columns (audit_logs.subject_type, media.model_type) store these short names, never class names.
        Relation::enforceMorphMap([
            'user' => User::class,
            'boat' => Boat::class,
            'harbor' => Harbor::class,
            'experience' => Experience::class,
            'addon' => Addon::class,
            'amenity' => Amenity::class,
            'cancellation_policy' => CancellationPolicy::class,
            'captain' => Captain::class,
            'setting' => Setting::class,
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
     * Named rate limiters — every form / JSON endpoint uses one (PLAN §8.2 rule 11; a test checks every
     * non-GET route has a throttle). Signed-in visitors are counted per account, guests per IP.
     * The auth limiters (login, two-factor, auth-forms) live in FortifyServiceProvider.
     */
    private function configureRateLimiting(): void
    {
        $perVisitor = fn (Request $request): string => $request->user() ? 'user:'.$request->user()->getKey() : 'ip:'.$request->ip();

        // Booking (Phases 3–5): live price / availability checks, holding a slot, checkout, promo codes.
        RateLimiter::for('quote', fn (Request $request) => Limit::perMinute(60)->by($perVisitor($request)));
        RateLimiter::for('hold', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request)));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request)));
        RateLimiter::for('promo', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request))); // stops code guessing

        // Files: uploads (listing photos, documents, complaint photos — Phases 3.4, 6, 7) and opening a private file.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($perVisitor($request)));
        RateLimiter::for('files', fn (Request $request) => Limit::perMinute(60)->by($perVisitor($request)));

        // Public forms (Phase 8): always per IP — these are the ones bots aim at.
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by('ip:'.$request->ip()));
        RateLimiter::for('newsletter', fn (Request $request) => Limit::perHour(5)->by('ip:'.$request->ip()));

        // Security settings of a signed-in account (log out other devices, delete account).
        RateLimiter::for('account-security', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request)));

        // Admin screens (3.4): saving forms, and the sensitive actions that take a reason + the password
        // (Trash / restore / delete permanently, settings) — a low limit also stops password guessing there.
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(60)->by($perVisitor($request)));
        RateLimiter::for('admin-sensitive', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request)));
    }
}
