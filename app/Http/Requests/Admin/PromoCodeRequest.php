<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Models\PromoCode;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add or edit a promo code (Admin → Promo codes, PLAN 4.8). The code is letters and digits, kept in upper case.
 * The amount is typed as a percent (1–100) or in dollars, and saved in basis points or cents. Dates are Chicago
 * days: the code works from the start of its first day to the end of its last.
 */
class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $promo = $this->route('promo');

        return $promo instanceof PromoCode
            ? $this->user()->can('update', $promo)
            : $this->user()->can('create', PromoCode::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $promo = $this->route('promo');
        $money = ['nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'];

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/', Rule::unique('promo_codes', 'code')->ignore($promo instanceof PromoCode ? $promo->getKey() : null)],
            'type' => ['required', Rule::enum(PromoType::class)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', $this->input('type') === PromoType::Percent->value ? 'max:100' : 'max:100000'],
            'applies_to' => ['required', Rule::enum(PromoAppliesTo::class)],
            'min_subtotal' => $money,
            'max_discount' => $money,
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', Rule::when(filled($this->input('starts_on')), ['after_or_equal:starts_on'])],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_per_user' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters and numbers only, e.g. SUMMER25.',
            'code.unique' => 'This code is already used (check the Trash too).',
            'amount.max' => $this->input('type') === PromoType::Percent->value ? 'A percentage can be at most 100.' : 'Enter at most $100,000.',
            'amount.gt' => 'Enter more than 0.',
            'ends_on.after_or_equal' => 'The last day must be on or after the first day.',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('type') === PromoType::Fixed->value && filled($this->input('max_discount'))) {
                    $validator->errors()->add('max_discount', 'A cap is only for percentage codes.');
                }
            },
        ];
    }

    /**
     * The fields saved on the promo code.
     *
     * @return array<string, mixed>
     */
    public function promoData(): array
    {
        $tz = config('app.display_timezone');
        $type = PromoType::from((string) $this->validated('type'));
        $day = fn (?string $date, bool $end) => $date === null ? null
            : ($end ? CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->endOfDay() : CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->startOfDay())->utc();

        return [
            'code' => $this->validated('code'),
            'type' => $type,
            'value' => $type === PromoType::Percent ? Money::toBasisPoints($this->validated('amount')) : Money::toCents($this->validated('amount')),
            'applies_to' => PromoAppliesTo::from((string) $this->validated('applies_to')),
            'min_subtotal_cents' => filled($this->validated('min_subtotal')) ? Money::toCents($this->validated('min_subtotal')) : null,
            'max_discount_cents' => $type === PromoType::Percent && filled($this->validated('max_discount')) ? Money::toCents($this->validated('max_discount')) : null,
            'starts_at' => $day($this->validated('starts_on'), false),
            'ends_at' => $day($this->validated('ends_on'), true),
            'max_redemptions' => $this->validated('max_redemptions') === null ? null : (int) $this->validated('max_redemptions'),
            'max_per_user' => $this->validated('max_per_user') === null ? null : (int) $this->validated('max_per_user'),
            'is_active' => (bool) $this->validated('is_active'),
        ];
    }
}
