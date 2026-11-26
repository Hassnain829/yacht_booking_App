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
use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use App\Services\PublicCatalog;
use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One catalog per request: the controller, the footer and the booking pieces share its queries (PLAN 3.5).
        $this->app->scoped(PublicCatalog::class);

        // Card payments (PLAN §8.4, 4.5): Stripe with its keys; the fake gateway (a test card form, no money) only
        // in local development and tests — never in production.
        $this->app->singleton(PaymentGateway::class, function (): PaymentGateway {
            if (config('payments.driver') === 'stripe') {
                return new StripeGateway(
                    new StripeClient((string) config('payments.stripe.secret')),
                    config('payments.stripe.key'),
                    config('payments.stripe.webhook_secret'),
                    (string) config('payments.currency', 'usd'),
                );
            }
            if ($this->app->isProduction()) {
                throw new RuntimeException('Payments: set the Stripe keys. The fake gateway is for local development and tests only.');
            }

            return new FakeGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // …and forgotten after every request, so a long-lived process (tests, workers) never serves stale data.
        Event::listen(RequestHandled::class, fn () => $this->app->forgetInstance(PublicCatalog::class));

        // The cached public catalog (3.6) is cleared after any catalog change commits. Restoring saves, and a
        // permanent delete fires `deleted`, so these two events cover the Trash too. Changes without model events
        // (pivot syncs, photo order) call PublicCatalog::flushAfterCommit() themselves.
        foreach ([Boat::class, Harbor::class, Experience::class, Addon::class, Amenity::class, Media::class, CancellationPolicy::class] as $model) {
            $model::saved(fn () => PublicCatalog::flushAfterCommit());
            $model::deleted(fn () => PublicCatalog::flushAfterCommit());
        }

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
        // The boat page's booking card (4.3) asks for a month, a day's start times and a price as the guest picks.
        RateLimiter::for('availability', fn (Request $request) => Limit::perMinute(120)->by($perVisitor($request)));
        RateLimiter::for('quote', fn (Request $request) => Limit::perMinute(60)->by($perVisitor($request)));
        RateLimiter::for('hold', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request)));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request)));
        RateLimiter::for('promo', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request))); // stops code guessing
        RateLimiter::for('payment', fn (Request $request) => Limit::perMinute(10)->by($perVisitor($request)));
        RateLimiter::for('payment-status', fn (Request $request) => Limit::perMinute(60)->by($perVisitor($request)));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by('ip:'.$request->ip()));

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
