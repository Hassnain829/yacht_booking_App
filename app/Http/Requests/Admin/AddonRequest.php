<?php

namespace App\Http\Requests\Admin;

use App\Enums\AddonUnit;
use App\Http\Requests\Admin\Concerns\PreparesSlug;
use App\Models\Addon;
use App\Rules\PlainText;
use App\Support\IconNames;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add or edit an add-on (Admin → Add-ons, PLAN 3.4.2). The price is typed in dollars and saved in cents. */
class AddonRequest extends FormRequest
{
    use PreparesSlug;

    public function authorize(): bool
    {
        $addon = $this->route('addon');

        return $addon instanceof Addon
            ? $this->user()->can('update', $addon)
            : $this->user()->can('create', Addon::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareSlug('name');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $addon = $this->route('addon');

        return [
            'name' => ['required', 'string', 'max:120', new PlainText],
            'slug' => ['required', 'string', 'max:80', self::SLUG_RULE, Rule::unique('addons', 'slug')->ignore($addon instanceof Addon ? $addon->getKey() : null)],
            'description' => ['nullable', 'string', 'max:255', new PlainText],
            'icon' => ['required', 'string', Rule::in(IconNames::PICKER)],
            'price' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'unit' => ['required', Rule::enum(AddonUnit::class)],
            'max_quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'is_active' => ['required', 'boolean'],
            'sort' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->slugMessages('add-on');
    }

    /**
     * @return array<string, mixed>
     */
    public function addonData(): array
    {
        return array_merge($this->safe()->except(['price']), ['price_cents' => Money::toCents($this->validated('price'))]);
    }
}
