<?php

namespace App\Http\Requests\Checkout;

use App\Models\Addon;
use App\Models\CustomerPaymentMethod;
use App\Rules\PlainText;
use App\Services\Payments\PaymentGateway;
use App\Support\Checkout\CheckoutOptions;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Pay" on the checkout page (POST /checkout/{booking}/pay, PLAN 4.5): the final add-ons and gratuity, the lead
 * guest's details, occasion, note and the terms; since 5.3 the guest's saved card to pay with (`card`, one of their
 * own cards of the provider in use) or whether to save the new one (`save_card`). Never a price: the total is
 * QuoteService's. The `idempotent` middleware and the payment's unique key stop a double payment.
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
            'card' => ['nullable', 'string', 'size:26', Rule::exists('customer_payment_methods', 'ulid')->where('user_id', $this->user()->getKey())->where('provider', app(PaymentGateway::class)->name())],
            'save_card' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('card') || $this->savedCard() === null) {
                    return;
                }
                if ($this->savedCard()->isExpired()) {
                    $validator->errors()->add('card', 'This card has expired. Choose another one or use a new card.');
                }
            },
        ];
    }

    /** The guest's own saved card chosen to pay with, if any. */
    public function savedCard(): ?CustomerPaymentMethod
    {
        $ulid = $this->input('card');

        return is_string($ulid) && $ulid !== '' ? CustomerPaymentMethod::query()->where('ulid', strtolower($ulid))->where('user_id', $this->user()->getKey())->first() : null;
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
     * @return array{addons: list<Addon>, gratuity: int, first_name: string, last_name: string, email: string, phone: string, occasion: ?string, notes: ?string, heard: ?string, card: ?CustomerPaymentMethod, save_card: bool}
     */
    public function choices(): array
    {
        $card = $this->savedCard();

        return [
            'card' => $card,
            'save_card' => $card === null && $this->boolean('save_card'),
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
