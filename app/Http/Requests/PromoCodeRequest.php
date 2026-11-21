<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** POST /checkout/promo — checks one promo code (codes are never sent to the browser in bulk). */
class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'alpha_num:ascii'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.*' => 'That promo code is not valid.',
        ];
    }
}
