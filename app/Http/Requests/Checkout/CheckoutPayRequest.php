<?php

namespace App\Http\Requests\Checkout;

use App\Models\Addon;
use App\Rules\PlainText;
use App\Support\Checkout\CheckoutOptions;
use Illuminate\Validation\Rule;

/**
 * "Pay" on the checkout page (POST /checkout/{booking}/pay, PLAN 4.5): the final add-ons and gratuity, the lead
 * guest's details, occasion, note and the terms. Never a price: the total is QuoteService's. The `idempotent`
 * middleware and the payment's unique key stop a double payment.
 */
class CheckoutPayRequest extends CheckoutPriceRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'first_name' => ['required', 'string', 'max:60', new PlainText],
            'last_name' => ['required', 'string', 'max:60', new PlainText],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]{7,30}$/'],
            'occasion' => ['nullable', 'string', Rule::in(CheckoutOptions::OCCASIONS)],
            'heard' => ['nullable', 'string', Rule::in(CheckoutOptions::HEARD)],
            'notes' => ['nullable', 'string', 'max:1000', new PlainText],
            'agree' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return parent::messages() + [
            'first_name.required' => 'Required',
            'last_name.required' => 'Required',
            'email.email' => 'Enter a valid email',
            'phone.required' => 'Your captain texts this number before the trip.',
            'phone.regex' => 'Enter a valid phone number.',
            'agree.accepted' => 'Please accept the cancellation policy and the waiver to continue.',
        ];
    }

    /**
     * What PrepareCheckoutPayment puts on the booking.
     *
     * @return array{addons: list<Addon>, gratuity: int, first_name: string, last_name: string, email: string, phone: string, occasion: ?string, notes: ?string, heard: ?string}
     */
    public function choices(): array
    {
        return [
            'addons' => $this->addons(),
            'gratuity' => $this->gratuity(),
            'first_name' => trim((string) $this->validated('first_name')),
            'last_name' => trim((string) $this->validated('last_name')),
            'email' => strtolower(trim((string) $this->validated('email'))),
            'phone' => trim((string) $this->validated('phone')),
            'occasion' => $this->validated('occasion'),
            'notes' => $this->validated('notes') === null ? null : trim((string) $this->validated('notes')),
            'heard' => $this->validated('heard'),
        ];
    }
}
