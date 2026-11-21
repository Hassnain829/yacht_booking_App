<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * LOCAL TEST ACCOUNTS ONLY — refuses to run in production.
 * The admin is never seeded: create it with `php artisan app:create-admin`.
 * Safe to run again (accounts are updated, not duplicated).
 */
class DatabaseSeeder extends Seeder
{
    /** Local-only password for the test accounts below. */
    public const PASSWORD = 'Local-Test-2026';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipped: test accounts are never created in production.');

            return;
        }

        $accounts = [
            ['test@example.com', 'Test', 'Customer', Role::Customer],
            ['owner@example.com', 'Test', 'Owner', Role::Owner],
        ];

        foreach ($accounts as [$email, $first, $last, $role]) {
            $user = User::withTrashed()->firstOrNew(['email' => $email]);
            $user->fill(['first_name' => $first, 'last_name' => $last, 'password' => self::PASSWORD]);
            $user->forceFill(['role' => $role, 'status' => UserStatus::Active, 'email_verified_at' => $user->email_verified_at ?? now(), 'deleted_at' => null])->save();
        }

        $this->command?->table(['Email', 'Role', 'Password'], array_map(fn ($a) => [$a[0], $a[3]->label(), self::PASSWORD], $accounts));
    }
}
