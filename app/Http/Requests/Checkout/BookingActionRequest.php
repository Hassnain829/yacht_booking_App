<?php

namespace App\Http\Requests\Checkout;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * An action on the guest's own booking that carries no input (remove the promo code, check the payment's status,
 * come back from the card step). Anyone else's booking: 404.
 */
class BookingActionRequest extends FormRequest
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
        return [];
    }
}
