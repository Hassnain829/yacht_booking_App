<?php

namespace App\Http\Requests\Checkout;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Apply a promo code to a held booking (POST /checkout/{booking}/promo, `throttle:promo` stops guessing). The
 * guest's own booking only (else 404). PromoService decides whether the code may be used.
 */
class ApplyPromoRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.regex' => 'That promo code is not valid.', 'code.required' => 'Enter a promo code.'];
    }
}
