<?php

namespace App\Http\Requests\Admin;

use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

/** Publish or hide a boat (BoatService::setStatus). The note is required when hiding an owner's boat. */
class BoatStatusRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:publish,hide'],
            'note' => ['nullable', 'string', 'max:500', new PlainText],
        ];
    }
}
