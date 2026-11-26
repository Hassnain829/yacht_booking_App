<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use Illuminate\Validation\Rule;

/**
 * Change a booking's status from the admin (PLAN 4.8): a reason + the password (SensitiveActionRequest). Only the
 * moves BookingService::adminTargets() allows now — let a hold go, completed, no-show. Never cancel here: that
 * comes with its refund in Phase 5.
 */
class BookingStatusRequest extends SensitiveActionRequest
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
        return parent::rules() + [
            'status' => ['required', 'string', Rule::in([BookingStatus::Expired->value, BookingStatus::Completed->value, BookingStatus::NoShow->value])],
        ];
    }

    public function status(): BookingStatus
    {
        return BookingStatus::from((string) $this->validated('status'));
    }
}
