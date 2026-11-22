<?php

namespace Tests\Feature\Phase2;

use App\Enums\LoginEventType;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\User;
use App\Notifications\TwoFactorChanged;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Phase 2.4 — two-factor login (PLAN §8.1): the login challenge, turning it on / off, recovery codes,
 * the `2fa` middleware for the admin, and the command-line reset.
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Harbor-Sunset-2026';

    /** The code the user's authenticator app shows right now. */
    private function code(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret));
    }

    private function wrongCode(User $user): string
    {
        return $this->code($user) === '000000' ? '111111' : '000000';
    }

    private function logIn(User $user, bool $remember = false): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD, 'remember' => $remember ? '1' : null])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    /** As if the user typed their password on the "Confirm it's you" screen a moment ago. */
    private function withRecentPassword(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    /* ------------------------------------------------------------------ the login challenge */

    public function test_the_challenge_only_opens_after_a_correct_password(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));

        $user = User::factory()->withTwoFactor()->create(['password' => self::PASSWORD]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Wrong-Password-2026'])->assertSessionHasErrors('email');
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    }

    public function test_a_two_factor_account_logs_in_only_with_a_valid_code(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => self::PASSWORD]);
        $this->logIn($user);

        $html = $this->get('/two-factor-challenge')->assertOk()->assertSee('Enter your code')->assertSee('name="_token"', false)->getContent();
        $this->assertStringNotContainsString('data-demo-form', $html);
        $this->assertStringContainsString('action="'.route('two-factor.login.store').'"', $html);

        $this->post('/two-factor-challenge', ['code' => $this->wrongCode($user)])->assertRedirect(route('two-factor.login'))->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->code($user)])->assertRedirect('/account/trips');
        $this->assertAuthenticatedAs($user);
        $this->assertSame([LoginEventType::TwoFactorFailed, LoginEventType::Login], LoginEvent::query()->where('user_id', $user->id)->orderBy('id')->pluck('event')->all());
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => self::PASSWORD]);
        $code = $this->code($user);

        $this->logIn($user);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/account/trips');
        $this->post('/logout');

        $this->logIn($user);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_recovery_code_logs_in_once_and_is_replaced(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => self::PASSWORD]);
        $recovery = $user->recoveryCodes()[0];

        $this->logIn($user);
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/account/trips');
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recovery, $user->fresh()->recoveryCodes());
        $this->assertCount(8, $user->fresh()->recoveryCodes());
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::RecoveryCodeUsed)->exists());
        $this->post('/logout');

        // The used code no longer works, and the screen comes back in recovery-code mode.
        $this->logIn($user);
        // (No assertSessionHasErrors before the next page: with JSON session serialization that assertion
        // re-reads the session and the following request would then see an empty error bag.)
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect(route('two-factor.login'));
        $html = $this->get('/two-factor-challenge')->assertOk()->assertSee('The provided two factor recovery code was invalid.')->getContent();
        $this->assertMatchesRegularExpression('/<div class="" data-tfa-field="recovery">/', $html);
        $this->assertMatchesRegularExpression('/<div class="hidden" data-tfa-field="code">/', $html);
        $this->assertGuest();
    }

    public function test_the_challenge_allows_five_tries_a_minute(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => self::PASSWORD]);
        $this->logIn($user);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['code' => $this->wrongCode($user)])->assertRedirect(route('two-factor.login'));
        }
        $this->post('/two-factor-challenge', ['code' => $this->code($user)])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_the_admin_is_never_remembered_even_through_the_challenge(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create(['password' => self::PASSWORD]);
        /** @var SessionGuard $guard */
        $guard = auth()->guard('web');

        $this->logIn($admin, remember: true);
        $this->post('/two-factor-challenge', ['code' => $this->code($admin)])->assertCookieMissing($guard->getRecallerName());
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_suspended_account_never_reaches_the_challenge(): void
    {
        $user = User::factory()->suspended()->withTwoFactor()->create(['password' => self::PASSWORD]);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE])
            ->assertSessionMissing('login.id');
    }

    /* ------------------------------------------------------------------ turning it on and off */

    public function test_the_setup_page_needs_a_login_and_a_recent_password(): void
    {
        $this->get('/account/two-factor')->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get('/account/two-factor')->assertRedirect(route('password.confirm'));
        $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect(route('password.confirm'));
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_turning_it_on_needs_a_code_from_the_app(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->withRecentPassword($user);

        $this->get('/account/two-factor')->assertOk()->assertSee('Two-factor login is off')->assertHeader('Cache-Control', 'no-store, private');

        $this->from('/account/two-factor')->post('/user/two-factor-authentication', ['force' => '1'])->assertRedirect('/account/two-factor');
        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $html = $this->get('/account/two-factor')->assertOk()
            ->assertSee('Finish turning it on')
            ->assertSee(trim(chunk_split($secret, 4, ' ')))
            ->getContent();
        // The QR code: an image, black on white, 4-module quiet zone, 5 whole screen pixels per module, shown at its own size.
        $this->assertMatchesRegularExpression('#<img src="data:image/svg\+xml;base64,[A-Za-z0-9+/=]+" alt="QR code[^"]*" width="(\d+)" height="\1"#', $html);
        preg_match('#src="data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)"[^>]*width="(\d+)"#', $html, $qr);
        $svg = base64_decode($qr[1]);
        $this->assertStringContainsString('width="'.$qr[2].'" height="'.$qr[2].'"', $svg);
        $this->assertStringContainsString('<g transform="scale(5)"><g transform="translate(4,4)">', $svg, 'whole-pixel modules + quiet zone');
        $this->assertStringContainsString('fill="#000000"', $svg);
        $this->assertSame(0, (int) $qr[2] % 5);
        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication(), 'not on before a valid code');

        $this->from('/account/two-factor')->post('/user/confirmed-two-factor-authentication', ['code' => $this->wrongCode($user)])
            ->assertRedirect('/account/two-factor');
        $this->get('/account/two-factor')->assertSee('The provided two factor authentication code was invalid.')->assertSee('Finish turning it on');
        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());

        $this->from('/account/two-factor')->post('/user/confirmed-two-factor-authentication', ['code' => $this->code($user)])->assertRedirect('/account/two-factor');
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());

        $html = $this->get('/account/two-factor')->assertOk()
            ->assertSee('Two-factor login is on')
            ->assertSee($user->fresh()->recoveryCodes()[0])
            ->assertDontSee('data:image/svg+xml', false)
            ->assertDontSee($secret)
            ->getContent();
        $this->assertMatchesRegularExpression('/<details[^>]*\sopen/', $html, 'recovery codes are shown right after turning it on');
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::TwoFactorEnabled)->exists());
        Notification::assertSentTo($user, TwoFactorChanged::class, fn (TwoFactorChanged $n) => $n->enabled);
        $this->get('/account')->assertSee('Manage two-factor login');
    }

    public function test_recovery_codes_can_be_replaced(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $old = $user->recoveryCodes();
        $this->withRecentPassword($user);

        $this->from('/account/two-factor')->post('/user/two-factor-recovery-codes')->assertRedirect('/account/two-factor');
        $new = $user->fresh()->recoveryCodes();
        $this->assertCount(8, $new);
        $this->assertEmpty(array_intersect($old, $new));
        $this->get('/account/two-factor')->assertSee('New recovery codes are ready')->assertSee($new[0])->assertDontSee($old[0]);
    }

    public function test_turning_it_off_is_emailed_to_the_account(): void
    {
        Notification::fake();
        $user = User::factory()->owner()->withTwoFactor()->create();
        $this->withRecentPassword($user);

        $this->get('/account/two-factor')->assertSee('Turn off two-factor login');
        $this->from('/account/two-factor')->delete('/user/two-factor-authentication')->assertRedirect('/account/two-factor');

        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->get('/account/two-factor')->assertSee('Two-factor login is off.');
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::TwoFactorDisabled)->exists());
        Notification::assertSentTo($user, TwoFactorChanged::class, fn (TwoFactorChanged $n) => ! $n->enabled && ! $n->byOurTeam);
    }

    /* ------------------------------------------------------------------ the admin */

    public function test_two_factor_is_the_admin_s_choice_shown_in_admin_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $this->withRecentPassword($admin);

        // Off: the admin area still opens; Settings → Security shows it is off and links to turn it on.
        $this->get('/admin/bookings')->assertOk();
        $this->get('/admin/settings')->assertOk()
            ->assertSee('id="security"', false)
            ->assertSee('Two-factor login off')
            ->assertSee('Turn on two-factor login')
            ->assertSee('href="'.route('account.two-factor').'"', false);
        $this->get('/account')->assertSee('Recommended for the admin account.');

        // On: shown as on, with the way to manage or turn it off.
        $admin->forceFill(User::factory()->withTwoFactor()->make()->only(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']))->save();
        $this->get('/admin/settings')->assertOk()->assertSee('Two-factor login on')->assertSee('Manage or turn off');
    }

    public function test_someone_sent_by_a_two_factor_area_is_told_why_after_confirming_their_password(): void
    {
        Route::middleware(['web', 'auth', '2fa'])->get('/_test/owner-money', fn () => 'ok'); // like the owner money area (Phase 7)
        $owner = User::factory()->owner()->create(['password' => self::PASSWORD]);

        $this->actingAs($owner)->get('/_test/owner-money')->assertRedirect(route('account.two-factor'));
        $this->get('/account/two-factor')->assertRedirect(route('password.confirm'));
        $this->post('/user/confirm-password', ['password' => self::PASSWORD])->assertRedirect('/account/two-factor');

        $this->get('/account/two-factor')->assertOk()->assertSee(EnsureTwoFactorEnabled::MESSAGE);
        $this->get('/account/two-factor')->assertDontSee(EnsureTwoFactorEnabled::MESSAGE);
        $this->getJson('/_test/owner-money')->assertStatus(403)->assertJson(['message' => EnsureTwoFactorEnabled::MESSAGE]);
    }

    public function test_the_admin_can_turn_it_off_and_still_use_the_admin_area(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $this->withRecentPassword($admin);

        $this->get('/account/two-factor')->assertOk()->assertSee('Turn off two-factor login')->assertSee('We recommend keeping it on for the admin account');
        $this->from('/account/two-factor')->delete('/user/two-factor-authentication')->assertRedirect('/account/two-factor');

        $this->assertFalse($admin->fresh()->hasEnabledTwoFactorAuthentication());
        Notification::assertSentTo($admin, TwoFactorChanged::class, fn (TwoFactorChanged $n) => ! $n->enabled);
        $this->get('/admin')->assertOk();
    }

    /* ------------------------------------------------------------------ command-line reset */

    public function test_the_command_line_resets_two_factor_for_a_locked_out_account(): void
    {
        Notification::fake();
        config(['session.driver' => 'database']);
        $admin = User::factory()->admin()->withTwoFactor()->create();
        DB::table('sessions')->insert(['id' => 'admin-laptop', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);

        $this->artisan('app:reset-two-factor', ['--email' => $admin->email, '--reason' => 'Lost phone and codes; identity checked by phone call'])
            ->expectsConfirmation("Turn off two-factor login for {$admin->name} ({$admin->email}) and log them out everywhere?", 'yes')
            ->assertSuccessful();

        $this->assertFalse($admin->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertNull($admin->fresh()->two_factor_recovery_codes);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $admin->id)->count(), 'logged out everywhere');
        $entry = AuditLog::query()->where('action', 'user.two_factor_reset')->sole();
        $this->assertNull($entry->user_id, 'done from the command line');
        $this->assertSame('Lost phone and codes; identity checked by phone call', $entry->reason);
        $this->assertSame(['two_factor' => true], $entry->before);
        Notification::assertSentTo($admin, TwoFactorChanged::class, fn (TwoFactorChanged $n) => ! $n->enabled && $n->byOurTeam);
    }

    public function test_the_command_line_reset_changes_nothing_without_a_yes(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->artisan('app:reset-two-factor', ['--email' => $user->email, '--reason' => 'Asked by email'])
            ->expectsConfirmation("Turn off two-factor login for {$user->name} ({$user->email}) and log them out everywhere?", 'no')
            ->assertFailed();
        $this->artisan('app:reset-two-factor', ['--email' => 'nobody@example.com', '--reason' => 'x'])->assertFailed();

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertSame(0, AuditLog::query()->count());
    }
}
