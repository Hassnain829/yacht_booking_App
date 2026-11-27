<?php

namespace App\Http\Requests\Account;

use App\Support\Payments\SavedCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Add a card" with the fake gateway (local development and tests only — 404 with Stripe or in production): only
 * the test card's brand, last 4 digits and expiry are sent; the number never leaves the page.
 */
class TestCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', Rule::in(['Visa', 'Mastercard', 'Amex', 'Discover', 'Card'])],
            'last4' => ['required', 'string', 'digits:4'],
            'exp_month' => ['required', 'integer', 'between:1,12'],
            'exp_year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $now = Date::now(config('app.display_timezone'));
                if ((int) $this->input('exp_year') * 12 + (int) $this->input('exp_month') < $now->year * 12 + $now->month) {
                    $validator->errors()->add('exp_month', 'This card has expired.');
                }
            },
        ];
    }

    public function card(string $paymentMethodId): SavedCard
    {
        return new SavedCard($paymentMethodId, strtolower((string) $this->validated('brand')), (string) $this->validated('last4'), (int) $this->validated('exp_month'), (int) $this->validated('exp_year'));
    }
}
