<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\EnsureIdempotency;
use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 2.6 — the JSON endpoint foundation (PLAN §8.2): one error format, named rate limiters,
 * the API Resource base and the idempotency-key middleware.
 */
class JsonFoundationTest extends TestCase
{
    use RefreshDatabase;

    private static int $payments = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::$payments = 0;

        // Stand-ins for later endpoints (a payment in Phase 5, a record lookup, a crash).
        Route::middleware(['web', 'idempotent'])->post('/_test/pay', function (Request $request) {
            if ($request->boolean('decline')) {
                return response()->json(['message' => 'Card declined.'], 422);
            }

            return response()->json(['payment' => ++self::$payments, 'amount' => $request->integer('amount')], 201);
        });
        Route::middleware('web')->get('/_test/users/{user}', fn (User $user) => $user->ulid);
        Route::middleware('web')->get('/_test/crash', fn () => throw new RuntimeException('database password is hunter2'));
    }

    /* ------------------------------------------------------------------ error format */

    public function test_json_errors_have_one_shape_and_leak_nothing(): void
    {
        $this->getJson('/no-such-page')->assertStatus(404)->assertExactJson(['message' => 'Not found.']);

        $missing = $this->getJson('/_test/users/01JDQ7M3K8R2V5X9C4B6N0P1TZ')->assertStatus(404)->assertExactJson(['message' => 'Not found.']);
        $this->assertStringNotContainsString('App\\\\Models', $missing->getContent(), 'no model names');

        $this->getJson('/checkout/promo')->assertStatus(405)->assertExactJson(['message' => 'Method not allowed.']); // no list of routes / methods

        config(['app.debug' => false]);
        $crash = $this->getJson('/_test/crash')->assertStatus(500);
        $this->assertStringNotContainsString('hunter2', $crash->getContent());
        $this->assertArrayNotHasKey('trace', $crash->json());

        // Validation keeps Laravel's field errors; HTML requests still get our error pages.
        $this->postJson('/checkout/promo', [])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->get('/no-such-page')->assertNotFound()->assertSee('find that page');
    }

    public function test_a_json_429_keeps_its_retry_after_header(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/checkout/promo', ['code' => 'GUESS'.$i]);
        }

        $this->postJson('/checkout/promo', ['code' => 'WELCOME10'])
            ->assertStatus(429)->assertExactJson(['message' => 'Too many requests.'])->assertHeader('Retry-After');
    }

    /* ------------------------------------------------------------------ rate limiters */

    public function test_every_named_limiter_exists_and_counts_accounts_separately_from_guests(): void
    {
        foreach (['quote', 'hold', 'checkout', 'promo', 'uploads', 'contact', 'newsletter', 'account-security', 'login', 'two-factor', 'auth-forms'] as $name) {
            $this->assertNotNull(RateLimiter::limiter($name), "limiter {$name}");
        }

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/checkout/promo', ['code' => 'GUESS'.$i]);
        }
        $this->postJson('/checkout/promo', ['code' => 'WELCOME10'])->assertStatus(429); // the guest (this IP) is out
        $this->actingAs(User::factory()->create())->postJson('/checkout/promo', ['code' => 'WELCOME10'])->assertOk(); // an account has its own count
    }

    public function test_every_route_that_changes_data_has_a_rate_limit(): void
    {
        $unthrottled = collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RoutingRoute $route) => array_diff($route->methods(), ['GET', 'HEAD']) === [])
            ->reject(fn (RoutingRoute $route) => str_starts_with($route->uri(), '_')) // local dev tools (Laravel Boost) and these tests' stand-ins
            ->reject(fn (RoutingRoute $route) => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')))
            ->map(fn (RoutingRoute $route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()->all();

        $this->assertSame([], $unthrottled, 'PLAN §8.2 rule 11: every form / JSON endpoint has a named limiter');
    }

    /* ------------------------------------------------------------------ API Resource base */

    public function test_resources_list_their_fields_and_use_the_ulid_and_utc_dates(): void
    {
        $user = User::factory()->create(['created_at' => '2026-11-22 15:00:00']);
        $lazy = new class($user) extends ApiResource {}; // forgot to list its fields
        try {
            $lazy->toArray(request());
            $this->fail('A resource without its own field list must not return the model.');
        } catch (LogicException) {
            // expected: no accidental "every column" response
        }

        $resource = new class($user) extends ApiResource
        {
            public function toArray(Request $request): array
            {
                return ['id' => $this->publicId(), 'joined_at' => self::iso($this->resource->created_at)];
            }
        };

        $this->assertSame(['data' => ['id' => $user->ulid, 'joined_at' => '2026-11-22T15:00:00Z']], $resource->response()->getData(true));
        $this->postJson('/checkout/promo', ['code' => 'welcome10'])->assertOk()->assertJsonPath('data.code', 'WELCOME10');
    }

    /* ------------------------------------------------------------------ idempotency */

    public function test_a_request_without_a_valid_key_is_refused(): void
    {
        $this->postJson('/_test/pay', ['amount' => 100])->assertStatus(400)->assertJsonPath('message', fn ($m) => str_contains($m, 'idempotency key'));
        $this->postJson('/_test/pay', ['amount' => 100], ['Idempotency-Key' => 'short'])->assertStatus(400);
        $this->from('/checkout')->post('/_test/pay', ['amount' => 100])->assertRedirect('/checkout')->assertSessionHasErrors(EnsureIdempotency::INPUT);
        $this->assertSame(0, self::$payments);
    }

    public function test_the_same_key_never_runs_twice_and_replays_the_answer(): void
    {
        $key = ['Idempotency-Key' => 'f1d2c3b4-0000-4000-8000-000000000001'];
        // A guest's keys belong to their session: keep one session cookie, like a browser does
        // (http.js sends it with credentials: 'same-origin').
        $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('g', 40));

        $first = $this->postJson('/_test/pay', ['amount' => 100], $key)->assertStatus(201)->assertExactJson(['payment' => 1, 'amount' => 100]);
        $first->assertHeaderMissing('Idempotent-Replayed');
        $this->postJson('/_test/pay', ['amount' => 100], $key)->assertStatus(201)->assertExactJson(['payment' => 1, 'amount' => 100])->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, self::$payments, 'a double-click pays once');

        $this->postJson('/_test/pay', ['amount' => 999], $key)->assertStatus(422); // same key, different request
        $this->postJson('/_test/pay', ['amount' => 100], ['Idempotency-Key' => 'f1d2c3b4-0000-4000-8000-000000000002'])->assertJsonPath('payment', 2);

        // Keys belong to one account (or guest session): someone else's identical key is a new action.
        $this->actingAs(User::factory()->create())->postJson('/_test/pay', ['amount' => 100], $key)->assertJsonPath('payment', 3);

        // A plain form can send the key as a hidden field.
        $this->post('/_test/pay', ['amount' => 5, EnsureIdempotency::INPUT => 'f1d2c3b4-0000-4000-8000-000000000003'])->assertStatus(201);
        $this->post('/_test/pay', ['amount' => 5, EnsureIdempotency::INPUT => 'f1d2c3b4-0000-4000-8000-000000000003'])->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(4, self::$payments);
    }

    public function test_a_failure_frees_the_key_and_a_running_request_blocks_its_twin(): void
    {
        $user = User::factory()->create();
        $key = 'f1d2c3b4-0000-4000-8000-000000000009';
        $this->actingAs($user);

        $this->postJson('/_test/pay', ['amount' => 100, 'decline' => true], ['Idempotency-Key' => $key])->assertStatus(422);
        $this->postJson('/_test/pay', ['amount' => 100], ['Idempotency-Key' => $key])->assertStatus(201); // retried with the same key after fixing it

        // While the first request is still running, its twin gets 409 (nothing runs twice in parallel).
        $running = 'f1d2c3b4-0000-4000-8000-000000000010';
        $request = Request::create('/_test/pay', 'POST');
        $request->setUserResolver(fn () => $user);
        Cache::put(EnsureIdempotency::cacheKey($request, $running), ['state' => 'running', 'fingerprint' => 'x'], now()->addMinute());
        $this->postJson('/_test/pay', ['amount' => 100], ['Idempotency-Key' => $running])->assertStatus(409);
        $this->assertSame(1, self::$payments);
    }
}
