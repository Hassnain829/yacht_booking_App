<?php

namespace Tests\Feature\Phase2;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Providers\AuthServiceProvider;
use App\Services\UserAccountService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 2.2 — roles, status, Gates, UserPolicy, app:create-admin, the local seeder (PLAN §4.3, §6.1).
 */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Harbor-Sunset-2026';

    /* ------------------------------------------------------------------ role & status */

    public function test_new_accounts_are_active_customers_and_role_cannot_be_mass_assigned(): void
    {
        $user = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'password' => self::PASSWORD, 'role' => 'admin', 'status' => 'banned']);

        $this->assertSame(Role::Customer, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame('customer', $user->fresh()->role->value);
        $this->assertTrue($user->isCustomer());
        $this->assertFalse($user->isAdmin());
    }

    public function test_sign_up_ignores_a_role_in_the_request(): void
    {
        $this->post('/register', [
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD, 'terms' => '1', 'role' => 'admin', 'status' => 'active',
        ]);

        $this->assertSame(Role::Customer, User::sole()->role);
    }

    /* ------------------------------------------------------------------ gates */

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function abilities(): array
    {
        return collect(AuthServiceProvider::ABILITIES)->map(fn ($roles, $ability) => [$ability, $roles])->all();
    }

    #[DataProvider('abilities')]
    public function test_each_ability_belongs_to_the_right_roles(string $ability, array $roles): void
    {
        $users = ['customer' => User::factory()->create(), 'owner' => User::factory()->owner()->create(), 'admin' => User::factory()->admin()->create()];

        foreach ($users as $role => $user) {
            $this->assertSame(in_array($role, $roles, true), $user->can($ability), "{$role} → {$ability}");
        }
    }

    public function test_suspended_accounts_have_no_abilities(): void
    {
        $admin = User::factory()->admin()->suspended()->create();

        $this->assertFalse($admin->can('access-admin'));
        $this->assertFalse($admin->can('manage-users'));
    }

    /* ------------------------------------------------------------------ policy */

    public function test_user_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $jane = User::factory()->create();
        $joe = User::factory()->create();

        $this->assertTrue($jane->can('view', $jane));
        $this->assertFalse($jane->can('view', $joe), 'customers only see themselves');
        $this->assertTrue($admin->can('view', $joe));
        $this->assertTrue($jane->can('update', $jane));
        $this->assertFalse($admin->can('update', $jane), 'the admin does not edit other people\'s profiles');
        $this->assertTrue($admin->can('changeStatus', $jane));
        $this->assertFalse($admin->can('changeStatus', $admin), 'no changing your own status');
        $this->assertFalse($jane->can('changeStatus', $joe));
        $this->assertTrue($jane->can('delete', $jane));
        $this->assertFalse($admin->can('delete', $admin), 'the admin account cannot delete itself');
        $this->assertFalse($jane->can('viewAny', User::class));
        $this->assertTrue($admin->can('viewAny', User::class));
    }

    /* ------------------------------------------------------------------ login + status */

    public function test_suspended_and_banned_accounts_cannot_log_in(): void
    {
        $suspended = User::factory()->suspended()->create(['password' => self::PASSWORD]);
        $banned = User::factory()->banned()->create(['password' => self::PASSWORD]);

        foreach ([$suspended, $banned] as $user) {
            $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])
                ->assertStatus(422)
                ->assertJsonPath('errors.email.0', 'This account is not active. Please contact us if you think this is a mistake.');
            $this->assertGuest();
        }
    }

    public function test_a_wrong_password_on_a_suspended_account_gets_the_generic_error(): void
    {
        $user = User::factory()->suspended()->create(['password' => self::PASSWORD]);
        $generic = $this->postJson('/login', ['email' => 'nobody@example.com', 'password' => 'Wrong-Password-1'])->json('errors.email.0');

        $this->postJson('/login', ['email' => $user->email, 'password' => 'Wrong-Password-1'])->assertJsonPath('errors.email.0', $generic);
    }

    public function test_deleted_accounts_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $user->delete();

        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_login_records_the_time_and_ip(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD], ['REMOTE_ADDR' => '203.0.113.7']);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('203.0.113.7', $user->last_login_ip);
        $this->assertArrayNotHasKey('last_login_ip', $user->toArray(), 'the IP is never serialized');
        $this->assertArrayNotHasKey('id', $user->toArray(), 'the numeric id is never serialized');
    }

    /* ------------------------------------------------------------------ service */

    public function test_account_service_status_changes(): void
    {
        config(['session.driver' => 'database']);
        $service = app(UserAccountService::class);
        $admin = User::factory()->admin()->create();
        $jane = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'jane-phone', 'user_id' => $jane->id, 'payload' => '', 'last_activity' => time()]);

        $service->setStatus($jane, UserStatus::Suspended, $admin, 'Chargeback under review');
        $this->assertSame(UserStatus::Suspended, $jane->fresh()->status);
        $this->assertSame('Chargeback under review', $jane->fresh()->status_reason);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $jane->id)->count(), 'suspension logs out everywhere');

        $service->setStatus($jane, UserStatus::Active, $admin);
        $this->assertTrue($jane->fresh()->isActive());
        $this->assertNull($jane->fresh()->status_reason);
    }

    public function test_account_service_guards(): void
    {
        $service = app(UserAccountService::class);
        $admin = User::factory()->admin()->create();
        $jane = User::factory()->create();

        foreach ([
            fn () => $service->setStatus($jane, UserStatus::Banned, $jane, 'x'),          // not an admin
            fn () => $service->setStatus($admin, UserStatus::Suspended, $admin, 'x'),     // own account
            fn () => $service->setStatus($jane, UserStatus::Suspended, $admin),            // no reason
        ] as $call) {
            try {
                $call();
                $this->fail('Expected an exception');
            } catch (InvalidArgumentException) {
                $this->assertTrue($jane->fresh()->isActive());
            }
        }
    }

    public function test_make_owner_promotes_customers_only(): void
    {
        $service = app(UserAccountService::class);
        $jane = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($service->makeOwner($jane));
        $this->assertSame(Role::Owner, $jane->fresh()->role);
        $this->assertFalse($service->makeOwner($jane->fresh()), 'already an owner');
        $this->assertFalse($service->makeOwner($admin));
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    /* ------------------------------------------------------------------ header */

    public function test_header_menu_links_follow_the_role(): void
    {
        $this->actingAs(User::factory()->owner()->create())->get('/')->assertSee('Owner dashboard')->assertDontSee('Admin dashboard');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertSee('Admin dashboard')->assertDontSee('List your boat</a>', false);
    }

    /* ------------------------------------------------------------------ app:create-admin */

    public function test_create_admin_command(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('First name', 'Sam')
            ->expectsQuestion('Last name', 'Rivera')
            ->expectsQuestion('Email (used to log in)', 'Admin@Example.com')
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertSame('admin@example.com', $admin->email);
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
    }

    public function test_create_admin_rejects_weak_passwords_and_duplicates(): void
    {
        $this->artisan('app:create-admin', ['--first-name' => 'Sam', '--last-name' => 'Rivera', '--email' => 'admin@example.com'])
            ->expectsQuestion('Password', 'weak')
            ->expectsQuestion('Confirm password', 'weak')
            ->assertFailed();
        $this->assertSame(0, User::count());

        User::factory()->create(['email' => 'taken@example.com']);
        $this->artisan('app:create-admin', ['--first-name' => 'Sam', '--last-name' => 'Rivera', '--email' => 'taken@example.com'])
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->assertFailed();
    }

    public function test_only_one_admin_unless_forced(): void
    {
        User::factory()->admin()->create();

        $this->artisan('app:create-admin')->assertFailed();
        $this->assertSame(1, User::where('role', 'admin')->count());

        $this->artisan('app:create-admin', ['--force' => true, '--first-name' => 'Ana', '--last-name' => 'K', '--email' => 'second@example.com'])
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->assertSuccessful();
        $this->assertSame(2, User::where('role', 'admin')->count());
    }

    /* ------------------------------------------------------------------ seeder */

    public function test_local_seeder_creates_test_accounts_and_can_run_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, User::count());
        $this->assertSame(Role::Owner, User::where('email', 'owner@example.com')->sole()->role);
        $this->assertSame(0, User::where('role', 'admin')->count(), 'the admin is never seeded');
        $this->assertTrue(Hash::check(DatabaseSeeder::PASSWORD, User::where('email', 'test@example.com')->sole()->password));
    }

    public function test_seeder_refuses_to_run_in_production(): void
    {
        // (`db:seed` itself also asks for confirmation in production; this checks the seeder's own guard.)
        $this->app['env'] = 'production';
        (new DatabaseSeeder)->setContainer($this->app)->run();
        $this->app['env'] = 'testing';

        $this->assertSame(0, User::count());
    }
}
