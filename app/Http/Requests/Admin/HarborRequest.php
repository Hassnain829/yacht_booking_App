<?php

namespace App\Http\Requests\Admin;

use App\Models\Harbor;
use App\Rules\PlainText;
use App\Rules\SafeUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Add or edit a harbor (Admin → Harbors, PLAN 3.4.1). The web address (slug) is made from the name when it
 * is left empty; it must be unique across every harbor, including the ones in the Trash (the database
 * index counts them too). Saving the same new harbor twice therefore fails the second time.
 */
class HarborRequest extends FormRequest
{
    public function authorize(): bool
    {
        $harbor = $this->route('harbor');

        return $harbor instanceof Harbor
            ? $this->user()->can('update', $harbor)
            : $this->user()->can('create', Harbor::class);
    }

    protected function prepareForValidation(): void
    {
        $slug = is_string($this->input('slug')) ? trim($this->input('slug')) : '';
        $name = is_string($this->input('name')) ? $this->input('name') : '';

        $this->merge(['slug' => $slug !== '' ? Str::lower($slug) : Str::slug($name)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $harbor = $this->route('harbor');

        return [
            'name' => ['required', 'string', 'max:120', new PlainText],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('harbors', 'slug')->ignore($harbor instanceof Harbor ? $harbor->getKey() : null)],
            'area' => ['nullable', 'string', 'max:120', new PlainText],
            'address' => ['nullable', 'string', 'max:255', new PlainText],
            'map_query' => ['nullable', 'string', 'max:255', new PlainText],
            'notes' => ['nullable', 'string', 'max:1000', new PlainText],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90', 'decimal:0,6'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180', 'decimal:0,6'],
            'is_active' => ['required', 'boolean'],
            'sort' => ['required', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', SafeUpload::image()],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Another harbor already uses this web address (it may be in the Trash). Choose a different name or address.',
            'slug.regex' => 'Use lowercase letters, numbers and single dashes only, e.g. "navy-pier".',
            'slug.required' => 'Please enter a name, so the web address can be made from it.',
            'lat.required_with' => 'Enter both latitude and longitude, or neither.',
            'lng.required_with' => 'Enter both latitude and longitude, or neither.',
        ];
    }

    /**
     * The fields saved on the harbor (the image is handled by HarborService).
     *
     * @return array<string, mixed>
     */
    public function harborData(): array
    {
        return $this->safe()->except(['image', 'remove_image']);
    }
}
