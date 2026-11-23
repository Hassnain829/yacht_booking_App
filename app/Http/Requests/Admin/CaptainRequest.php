<?php

namespace App\Http\Requests\Admin;

use App\Enums\LicenseType;
use App\Http\Requests\Admin\Concerns\PreparesSlug;
use App\Models\Captain;
use App\Rules\PlainText;
use App\Rules\SafeUpload;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add or edit a captain profile (Admin → Captains, PLAN §6.4, 3.4.6). The email is the captain's own (trip
 * offers and assignments go to it — Phase 8). The license number is stored encrypted and only typed, never
 * shown again: leaving it empty on edit keeps the saved one. An OUPV ("six-pack") license carries at most 6.
 */
class CaptainRequest extends FormRequest
{
    use PreparesSlug;

    public function authorize(): bool
    {
        $captain = $this->route('captain');

        return $captain instanceof Captain
            ? $this->user()->can('update', $captain)
            : $this->user()->can('create', Captain::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareSlug('name');
        $languages = is_string($this->input('languages_text')) ? preg_split('/\s*,\s*/', trim($this->input('languages_text'))) : [];
        $this->merge([
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
            'languages' => array_values(array_filter($languages ?: [], fn (string $language) => $language !== '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $captain = $this->route('captain');
        $ignore = $captain instanceof Captain ? $captain->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:120', new PlainText],
            'slug' => ['required', 'string', 'max:120', self::SLUG_RULE, Rule::unique('captains', 'slug')->ignore($ignore)],
            'email' => ['required', 'string', 'max:254', 'email:rfc,strict', Rule::unique('captains', 'email')->ignore($ignore)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]{7,30}$/'],
            'bio' => ['nullable', 'string', 'max:2000', new PlainText],
            'license_type' => ['required', Rule::enum(LicenseType::class)],
            'license_number' => [$captain instanceof Captain ? 'nullable' : 'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'license_expires_on' => ['required', 'date_format:Y-m-d'],
            'max_passengers_licensed' => ['required', 'integer', 'between:1,150'],
            'years_experience' => ['nullable', 'integer', 'between:0,70'],
            'languages_text' => ['nullable', 'string', 'max:200'],
            'languages' => ['array', 'max:8'],
            'languages.*' => ['string', 'max:30', 'distinct:ignore_case', new PlainText],
            'pay_rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'sort' => ['required', 'integer', 'between:0,9999'],
            'photo' => ['nullable', SafeUpload::image(minWidth: 300, minHeight: 300)],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('license_type') === LicenseType::Oupv->value && (int) $this->input('max_passengers_licensed') > 6) {
                $validator->errors()->add('max_passengers_licensed', 'An OUPV (six-pack) license allows at most 6 passengers.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->slugMessages('captain') + [
            'email.unique' => 'Another captain already uses this email (it may be in the Trash).',
            'phone.regex' => 'Use digits, spaces and + ( ) - only.',
            'license_number.regex' => 'Use letters, numbers, spaces and dashes only.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function captainData(): array
    {
        $data = $this->safe()->except(['languages_text', 'pay_rate', 'photo', 'remove_photo', 'license_number']);
        $data['languages'] = $data['languages'] === [] ? null : $data['languages'];
        $data['pay_rate_cents'] = Money::toCents($this->validated('pay_rate'));
        if (filled($this->validated('license_number'))) {
            $data['license_number'] = strtoupper(trim((string) $this->validated('license_number')));
        }

        return $data;
    }
}
