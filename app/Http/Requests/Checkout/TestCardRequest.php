<?php

namespace App\Http\Requests\Checkout;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The test card form of the fake gateway (local development and tests only — the route answers 404 with Stripe or
 * in production). The browser sends only the card's brand, last 4 digits, expiry and whether it is the decline
 * test card; the number itself never leaves the page. Paying with a saved card, the saved card's details are used.
 */
class TestCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');
        Gate::authorize('pay', $booking instanceof Booking ? $booking : abort(404));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', Rule::in(['Visa', 'Mastercard', 'Amex', 'Discover', 'Card'])],
            'last4' => ['required', 'string', 'digits:4'],
            'decline' => ['required', 'boolean'],
            'exp_month' => ['nullable', 'integer', 'between:1,12'],
            'exp_year' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }
}
