<?php

namespace App\Http\Requests\BookingCard;

use App\Models\Experience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The choices every booking-card call carries (PLAN 4.3, §8.2): a trip length, a guest count and an optional
 * experience. Only choices, never prices. The boat comes from the URL; the controller checks that the choices fit it.
 */
abstract class BookingCardRequest extends FormRequest
{
    /** The public booking card: anyone may ask. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'integer', 'between:1,24'],
            'guests' => ['nullable', 'integer', 'between:1,500'],
            'experience' => ['nullable', 'string', 'max:80', Rule::exists('experiences', 'slug')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'experience.exists' => 'That experience isn\'t offered right now.',
            'hours.*' => 'Choose a trip length.',
            'guests.*' => 'Enter the number of guests.',
        ];
    }

    public function hours(): int
    {
        return (int) $this->validated('hours');
    }

    public function guests(): int
    {
        return (int) ($this->validated('guests') ?? 1);
    }

    public function experience(): ?Experience
    {
        $slug = $this->validated('experience');

        return $slug === null ? null : Experience::query()->where('slug', $slug)->first();
    }

    /** Today in Chicago, "2027-06-04". */
    protected static function today(): string
    {
        return now(config('app.display_timezone'))->toDateString();
    }

    /** The last date that can be booked (18 months ahead). */
    protected static function lastDate(): string
    {
        return now(config('app.display_timezone'))->addMonths(18)->toDateString();
    }
}
