<?php

namespace App\Http\Requests\BookingCard;

use Illuminate\Validation\Rule;

/**
 * GET /yachts/{slug}/availability — either a month's day statuses (`month=2027-06`, the calendar) or one day's
 * start times (`date=2027-06-04`, the start-time grid). Never both. Up to 18 months ahead.
 */
class AvailabilityRequest extends BookingCardRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'month' => ['required_without:date', 'prohibits:date', 'string', Rule::in(self::months())],
            'date' => ['required_without:month', 'string', 'date_format:Y-m-d', 'after_or_equal:'.self::today(), 'before_or_equal:'.self::lastDate()],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'month.*' => 'Pick a month from now up to 18 months ahead.',
            'date.*' => 'Pick a date from today up to 18 months ahead.',
        ];
    }

    /**
     * This month and the next 18, "2027-06" … (the calendar's prev / next buttons stay inside them).
     *
     * @return list<string>
     */
    public static function months(): array
    {
        $first = now(config('app.display_timezone'))->startOfMonth();

        return array_map(fn (int $i) => $first->copy()->addMonths($i)->format('Y-m'), range(0, 18));
    }
}
