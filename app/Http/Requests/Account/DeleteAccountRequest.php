<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/** "Delete my account" on Profile & security: only your own account (never the admin's), with your password. */
class DeleteAccountRequest extends FormRequest
{
    /** Errors show inside the delete dialog, not on the other forms of the page. */
    protected $errorBag = 'deleteAccount';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('delete', $user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:128', 'current_password:web'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.required' => 'Enter your password to delete your account.',
            'password.current_password' => __('The provided password does not match your current password.'),
        ];
    }
}
