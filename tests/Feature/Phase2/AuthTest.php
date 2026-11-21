<?php

namespace Tests\Feature\Phase2;

use App\Http\Responses\Auth\PasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 2.1 — Fortify: register, login, logout, email verification, password reset, password confirmation,
 * throttling, password rules and no account enumeration (PLAN §8.1).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Harbor-Sunset-2026';

    /** @param array<string, mixed> $overrides */
    private function signup(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'Jane.Doe@Example.com',
            'phone' => '(312) 555-0199',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms' => '1',
            'marketing' => '1',
        ], $overrides);
    }

    /* ------------------------------------------------------------------ register */

    public function test_registration_creates_a_customer_and_asks_to_verify_the_email(): void
    {
        Notification::fake();

        $this->post('/register', $this->signup([
            // none of these may be taken from the request
            'id' => 999, 'ulid' => 'hacked', 'email_verified_at' => now()->toDateTimeString(), 'role' => 'admin', 'remember_token' => 'x',
        ]))->assertRedirect(route('verification.notice'));

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('jane.doe@example.com', $user->email, 'emails are stored lower-case');
        $this->assertSame(['Jane', 'Doe', '(312) 555-0199'], [$user->first_name, $user->last_name, $user->phone]);
        $this->assertTrue($user->marketing_opt_in);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame(999, $user->id);
        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $user->ulid);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame(self::PASSWORD, $user->password);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('verification.notice'))->assertOk()->assertSee('jane.doe@example.com');
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidSignups(): array
    {
        return [
            'password too short' => [['password' => 'Short-1a', 'password_confirmation' => 'Short-1a'], 'password'],
            'password without upper case' => [['password' => 'harbor-sunset-2026', 'password_confirmation' => 'harbor-sunset-2026'], 'password'],
            'password without a number' => [['password' => 'Harbor-Sunset-Cruise', 'password_confirmation' => 'Harbor-Sunset-Cruise'], 'password'],
            'password confirmation differs' => [['password_confirmation' => 'Harbor-Sunset-2027'], 'password'],
            'terms not accepted' => [['terms' => null], 'terms'],
            'invalid email' => [['email' => 'jane@'], 'email'],
            'first name too long' => [['first_name' => str_repeat('a', 61)], 'first_name'],
            'missing last name' => [['last_name' => ''], 'last_name'],
            'letters in phone' => [['phone' => 'call me'], 'phone'],
        ];
    }

    #[DataProvider('invalidSignups')]
    public function test_invalid_registration_is_rejected(array $overrides, string $field): void
    {
        $this->postJson('/register', $this->signup($overrides))->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_an_email_can_only_register_once(): void
    {
        User::factory()->create(['email' => 'jane.doe@example.com']);

        $this->postJson('/register', $this->signup())->assertStatus(422)->assertJsonValidationErrors('email');
    }

    /* ------------------------------------------------------------------ login / logout */

    public function test_login_regenerates_the_session_and_goes_to_my_trips(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => strtoupper($user->email), 'password' => self::PASSWORD])->assertRedirect('/account/trips');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId(), 'session id must change on login');
    }

    public function test_login_errors_never_reveal_whether_an_email_exists(): void
    {
        User::factory()->create(['email' => 'real@example.com', 'password' => self::PASSWORD]);

        $wrongPassword = $this->postJson('/login', ['email' => 'real@example.com', 'password' => 'Not-The-Password-1'])->assertStatus(422)->json('errors.email.0');
        $unknownEmail = $this->postJson('/login', ['email' => 'nobody@example.com', 'password' => 'Not-The-Password-1'])->assertStatus(422)->json('errors.email.0');

        $this->assertSame($wrongPassword, $unknownEmail);
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'real@example.com', 'password' => self::PASSWORD]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'real@example.com', 'password' => 'Wrong-Password-'.$i])->assertStatus(422);
        }
        $this->postJson('/login', ['email' => 'real@example.com', 'password' => self::PASSWORD])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_logged_in_users_skip_the_guest_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/account/trips');
        $this->actingAs($user)->get('/register')->assertRedirect('/account/trips');
    }

    public function test_the_header_shows_the_signed_in_user(): void
    {
        $user = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->get('/')->assertSee('Log in or create an account')->assertDontSee('Account menu — Jane Doe');
        $this->actingAs($user)->get('/')->assertOk()->assertSee('Account menu — Jane Doe')->assertSeeText('JD')->assertSee('action="'.url('/logout').'"', false);
    }

    /* ------------------------------------------------------------------ email verification */

    public function test_the_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_tampered_or_unsigned_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1('someone@else.com')]);

        $this->actingAs($user)->get($wrongHash)->assertForbidden();
        $this->actingAs($user)->get('/email/verify/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_the_verification_email_can_be_resent_but_not_spammed(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification')->assertRedirect()->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post('/email/verification-notification');
        }
        $this->actingAs($user)->post('/email/verification-notification')->assertStatus(429);
    }

    public function test_the_verify_screen_needs_a_login(): void
    {
        $this->get('/email/verify')->assertRedirect('/login');
    }

    /* ------------------------------------------------------------------ password reset */

    public function test_forgot_password_answers_the_same_for_any_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'real@example.com']);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'real@example.com'])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE)->assertSessionHasNoErrors();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE)->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_reset_password_changes_it_once_and_logs_out_everywhere(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['email' => 'real@example.com']);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $token = Password::createToken($user);
        $oldRemember = $user->remember_token;

        $this->post('/reset-password', ['token' => $token, 'email' => 'real@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertRedirect('/login')->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame($oldRemember, $user->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count(), 'all other devices logged out');

        $this->post('/reset-password', ['token' => $token, 'email' => 'real@example.com', 'password' => 'Another-Pass-2027', 'password_confirmation' => 'Another-Pass-2027'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password), 'a reset token works only once');
    }

    public function test_after_a_reset_only_the_new_password_logs_in(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com', 'password' => 'Old-Password-2025']);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => 'real@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD]);

        $this->post('/login', ['email' => 'real@example.com', 'password' => 'Old-Password-2025'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'real@example.com', 'password' => self::PASSWORD])->assertRedirect('/account/trips');
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_password_still_enforces_the_password_rules(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com']);
        $token = Password::createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => 'real@example.com', 'password' => 'weakpass', 'password_confirmation' => 'weakpass'])
            ->assertSessionHasErrors('password');
    }

    /* ------------------------------------------------------------------ password confirmation */

    public function test_password_confirmation_screen_and_check(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->get('/user/confirm-password')->assertRedirect('/login');
        $this->actingAs($user)->get('/user/confirm-password')->assertOk()->assertSee('Confirm it');

        $this->actingAs($user)->post('/user/confirm-password', ['password' => 'Wrong-Password-1'])->assertSessionHasErrors('password');
        $this->actingAs($user)->post('/user/confirm-password', ['password' => self::PASSWORD])->assertSessionHas('auth.password_confirmed_at');
    }

    /* ------------------------------------------------------------------ limits */

    public function test_auth_pages_have_a_ceiling_per_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/login')->assertOk();
        }
        $this->get('/login')->assertStatus(429);
    }
}
