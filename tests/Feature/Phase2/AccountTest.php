<?php

namespace Tests\Feature\Phase2;

use App\Enums\LoginEventType;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\User;
use App\Notifications\AccountDeleted;
use App\Notifications\EmailChanged;
use App\Notifications\PasswordChanged;
use App\Notifications\SecurityContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Phase 2.5 — role-based redirects (PLAN §4.2) and the real Profile & security page:
 * details + email change, password change, delete account (PLAN §8.1, §8.5).
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Harbor-Sunset-2026';

    /** @param array<string, mixed> $overrides */
    private function details(User $user, array $overrides = []): array
    {
        return [...['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email, 'phone' => $user->phone, 'marketing' => '0'], ...$overrides];
    }

    /* ------------------------------------------------------------------ redirects */

    public function test_after_login_each_role_lands_on_its_home(): void
    {
        foreach ([[User::factory()->admin(), '/admin'], [User::factory()->owner(), '/owner'], [User::factory(), '/account/trips']] as [$factory, $home]) {
            $user = $factory->create(['password' => self::PASSWORD]);

            $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect($home);
            $this->get('/login')->assertRedirect($home); // already signed in
            $this->post('/logout');
        }
    }

    public function test_the_page_they_wanted_still_wins_and_two_factor_logins_land_home_too(): void
    {
        $customer = User::factory()->create(['password' => self::PASSWORD]);
        $this->get('/account')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $customer->email, 'password' => self::PASSWORD])->assertRedirect('/account');
        $this->post('/logout');

        $admin = User::factory()->admin()->withTwoFactor()->create(['password' => self::PASSWORD]);
        $this->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD])->assertRedirect(route('two-factor.login'));
        $code = app(Google2FA::class)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($admin->two_factor_secret));
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/admin');
    }

    /* ------------------------------------------------------------------ your details */

    public function test_details_are_saved_without_a_password_when_the_email_stays(): void
    {
        $user = User::factory()->create(['marketing_opt_in' => true]);

        $this->actingAs($user)->from('/account')
            ->put('/user/profile-information', $this->details($user, ['first_name' => ' Jane ', 'phone' => '(312) 555-0199', 'marketing' => '0', 'role' => 'admin', 'status' => 'banned']))
            ->assertRedirect('/account')->assertSessionHas('status', 'profile-information-updated');

        $user->refresh();
        $this->assertSame('Jane', $user->first_name);
        $this->assertSame('(312) 555-0199', $user->phone);
        $this->assertFalse($user->marketing_opt_in, 'the hidden 0 unticks it');
        $this->assertSame(Role::Customer, $user->role, 'role never comes from the request');
        $this->assertTrue($user->isActive(), 'status never comes from the request');
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_details_need_a_login_and_valid_input(): void
    {
        $this->put('/user/profile-information', ['first_name' => 'X'])->assertRedirect(route('login'));

        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($user)->from('/account')
            ->put('/user/profile-information', $this->details($user, ['first_name' => '', 'last_name' => str_repeat('a', 61), 'email' => 'taken@example.com', 'phone' => '<script>']))
            ->assertRedirect('/account')
            ->assertSessionHasErrorsIn('updateProfileInformation', ['first_name', 'last_name', 'email', 'phone']);
        $this->actingAs($user)->putJson('/user/profile-information', $this->details($user, ['email' => 'not-an-email']))->assertStatus(422);
    }

    public function test_changing_the_email_needs_the_password_and_tells_the_old_address(): void
    {
        Notification::fake();
        $user = User::factory()->owner()->create(['email' => 'old.address@example.com', 'password' => self::PASSWORD]);
        $this->actingAs($user)->from('/account');

        $this->put('/user/profile-information', $this->details($user, ['email' => 'new.address@example.com']))
            ->assertSessionHasErrorsIn('updateProfileInformation', ['current_password' => 'Enter your current password to change your email.']);
        $this->put('/user/profile-information', $this->details($user, ['email' => 'new.address@example.com', 'current_password' => 'Wrong-Password-2026']))
            ->assertSessionHasErrorsIn('updateProfileInformation', 'current_password');
        $this->assertSame('old.address@example.com', $user->fresh()->email);

        $this->put('/user/profile-information', $this->details($user, ['email' => 'New.Address@Example.com', 'current_password' => self::PASSWORD]))
            ->assertRedirect('/account')->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new.address@example.com', $user->email);
        $this->assertNull($user->email_verified_at, 'the new address must be confirmed');
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentOnDemand(EmailChanged::class, function (EmailChanged $n, array $channels, object $notifiable) {
            return $notifiable->routes['mail'] === 'old.address@example.com' && EmailChanged::mask($n->newEmail) === 'n**********@example.com';
        });
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::EmailChanged)->exists());
        $this->get('/owner')->assertRedirect(route('verification.notice')); // the owner area waits for the new address
    }

    public function test_the_profile_page_shows_the_real_account(): void
    {
        $user = User::factory()->unverified()->create(['first_name' => 'Jane', 'email' => 'jane@example.com', 'marketing_opt_in' => true]);

        $html = $this->actingAs($user)->get('/account')->assertOk()
            ->assertSee('value="Jane"', false)
            ->assertSee('data-current-email="jane@example.com"', false)
            ->assertSee('Please confirm')
            ->assertSee('action="'.route('verification.send').'"', false)
            ->assertSee('Last changed')
            ->assertSee('id="delete-dialog"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/<div class="sm:col-span-2 hidden" data-email-password>/', $html, 'the password box waits for an email change');
        $this->assertMatchesRegularExpression('/name="marketing" type="checkbox" value="1"\s+checked/', $html);
    }

    public function test_a_failed_password_form_does_not_untick_the_marketing_box(): void
    {
        $user = User::factory()->create(['marketing_opt_in' => true, 'password' => self::PASSWORD]);

        // (No assertSessionHasErrors before the next page: see the JSON-session note in TwoFactorTest.)
        $this->actingAs($user)->from('/account')->put('/user/password', ['current_password' => 'Wrong-Password-2026', 'password' => 'x', 'password_confirmation' => 'x']);
        $html = $this->get('/account')->assertOk()->assertSee('The provided password does not match your current password.')->getContent();

        $this->assertMatchesRegularExpression('/name="marketing" type="checkbox" value="1"\s+checked/', $html);
    }

    /* ------------------------------------------------------------------ password */

    public function test_changing_the_password_logs_other_devices_out_and_emails(): void
    {
        Notification::fake();
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $changedBefore = $user->password_changed_at;
        DB::table('sessions')->insert(['id' => 'my-phone', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->travel(5)->minutes();

        $this->actingAs($user)->from('/account')->put('/user/password', ['current_password' => 'Wrong-Password-2026', 'password' => 'New-Harbor-Pass-2026', 'password_confirmation' => 'New-Harbor-Pass-2026'])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->actingAs($user)->from('/account')->put('/user/password', ['current_password' => self::PASSWORD, 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));

        $this->actingAs($user)->from('/account')->put('/user/password', ['current_password' => self::PASSWORD, 'password' => 'New-Harbor-Pass-2026', 'password_confirmation' => 'New-Harbor-Pass-2026'])
            ->assertRedirect('/account')->assertSessionHas('status', 'password-updated');

        $user->refresh();
        $this->assertTrue(Hash::check('New-Harbor-Pass-2026', $user->password));
        $this->assertTrue($user->password_changed_at->gt($changedBefore));
        $this->assertSame(0, DB::table('sessions')->where('id', 'my-phone')->count(), 'other devices are logged out');
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, PasswordChanged::class);
        $this->assertTrue(LoginEvent::query()->where('user_id', $user->id)->where('event', LoginEventType::PasswordChanged)->exists());
    }

    /* ------------------------------------------------------------------ delete account */

    public function test_delete_account_needs_a_login_your_password_and_is_never_for_the_admin(): void
    {
        $this->delete('/account', ['password' => self::PASSWORD])->assertRedirect(route('login'));

        $admin = User::factory()->admin()->create(['password' => self::PASSWORD]);
        $this->actingAs($admin)->delete('/account', ['password' => self::PASSWORD])->assertForbidden();
        $this->actingAs($admin)->get('/account')->assertSee("The admin account can't be deleted.")->assertDontSee('id="delete-dialog"', false);
        $this->assertNotSoftDeleted($admin);

        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->actingAs($user)->from('/account')->delete('/account', ['password' => 'Wrong-Password-2026'])
            ->assertRedirect('/account')->assertSessionHasErrorsIn('deleteAccount', 'password');
        $this->actingAs($user)->from('/account')->delete('/account')->assertSessionHasErrorsIn('deleteAccount', 'password');
        $this->assertNotSoftDeleted($user);
    }

    public function test_a_wrong_password_reopens_the_delete_dialog(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->actingAs($user)->from('/account')->delete('/account', ['password' => 'Wrong-Password-2026']);
        $html = $this->get('/account')->assertSee('The provided password does not match your current password.')->getContent();
        $this->assertMatchesRegularExpression('/<dialog id="delete-dialog"[^>]*\sdata-open-on-load\s/', $html);
    }

    public function test_deleting_an_account_anonymizes_it_logs_it_out_and_frees_the_email(): void
    {
        Notification::fake();
        config(['session.driver' => 'database']);
        $user = User::factory()->owner()->withTwoFactor()->create(['email' => 'leaving@example.com', 'phone' => '(312) 555-0100', 'password' => self::PASSWORD]);
        LoginEvent::query()->insert(['user_id' => $user->id, 'email' => 'leaving@example.com', 'event' => 'login', 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => 'leaving-phone', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($user)->delete('/account', ['password' => self::PASSWORD])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been deleted. A confirmation is on its way to your email.');
        $this->assertGuest();

        $user = User::withTrashed()->find($user->id);
        $this->assertSoftDeleted($user);
        $this->assertSame('Deleted', $user->first_name);
        $this->assertSame("deleted-{$user->ulid}@deleted.invalid", $user->email);
        $this->assertNull($user->phone);
        $this->assertNull($user->two_factor_secret);
        $this->assertFalse(Hash::check(self::PASSWORD, $user->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(0, LoginEvent::query()->where('email', 'leaving@example.com')->count(), 'the security log forgets the address');
        $this->assertSame($user->id, AuditLog::query()->where('action', 'user.deleted')->sole()->user_id);
        Notification::assertSentOnDemand(AccountDeleted::class, fn ($n, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'leaving@example.com');

        // The account can't log in, and the address is free for a new sign-up.
        $this->post('/login', ['email' => 'leaving@example.com', 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertFalse(User::withTrashed()->where('email', 'leaving@example.com')->exists());
    }

    public function test_queued_security_emails_skip_an_account_deleted_in_the_meantime(): void
    {
        Event::fake([NotificationSent::class]);
        $user = User::factory()->create();
        $notification = new PasswordChanged(SecurityContext::fromRequest());

        $user->notify($notification); // the test queue runs it straight away
        Event::assertDispatchedTimes(NotificationSent::class, 1);

        // Deleted (and anonymized) before the queue got to it: the email is dropped instead of bouncing.
        $user->forceFill(['email' => 'deleted-x@deleted.invalid'])->save();
        $user->delete();
        $user->notify($notification);
        Event::assertDispatchedTimes(NotificationSent::class, 1);
    }

    public function test_delete_account_is_rate_limited(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->from('/account')->delete('/account', ['password' => 'Wrong-Password-2026'])->assertRedirect('/account');
        }
        $this->actingAs($user)->delete('/account', ['password' => self::PASSWORD])->assertStatus(429);
        $this->assertNotSoftDeleted($user);
    }
}
