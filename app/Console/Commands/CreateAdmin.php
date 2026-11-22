<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the administrator (PLAN §4.1: the admin never signs up on the website).
 * Run on the server: php artisan app:create-admin
 *
 * The password is only ever typed into a hidden prompt — never passed as an option, so it can't end up
 * in shell history. Two-factor login is the admin's choice: Admin → Settings → Security (recommended live).
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
                            {--first-name= : First name}
                            {--last-name= : Last name}
                            {--email= : Email address (used to log in)}
                            {--force : Create another admin even if one already exists}';

    protected $description = 'Create the administrator account (the admin never signs up on the website)';

    public function handle(AuditService $audit): int
    {
        if (User::withTrashed()->where('role', Role::Admin)->exists() && ! $this->option('force')) {
            $this->components->error('An admin account already exists. Run with --force to add another one.');

            return self::FAILURE;
        }

        $input = [
            'first_name' => $this->option('first-name') ?: text('First name', required: true),
            'last_name' => $this->option('last-name') ?: text('Last name', required: true),
            'email' => Str::lower(trim((string) ($this->option('email') ?: text('Email (used to log in)', required: true)))),
            'password' => password('Password', required: true, hint: 'At least 12 characters, with upper- and lower-case letters and a number.'),
        ];
        $input['password_confirmation'] = password('Confirm password', required: true);

        $validator = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $data = $validator->validated();
        $admin = new User([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        // System-only fields: the role, and a verified email (the person running this command owns it).
        $admin->forceFill(['role' => Role::Admin, 'email_verified_at' => now()])->save();
        $audit->record('user.admin_created', $admin, after: ['role' => Role::Admin->value, 'email' => $admin->email]);

        $this->components->info("Admin account created for {$admin->email}.");
        $this->components->bulletList([
            'Log in at '.route('login'),
            'Recommended: turn on two-factor login in Admin → Settings → Security.',
        ]);

        return self::SUCCESS;
    }
}
