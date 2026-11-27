<?php

namespace App\Http\Requests\Account;

use App\Models\Booking;
use App\Rules\PlainText;
use App\Support\Booking\CancelReasons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * "Cancel trip" on the trip page (POST /account/trips/{booking}/cancel, PLAN 5.4): why (a fixed list), an optional
 * note and the form's idempotency key. The guest's own trip only (else 404). Never an amount: the refund comes
 * from the booking's policy (CancellationPolicyService), and the admin approves it (D23).
 */
class CancelTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');
        Gate::authorize('cancel', $booking instanceof Booking ? $booking : abort(404));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(array_keys(CancelReasons::OPTIONS))],
            'note' => ['nullable', 'string', 'max:500', new PlainText],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => 'Please choose a reason.', 'reason.in' => 'Please choose a reason.'];
    }

    public function note(): ?string
    {
        $note = trim((string) $this->validated('note'));

        return $note === '' ? null : $note;
    }
}
