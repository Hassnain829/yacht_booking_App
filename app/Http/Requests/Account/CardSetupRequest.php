<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Add a card" on Profile & security (5.3): start saving a card for the signed-in guest (a Stripe SetupIntent).
 * No input: the card is always added to the signed-in account.
 */
class CardSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
