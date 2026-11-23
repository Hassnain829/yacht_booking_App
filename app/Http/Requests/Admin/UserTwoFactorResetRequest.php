<?php

namespace App\Http\Requests\Admin;

/**
 * Reset someone's two-factor login after they lost their phone and recovery codes (Admin → Users, 3.4.7):
 * a reason + the password; the person is logged out everywhere and told by email. Not for the admin's own
 * account (Profile & security, or `php artisan app:reset-two-factor`).
 */
class UserTwoFactorResetRequest extends SensitiveActionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('user'));
    }
}
