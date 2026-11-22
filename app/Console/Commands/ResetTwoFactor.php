<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Turns two-factor login off for an account whose owner lost both their authenticator and their
 * recovery codes — the way back in for the only admin (PLAN §8.1). Run on the server:
 * php artisan app:reset-two-factor --email=someone@example.com
 *
 * The account is logged out everywhere, told by email, and the reset is written to the audit log.
 * Check the person's identity first: this removes the second step of their login.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'app:reset-two-factor
                            {--email= : Email address of the account}
                            {--reason= : Why (kept in the audit log)}';

    protected $description = 'Turn off two-factor login for an account that lost its authenticator and recovery codes';

    public function handle(UserAccountService $accounts): int
    {
        $email = Str::lower(trim((string) ($this->option('email') ?: text('Email of the account', required: true))));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No account uses {$email}.");

            return self::FAILURE;
        }
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            $this->components->info("Two-factor login is already off for {$email}.");

            return self::SUCCESS;
        }

        $reason = trim((string) ($this->option('reason') ?: text('Reason (kept in the audit log)', required: true)));
        if ($reason === '') {
            $this->components->error('A reason is required.');

            return self::FAILURE;
        }
        if (! confirm("Turn off two-factor login for {$user->name} ({$email}) and log them out everywhere?", default: false)) {
            $this->components->warn('Nothing changed.');

            return self::FAILURE;
        }

        $accounts->resetTwoFactor($user, null, $reason);

        $this->components->info("Two-factor login is off for {$email}. They have been emailed and can turn it on again after logging in.");

        return self::SUCCESS;
    }
}
