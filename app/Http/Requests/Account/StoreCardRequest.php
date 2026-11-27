<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A finished "Add a card" (5.3): the id of the Stripe SetupIntent the browser confirmed — posted by the page, or in
 * Stripe's return URL after a bank check. The server asks Stripe for it; the browser's word isn't trusted.
 */
class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'setup_intent' => ['required', 'string', 'max:100', 'regex:/^seti_[A-Za-z0-9_]+$/'],
        ];
    }

    public function setupId(): string
    {
        return (string) $this->validated('setup_intent');
    }
}
