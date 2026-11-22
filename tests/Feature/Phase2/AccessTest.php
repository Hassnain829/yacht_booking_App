<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 2.3 — route protection (PLAN §4.4–4.5), admin idle timeout, security headers + CSP, HTTPS + trusted proxies (§8.3).
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    private const URLS = [
        'account' => '/account/trips',
        'profile' => '/account',
        'owner' => '/owner',
        'owner money' => '/owner/payouts',
        'list boat' => '/list-your-boat?new=1',
        'admin' => '/admin',
        'admin finance' => '/admin/payouts',
    ];

    private function userFor(string $who): ?User
    {
        return match ($who) {
            'guest' => null,
            'customer' => User::factory()->create(),
            'owner' => User::factory()->owner()->create(),
            'admin' => User::factory()->admin()->withTwoFactor()->create(),
            'admin without 2FA' => User::factory()->admin()->create(),
            'unverified owner' => User::factory()->owner()->unverified()->create(),
        };
    }

    /**
     * Expected result per area: 200, 403, 'login' (redirect to log in), 'verify' (redirect to verify email)
     * or '2fa' (redirect to turn on two-factor login).
     *
     * @return array<string, array{0: string, 1: string, 2: int|string}>
     */
    public static function matrix(): array
    {
        $expected = [
            //                  account profile owner  money  list   admin  finance
            'guest' => ['login', 'login', 'login', 'login', 'login', 'login', 'login'],
            'customer' => [200, 200, 403, 403, 200, 403, 403],
            'owner' => [200, 200, 200, 200, 200, 403, 403],
            'admin' => [200, 200, 403, 403, 403, 200, 200],
            'admin without 2FA' => [200, 200, 403, 403, 403, 200, 200], // 2FA is the admin's choice (user decision 2026-11-22)
            'unverified owner' => [200, 200, 'verify', 'verify', 'verify', 'verify', 'verify'], // verification is checked before the role
        ];
        $cases = [];
        foreach ($expected as $who => $results) {
            foreach (array_keys(self::URLS) as $i => $area) {
                $cases["{$who} → {$area}"] = [$who, $area, $results[$i]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_access_matrix(string $who, string $area, int|string $expected): void
    {
        if ($user = $this->userFor($who)) {
            $this->actingAs($user);
        }

        $response = $this->get(self::URLS[$area]);

        match ($expected) {
            'login' => $response->assertRedirect(route('login')),
            'verify' => $response->assertRedirect(route('verification.notice')),
            '2fa' => $response->assertRedirect(route('account.two-factor')),
            default => $response->assertStatus($expected),
        };
    }

    public function test_after_logging_in_the_guest_returns_to_the_page_they_wanted(): void
    {
        $user = User::factory()->owner()->create(['password' => 'Harbor-Sunset-2026']);

        $this->get('/owner/earnings')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'Harbor-Sunset-2026'])->assertRedirect('/owner/earnings');
    }

    public function test_a_suspended_account_is_logged_out_on_its_next_request(): void
    {
        $user = User::factory()->owner()->suspended()->create();

        $this->actingAs($user)->get('/owner')->assertRedirect(route('login'))->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE]);
        $this->assertGuest();

        $this->actingAs($user)->getJson('/account/trips')->assertStatus(403)->assertJson(['message' => EnsureAccountIsActive::MESSAGE]);
    }

    /* ------------------------------------------------------------------ admin idle + remember me */

    public function test_the_admin_is_logged_out_after_30_idle_minutes(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();

        $this->actingAs($admin)->withSession([AdminIdleTimeout::SESSION_KEY => time() - 29 * 60])->get('/admin')->assertOk();
        $this->assertGreaterThan(time() - 5, session(AdminIdleTimeout::SESSION_KEY), 'activity refreshes the timer');

        $this->actingAs($admin)->withSession([AdminIdleTimeout::SESSION_KEY => time() - 31 * 60])->get('/admin/bookings')
            ->assertRedirect(route('login'))->assertSessionHas('status', AdminIdleTimeout::MESSAGE);
        $this->assertGuest();
    }

    public function test_remember_me_is_ignored_for_the_admin_only(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'Harbor-Sunset-2026']);
        $customer = User::factory()->create(['password' => 'Harbor-Sunset-2026']);
        /** @var SessionGuard $guard */
        $guard = auth()->guard('web');
        $recaller = $guard->getRecallerName();

        $this->post('/login', ['email' => $admin->email, 'password' => 'Harbor-Sunset-2026', 'remember' => '1'])->assertCookieMissing($recaller);
        $this->post('/logout');
        $this->post('/login', ['email' => $customer->email, 'password' => 'Harbor-Sunset-2026', 'remember' => '1'])->assertCookie($recaller);
    }

    /* ------------------------------------------------------------------ headers + CSP */

    public function test_every_response_has_the_security_headers(): void
    {
        foreach (['/', '/login', '/no-such-page'] as $url) {
            $response = $this->get($url);

            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
                ->assertHeaderMissing('X-Powered-By')
                ->assertHeaderMissing('Strict-Transport-Security'); // only in production over HTTPS
            $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
            $this->assertNotEmpty($response->headers->get('Content-Security-Policy'), $url);
        }
    }

    public function test_the_csp_only_allows_our_own_nonced_code(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9]{40})'/", $csp);
        preg_match("/'nonce-([A-Za-z0-9]{40})'/", $csp, $m);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        foreach (["object-src 'none'", "base-uri 'self'", "form-action 'self'", "frame-ancestors 'none'", 'frame-src https://www.google.com'] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }

        // Every <script>/<style> tag Vite renders carries this request's nonce.
        $html = $response->getContent();
        preg_match_all('/<(script|style)\b[^>]*>/', $html, $tags);
        $this->assertNotEmpty($tags[0]);
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $tag, $tag);
        }

        $this->assertNotSame($m[1], $this->extractNonce($this->get('/')->headers->get('Content-Security-Policy')), 'a new nonce per request');
    }

    /* ------------------------------------------------------------------ HTTPS + proxies */

    public function test_production_sends_plain_http_to_https(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('http://localhost/yachts?guests=6')->assertStatus(301)->assertRedirect('https://localhost/yachts?guests=6')
            ->assertHeader('X-Frame-Options', 'DENY')->assertHeaderMissing('Strict-Transport-Security');
        $this->post('http://localhost/login')->assertStatus(308)->assertRedirect('https://localhost/login');
        $this->get('http://localhost//evil.example/x')->assertRedirect('https://localhost//evil.example/x'); // never leaves our host

        $this->get('https://localhost/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_forwarded_headers_only_count_from_a_trusted_proxy(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $user = User::factory()->create(['password' => 'Harbor-Sunset-2026']);
        $forwarded = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'];

        // Default, no proxy trusted: a visitor can't claim HTTPS or fake their IP (so the IP rate limits hold).
        // (Outside the "testing" env Laravel checks CSRF, so the form sends the session's token.)
        $this->get('http://localhost/login', $forwarded)->assertStatus(301);
        $this->withSession(['_token' => 'csrf-test-token'])
            ->post('https://localhost/login', ['_token' => 'csrf-test-token', 'email' => $user->email, 'password' => 'Harbor-Sunset-2026'], $forwarded)
            ->assertRedirect();
        $this->assertSame('127.0.0.1', $user->fresh()->last_login_ip);

        // Behind a trusted proxy that terminates SSL (e.g. Cloudflare): its headers count, so no redirect loop.
        config(['trustedproxy.proxies' => '127.0.0.1']);
        $this->get('http://localhost/', $forwarded)->assertOk()->assertHeader('Strict-Transport-Security');
    }

    public function test_while_npm_run_dev_runs_the_csp_allows_the_vite_dev_server(): void
    {
        $hot = storage_path('framework/testing/vite-hot-test');
        File::ensureDirectoryExists(dirname($hot));
        Vite::useHotFile($hot);

        try {
            File::put($hot, 'http://127.0.0.1:5173');
            $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
            foreach ([
                "style-src 'self' 'unsafe-inline' http://127.0.0.1:5173",
                "img-src 'self' data: blob: http://127.0.0.1:5173",
                "font-src 'self' data: http://127.0.0.1:5173", // the dev server serves the fonts too
                "connect-src 'self' http://127.0.0.1:5173 ws://127.0.0.1:5173",
            ] as $directive) {
                $this->assertStringContainsString($directive, $csp);
            }
            $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]{40}' http:\/\/127\.0\.0\.1:5173/", $csp);

            // A CSP can't name an IPv6 address (browsers drop it and the CSS is blocked): only the scheme is used.
            File::put($hot, 'http://[::1]:5173');
            $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
            $this->assertStringNotContainsString('[::1]', $csp);
            $this->assertStringContainsString("style-src 'self' 'unsafe-inline' http:;", $csp);
            $this->assertStringContainsString("font-src 'self' data: http:;", $csp);
            $this->assertStringContainsString("connect-src 'self' http: ws:;", $csp);
        } finally {
            File::delete($hot);
        }

        Vite::useHotFile(storage_path(TestCase::NO_HOT_FILE));
        $this->assertStringNotContainsString("'unsafe-inline'", $this->get('/')->headers->get('Content-Security-Policy'), 'a build never allows inline styles');
    }

    public function test_the_private_disk_is_never_served_by_url(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(Route::has('storage.local'));
    }

    private function extractNonce(string $csp): string
    {
        preg_match("/'nonce-([A-Za-z0-9]{40})'/", $csp, $m);

        return $m[1];
    }
}
