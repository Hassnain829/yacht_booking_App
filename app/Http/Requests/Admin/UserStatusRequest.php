<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Validation\Rule;

/**
 * Suspend, ban or re-activate an account (Admin → Users, PLAN §4.1, 3.4.7): a reason + the password. Never
 * the admin's own account (UserPolicy::changeStatus). Suspending or banning logs the account out everywhere.
 */
class UserStatusRequest extends SensitiveActionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + ['status' => ['required', Rule::enum(UserStatus::class)]];
    }
}
