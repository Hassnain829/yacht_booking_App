<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * "Your details" on /account (switched on in Phase 2.5). A new email address must be verified again.
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $data = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'marketing' => ['nullable', 'boolean'],
        ])->validateWithBag('updateProfileInformation');

        $email = Str::lower($data['email']);
        $user->forceFill([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'marketing_opt_in' => (bool) ($data['marketing'] ?? false),
        ]);

        if ($email !== $user->email && $user instanceof MustVerifyEmail) {
            $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();

            return;
        }

        $user->save();
    }
}
