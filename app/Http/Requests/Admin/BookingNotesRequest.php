<?php

namespace App\Http\Requests\Admin;

use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The admin's notes on a booking (Admin → Bookings, PLAN 4.8): an internal note and a note for the captain (shown
 * to the captain from Phase 8 — never the guest's personal details). Plain text only; audit-logged.
 */
class BookingNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-bookings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_notes' => ['nullable', 'string', 'max:2000', new PlainText],
            'captain_notes' => ['nullable', 'string', 'max:1000', new PlainText],
        ];
    }

    /** @return array{admin_notes: ?string, captain_notes: ?string} */
    public function notes(): array
    {
        $clean = fn (?string $value) => $value === null || trim($value) === '' ? null : trim($value);

        return [
            'admin_notes' => $clean($this->validated('admin_notes')),
            'captain_notes' => $clean($this->validated('captain_notes')),
        ];
    }
}
