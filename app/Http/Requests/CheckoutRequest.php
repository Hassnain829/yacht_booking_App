<?php

namespace App\Http\Requests;

use App\Services\PublicCatalog;
use App\Support\Demo\BookingSelection;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Checkout form (Phase 1 demo: no payment is taken).
 *
 * Card numbers are NEVER posted: the card inputs have no `name`, only the brand + last 4 digits are
 * sent (what Stripe returns in Phase 5). Prices are never accepted — the server recalculates them.
 */
class CheckoutRequest extends FormRequest
{
    public const HEARD = ['Google', 'Instagram', 'Friend / referral', 'Returning guest', 'Other'];

    public const CARD_BRANDS = ['Visa', 'Mastercard', 'Amex', 'Discover', 'Card'];

    /** Phase 1: anyone may check out. Phase 4: the booking hold must belong to the logged-in user. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/', function (string $attribute, mixed $value, Closure $fail) {
                if (strlen(preg_replace('/\D/', '', (string) $value)) < 10) {
                    $fail('Enter a valid phone number.');
                }
            }],
            'occasion' => ['nullable', Rule::in(BookingSelection::OCCASIONS)],
            'heard' => ['nullable', Rule::in(self::HEARD)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'gratuity' => ['required', 'integer', Rule::in(config('demo.pricing.gratuity_options'))],
            'plan' => ['required', Rule::in(['deposit', 'full'])],
            'promo' => ['nullable', 'string', 'max:30', 'alpha_num:ascii'],
            'agree' => ['accepted'],
            'marketing' => ['nullable', 'boolean'],
            'card_brand' => ['required', Rule::in(self::CARD_BRANDS)],
            'card_last4' => ['required', 'digits:4'],

            // The booking itself (hidden fields) — re-checked against the catalog in after().
            'experience' => ['required', 'string', 'max:40'],
            'yacht' => ['required', 'string', 'max:40'],
            'guests' => ['required', 'integer', 'between:1,'.BookingSelection::MAX_GUESTS],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'integer', 'between:1,12'],
            'addons' => ['nullable', 'array', 'max:'.BookingSelection::MAX_ADDONS],
            'addons.*' => ['string', 'max:40', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'agree.accepted' => 'Please accept the cancellation policy and waiver to continue.',
            'first_name.required' => 'Required',
            'last_name.required' => 'Required',
            'email.email' => 'Enter a valid email',
        ];
    }

    /** The submitted booking must still be a complete, valid selection — exactly as posted. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $selection = $this->selection();
                $posted = $this->only(['experience', 'yacht', 'date', 'time']);
                $same = $selection->isComplete()
                    && $selection->experience === $posted['experience']
                    && $selection->yacht === $posted['yacht']
                    && $selection->date === $posted['date']
                    && $selection->time === $posted['time']
                    && $selection->guests === (int) $this->input('guests')
                    && $selection->hours === (int) $this->input('hours')
                    && count($selection->addons) === count((array) $this->input('addons', []));
                if (! $same) {
                    $validator->errors()->add('booking', 'This booking is no longer available. Please go back and choose again.');
                }
            },
        ];
    }

    public function selection(): BookingSelection
    {
        return BookingSelection::from($this->only(['experience', 'yacht', 'guests', 'date', 'time', 'hours', 'addons', 'occasion']), app(PublicCatalog::class));
    }
}
