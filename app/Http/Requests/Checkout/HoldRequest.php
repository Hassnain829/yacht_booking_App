<?php

namespace App\Http\Requests\Checkout;

/**
 * "Book now" for a signed-in guest (POST /checkout, PLAN 4.5): the trip's choices plus the form's idempotency key,
 * which becomes the booking's `hold_key` (one hold per click, `idempotent` middleware too).
 */
class HoldRequest extends TripChoicesRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{16,100}$/'],
        ];
    }
}
