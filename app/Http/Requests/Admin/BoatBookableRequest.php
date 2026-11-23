<?php

namespace App\Http\Requests\Admin;

use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

/** The "Bookable" switch (JSON, from the boats list): on = bookable, off = the Unavailable tag. */
class BoatBookableRequest extends FormRequest
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
            'booking_enabled' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255', new PlainText],
        ];
    }
}
