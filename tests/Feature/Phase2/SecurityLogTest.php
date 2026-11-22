<?php

namespace Tests\Feature\Phase2;

use App\Enums\LoginEventType;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
use App\Notifications\PasswordChanged;
use App\Services\AuditService;
use App\Services\LoginEventService;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use LogicException;
use Tests\TestCase;

/**
 * Phase 2.4 — the security log (login_events), security emails, Devices + "log out other devices",
 * and the audit log (PLAN §8.1, §8.5).
 */
class SecurityLogTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Harbor-Sunset-2026';

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    private const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    /** @param array<string, string> $headers */
    private function logIn(User $user, array $headers = []): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD], $headers)->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    /* ------------------------------------------------------------------ login_events */

    public function test_logins_failures_and_logouts_are_logged_without_secrets(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'Wrong-Password-2026']);
        $this->post('/login', ['email' => 'ghost@example.com', 'password' => 'Ghost-Password-2026']);
        $this->logIn($user, ['User-Agent' => self::CHROME_WINDOWS]);
        $this->post('/logout');

        $events = LoginEvent::query()->orderBy('id')->get();
        $this->assertSame([LoginEventType::Failed, LoginEventType::Failed, LoginEventType::Login, LoginEventType::Logout], $events->pluck('event')->all());
        [$wrong, $ghost, $login] = $events->all();

        $this->assertSame($user->id, $wrong->user_id, 'a wrong password is linked to the account');
        $this->assertSame($user->email, $wrong->email);
        $this->assertNull($ghost->user_id);
        $this->assertSame('ghost@example.com', $ghost->email);
        $this->assertSame('127.0.0.1', $login->ip_address);
        $this->assertSame(self::CHROME_WINDOWS, $login->user_agent);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $login->device_id, 'only a hash of the device cookie');
        $this->assertNotNull($user->fresh()->last_login_at);

        foreach (['Wrong-Password-2026', 'Ghost-Password-2026', self::PASSWORD] as $secret) {
            $this->assertStringNotContainsString($secret, $events->toJson());
        }
    }

    public function test_a_blocked_login_is_logged_as_a_lockout(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'Wrong-Password-2026'])->assertRedirect();
        }
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(429)->assertSee('Slow down a little');

        $this->assertGuest();
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::Lockout)->exists());
    }

    public function test_login_events_older_than_a_year_are_pruned(): void
    {
        $user = User::factory()->create();
        LoginEvent::query()->insert([
            ['user_id' => $user->id, 'event' => 'login', 'created_at' => now()->subYear()->subDay()],
            ['user_id' => $user->id, 'event' => 'login', 'created_at' => now()->subMonths(11)],
        ]);

        $this->artisan('model:prune', ['--model' => [LoginEvent::class]])->assertSuccessful();

        $this->assertSame(1, LoginEvent::query()->count());
    }

    /* ------------------------------------------------------------------ security emails */

    public function test_owners_are_emailed_about_a_login_from_a_new_device(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create(['password' => self::PASSWORD]);

        // The very first login: no email, and the browser gets a device cookie.
        $first = $this->post('/login', ['email' => $owner->email, 'password' => self::PASSWORD]);
        $device = $first->getCookie(LoginEventService::DEVICE_COOKIE);
        $this->assertNotNull($device);
        $this->assertTrue($device->isHttpOnly());
        Notification::assertNothingSent();
        $this->post('/logout');

        // Another browser (no device cookie): email.
        $this->logIn($owner, ['User-Agent' => self::SAFARI_IPHONE]);
        Notification::assertSentToTimes($owner, NewDeviceLogin::class, 1);
        Notification::assertSentTo($owner, NewDeviceLogin::class, fn (NewDeviceLogin $n) => str_starts_with($n->context->summary(), 'Safari on iPhone · IP 127.0.0.1 · '));
        $this->post('/logout');

        // The first browser again: known, no new email.
        $this->withCookie(LoginEventService::DEVICE_COOKIE, $device->getValue());
        $this->logIn($owner);
        Notification::assertSentToTimes($owner, NewDeviceLogin::class, 1);
    }

    public function test_customers_are_not_emailed_about_new_devices(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['password' => self::PASSWORD]);

        $this->logIn($customer);
        $this->post('/logout');
        $this->logIn($customer);

        Notification::assertNotSentTo($customer, NewDeviceLogin::class);
    }

    public function test_a_password_reset_is_logged_and_emailed(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'New-Harbor-Pass-2026', 'password_confirmation' => 'New-Harbor-Pass-2026'])
            ->assertRedirect(route('login'));

        Notification::assertSentTo($user, PasswordChanged::class);
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::PasswordChanged)->exists());
    }

    public function test_security_emails_say_what_happened_and_where(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create(['password' => self::PASSWORD]);
        LoginEvent::query()->insert(['user_id' => $owner->id, 'event' => 'login', 'device_id' => str_repeat('a', 64), 'created_at' => now()->subDay()]);

        $this->logIn($owner, ['User-Agent' => self::CHROME_WINDOWS]);

        Notification::assertSentTo($owner, NewDeviceLogin::class, function (NewDeviceLogin $n) use ($owner) {
            $mail = $n->toMail($owner);
            $this->assertSame('New login to your '.config('app.name').' account', $mail->subject);
            $this->assertStringContainsString('Chrome on Windows · IP 127.0.0.1', implode(' ', $mail->introLines));
            $this->assertSame(route('account.profile').'#sessions', $mail->actionUrl);

            return true;
        });
    }

    /* ------------------------------------------------------------------ Devices + log out other devices */

    public function test_devices_lists_only_this_account_s_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'my-phone', 'user_id' => $user->id, 'ip_address' => '198.51.100.7', 'user_agent' => self::SAFARI_IPHONE, 'payload' => '', 'last_activity' => now()->subDays(2)->timestamp],
            ['id' => 'not-mine', 'user_id' => $someoneElse->id, 'ip_address' => '203.0.113.50', 'user_agent' => self::CHROME_WINDOWS, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->get('/account', ['User-Agent' => self::CHROME_WINDOWS])
            ->assertOk()
            ->assertSeeInOrder(['Chrome on Windows', 'IP 127.0.0.1', 'This device', 'Current', 'Safari on iPhone', 'IP 198.51.100.7', 'Last active 2 days ago'])
            ->assertDontSee('203.0.113.50');
    }

    public function test_log_out_other_devices_needs_a_login_and_a_recent_password(): void
    {
        $this->delete('/account/sessions')->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->from('/account')->delete('/account/sessions')->assertRedirect(route('password.confirm'));
        $this->assertFalse(LoginEvent::query()->where('event', LoginEventType::OtherDevicesLoggedOut)->exists());
    }

    public function test_log_out_other_devices_ends_the_others_and_keeps_this_one(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();
        $token = $user->remember_token;
        DB::table('sessions')->insert([
            ['id' => 'my-phone', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'my-laptop', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'not-mine', 'user_id' => $someoneElse->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/account/sessions')
            ->assertRedirect(route('account.profile').'#sessions')
            ->assertSessionHas('sessions_status', 'Logged out of 2 other devices.');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, DB::table('sessions')->whereIn('id', ['my-phone', 'my-laptop'])->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'not-mine')->count(), "another account's sessions are untouched");
        $this->assertNotSame($token, $user->fresh()->remember_token, '"remember me" on other browsers stops working');
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::OtherDevicesLoggedOut)->exists());
    }

    public function test_log_out_other_devices_can_be_repeated_and_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        for ($i = 0; $i < 10; $i++) {
            $this->delete('/account/sessions')->assertRedirect(route('account.profile').'#sessions');
        }
        $this->delete('/account/sessions')->assertStatus(429);
    }

    /* ------------------------------------------------------------------ audit log */

    public function test_status_and_role_changes_are_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $accounts = app(UserAccountService::class);

        $accounts->setStatus($owner, UserStatus::Suspended, $admin, 'Chargeback under review');
        $accounts->makeOwner($customer);

        $status = AuditLog::query()->where('action', 'user.status_changed')->sole();
        $this->assertSame($admin->id, $status->user_id);
        $this->assertSame('user', $status->subject_type, 'a short morph name, never a class name');
        $this->assertTrue($status->subject->is($owner));
        $this->assertSame(['status' => 'active'], $status->before);
        $this->assertSame(['status' => 'suspended'], $status->after);
        $this->assertSame('Chargeback under review', $status->reason);

        $role = AuditLog::query()->where('action', 'user.role_changed')->sole();
        $this->assertSame($customer->id, $role->user_id);
        $this->assertSame(['role' => 'owner'], $role->after);
    }

    public function test_audit_entries_can_never_be_changed_or_deleted(): void
    {
        $entry = app(AuditService::class)->record('test.recorded', after: ['value' => 1]);

        foreach ([fn () => $entry->update(['reason' => 'edited']), fn () => $entry->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('An audit entry was changed.');
            } catch (LogicException) {
                // expected
            }
        }

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertNull($entry->fresh()->reason);
    }
}
