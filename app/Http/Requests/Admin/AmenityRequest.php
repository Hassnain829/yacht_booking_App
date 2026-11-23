<?php

namespace App\Http\Requests\Admin;

use App\Enums\AmenityGroup;
use App\Models\Amenity;
use App\Rules\PlainText;
use App\Support\IconNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add or edit an amenity (Admin → Amenities, PLAN 3.4.5). A name is unique within its group. */
class AmenityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $amenity = $this->route('amenity');

        return $amenity instanceof Amenity
            ? $this->user()->can('update', $amenity)
            : $this->user()->can('create', Amenity::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $amenity = $this->route('amenity');

        return [
            'name' => ['required', 'string', 'max:80', new PlainText, Rule::unique('amenities', 'name')->where('group', $this->input('group'))->ignore($amenity instanceof Amenity ? $amenity->getKey() : null)],
            'group' => ['required', Rule::enum(AmenityGroup::class)->only(AmenityGroup::forms())],
            'icon' => ['nullable', 'string', Rule::in(IconNames::PICKER)],
            'sort' => ['required', 'integer', 'between:0,9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'This list already has an item with that name.'];
    }
}
