<?php

namespace App\Http\Requests\BookingCard;

use App\Models\Addon;
use App\Support\StartTimes;
use Illuminate\Validation\Rule;

/**
 * GET /yachts/{slug}/quote — the live price on the booking card and the pricing page's estimator. Without a date
 * it prices a weekday (marked as an estimate); without a start time, that day. Add-ons are optional extras the
 * guest picks; the experience's own paid extras are added by QuoteService.
 */
class PriceRequest extends BookingCardRequest
{
    public const MAX_ADDONS = 10;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'guests' => ['required', 'integer', 'between:1,500'],
            'date' => ['nullable', 'string', 'date_format:Y-m-d', 'after_or_equal:'.self::today(), 'before_or_equal:'.self::lastDate()],
            'time' => ['nullable', 'string', Rule::in(StartTimes::grid())],
            'addons' => ['nullable', 'array', 'max:'.self::MAX_ADDONS],
            'addons.*' => ['string', 'max:80', 'distinct', Rule::exists('addons', 'slug')->where('is_active', true)->whereNull('deleted_at')],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'date.*' => 'Pick a date from today up to 18 months ahead.',
            'time.*' => 'Pick one of the start times shown.',
            'addons.max' => 'Pick at most '.self::MAX_ADDONS.' add-ons.',
            'addons.*' => 'One of the add-ons is no longer offered. Remove it and try again.',
        ];
    }

    public function tripDate(): ?string
    {
        return $this->validated('date');
    }

    public function tripTime(): ?string
    {
        return $this->validated('date') === null ? null : $this->validated('time');
    }

    /** @return list<Addon> in the order picked */
    public function addons(): array
    {
        $slugs = array_values($this->validated('addons') ?? []);
        $found = Addon::query()->whereIn('slug', $slugs)->get()->keyBy('slug');

        return array_values(array_filter(array_map(fn (string $slug) => $found[$slug] ?? null, $slugs)));
    }
}
