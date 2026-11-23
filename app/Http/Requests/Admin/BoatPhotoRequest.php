<?php

namespace App\Http\Requests\Admin;

use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

/** Change one boat photo: its alt text (what screen readers say) and / or its place in the gallery. */
class BoatPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('boat'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:160', new PlainText],
            'move' => ['nullable', 'string', 'in:first,up,down'],
        ];
    }
}
