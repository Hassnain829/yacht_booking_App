<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\SessionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * "Choose a new password" from the reset email. The new password logs the account out everywhere:
 * every stored session is deleted and the remember-me token is rotated (PLAN §8.1).
 * The reset token itself is single-use and expires after 60 minutes (config/auth.php).
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private SessionService $sessions) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        $data = Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill(['password' => $data['password']])->save(); // hashed by the model cast

        $this->sessions->logOutEverywhere($user); // the "password changed" email + log entry: SecurityEventSubscriber
    }
}
