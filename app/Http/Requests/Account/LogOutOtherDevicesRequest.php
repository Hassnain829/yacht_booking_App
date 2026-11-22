<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/** "Log out other devices" on Profile & security. No input: it always acts on the signed-in account. */
class LogOutOtherDevicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('update', $user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
