<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\SessionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * "Password" on Profile & security (PUT /user/password). Needs the current password. A new password logs
 * every other device out (PLAN §8.1); this browser stays logged in. The "password changed" email and the
 * log entry come from SecurityEventSubscriber (Fortify's PasswordUpdatedViaController event).
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private SessionService $sessions) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $data = Validator::make($input, [
            'current_password' => ['required', 'string', 'max:128', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill(['password' => $data['password']])->save(); // hashed by the model cast

        $this->sessions->logOutOtherDevices($user, request());
    }
}
