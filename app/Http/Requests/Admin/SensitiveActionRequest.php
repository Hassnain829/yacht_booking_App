<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * A sensitive admin action (PLAN §8.2 rule 15): a written reason (saved in the audit log) and the admin's
 * password. The password is typed in the same dialog, so nothing is lost on the way. It is skipped when it
 * was confirmed in the last `auth.password_timeout` seconds — the same window and session key as Laravel's
 * `password.confirm` — and a correct password counts as a fresh confirmation.
 *
 * Errors go to the "sensitive" error bag; the hidden `dialog` field tells the page which dialog to reopen.
 */
abstract class SensitiveActionRequest extends FormRequest
{
    protected $errorBag = 'sensitive';

    /** Asked for when the last password confirmation is older than the timeout. */
    public static function needsPassword(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        return Date::now()->unix() - $confirmedAt > (int) config('auth.password_timeout', 10800);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'password' => [Rule::requiredIf(self::needsPassword()), 'nullable', 'string', 'max:128', 'current_password:web'],
            'dialog' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Please write why you are doing this. It is saved in the audit log.',
            'reason.min' => 'Please write a little more (at least 10 characters).',
            'password.required' => 'Please enter your password to confirm.',
            'password.current_password' => 'That password is not correct.',
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    protected function passedValidation(): void
    {
        if (filled($this->input('password'))) {
            $this->session()->passwordConfirmed();
        }
    }
}
