<?php

namespace App\Actions\Fortify;

use App\Enums\LoginEventType;
use App\Models\User;
use App\Notifications\EmailChanged;
use App\Notifications\SecurityContext;
use App\Services\LoginEventService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * "Your details" on Profile & security (PUT /user/profile-information).
 *
 * Changing the email moves the whole account, so (PLAN §8.1):
 * - it needs the current password,
 * - the new address must be verified again (until then the owner area and listing a boat stay closed),
 * - the OLD address gets an email about it, and the change is in the security log.
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(private LoginEventService $log) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $changingEmail = Str::lower(trim((string) ($input['email'] ?? ''))) !== $user->email;

        $data = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'marketing' => ['nullable', 'boolean'],
            'current_password' => [Rule::requiredIf($changingEmail), 'nullable', 'string', 'max:128', 'current_password:web'],
        ], [
            'current_password.required' => 'Enter your current password to change your email.',
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updateProfileInformation');

        $user->forceFill([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => isset($data['phone']) && trim($data['phone']) !== '' ? trim($data['phone']) : null,
            'marketing_opt_in' => (bool) ($data['marketing'] ?? false),
        ]);

        if (! $changingEmail) {
            $user->save();

            return;
        }

        $oldEmail = $user->email;
        $user->forceFill(['email' => Str::lower(trim($data['email'])), 'email_verified_at' => null])->save();

        $user->sendEmailVerificationNotification();
        $this->log->record(LoginEventType::EmailChanged, $user);
        Notification::route('mail', $oldEmail)->notify(new EmailChanged($user->email, SecurityContext::fromRequest()));
    }
}
