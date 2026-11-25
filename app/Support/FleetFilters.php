<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * The fleet page's filters (PLAN 4.4, D21: Boatsetter-style search, our design), read from the query string so
 * filtered views work without JS and can be shared. Every value is checked against a whitelist; anything else is
 * dropped, never echoed. The same rules run in resources/js/pages/yachts.js for instant filtering (all but the
 * date, which needs the availability calendar and reloads the page).
 */
final class FleetFilters
{
    public const SORTS = ['popular' => 'Most popular', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'guests' => 'Largest groups'];

    /** Guest counts offered in the select (a boat must take at least that many). */
    public const GUESTS = [2, 4, 6, 8, 10, 13, 20, 30, 49, 80];

    public const HOURS = [2, 3, 4, 5, 6, 8];

    /** Hourly rate ranges in cents: key => [min, max) (null = open). */
    public const PRICES = ['under-500' => ['Under $500 / hr', null, 50000], '500-1000' => ['$500 – $1,000 / hr', 50000, 100000], 'over-1000' => ['$1,000+ / hr', 100000, null]];

    /** Lengths in feet: key => [label, min, max). */
    public const LENGTHS = ['under-40' => ['Under 40 ft', null, 40], '40-60' => ['40 – 60 ft', 40, 60], 'over-60' => ['60 ft +', 60, null]];

    public const MAX_AMENITIES = 10;

    /** The defaults: no filter. */
    public const NONE = ['type' => 'All', 'date' => '', 'guests' => 0, 'experience' => '', 'hours' => 0, 'harbor' => '', 'price' => '', 'length' => '', 'amenities' => [], 'sort' => 'popular'];

    /**
     * @param  array{type: string, date: string, guests: int, experience: string, hours: int, harbor: string, price: string, length: string, amenities: list<string>, sort: string}  $values
     */
    private function __construct(public readonly array $values) {}

    /**
     * @param  list<string>  $types  the fleet's categories
     * @param  list<string>  $harbors  harbor slugs
     * @param  list<string>  $experiences  experience slugs
     * @param  list<string>  $amenities  amenity ulids offered in "More filters"
     */
    public static function from(Request $request, array $types, array $harbors, array $experiences, array $amenities): self
    {
        $pick = fn (string $key, array $allowed, mixed $default) => is_string($value = $request->query($key)) && in_array($value, $allowed, true) ? $value : $default;
        $number = fn (string $key, array $allowed) => in_array($value = filter_var($request->query($key), FILTER_VALIDATE_INT), $allowed, true) ? $value : 0;
        $chosen = $request->query('amenities');

        return new self([
            'type' => $pick('type', $types, 'All'),
            'date' => self::date($request->query('date')),
            'guests' => self::guests($request->query('guests')),
            'experience' => $pick('experience', $experiences, ''),
            'hours' => $number('hours', self::HOURS),
            'harbor' => $pick('harbor', $harbors, ''),
            'price' => $pick('price', array_keys(self::PRICES), ''),
            'length' => $pick('length', array_keys(self::LENGTHS), ''),
            'amenities' => is_array($chosen) ? array_slice(array_values(array_unique(array_intersect(array_filter($chosen, 'is_string'), $amenities))), 0, self::MAX_AMENITIES) : [],
            'sort' => $pick('sort', array_keys(self::SORTS), 'popular'),
        ]);
    }

    /** A real date from today up to 18 months ahead (Chicago time), else ''. */
    private static function date(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            return '';
        }
        $today = CarbonImmutable::now(config('app.display_timezone'));

        return $value >= $today->toDateString() && $value <= $today->addMonths(18)->toDateString() ? $value : '';
    }

    /** Any whole number of guests from 1 to 500 (the home page search sends its own counts), else 0 = any. */
    private static function guests(mixed $value): int
    {
        $guests = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);

        return $guests === false ? 0 : $guests;
    }

    public function isFiltered(): bool
    {
        return $this->values !== self::NONE;
    }

    /** How many of the "More filters" are on (shown on its button). */
    public function moreCount(): int
    {
        return count(array_filter([$this->values['harbor'], $this->values['price'], $this->values['length']])) + count($this->values['amenities']);
    }

    /** The lengths a yacht offers (the same with any experience, 2026-11-25). @return list<int> */
    public function lengthsOf(Fluent $yacht): array
    {
        return $yacht->lengths ?? [];
    }

    /** Everything but the date (that needs AvailabilityService; see YachtController). */
    public function matches(Fluent $yacht): bool
    {
        $v = $this->values;
        $in = fn (?int $value, ?int $min, ?int $max) => $value !== null && ($min === null || $value >= $min) && ($max === null || $value < $max);

        return ($v['type'] === 'All' || $yacht->category === $v['type'])
            && ($v['guests'] === 0 || $yacht->max_guests >= $v['guests'])
            && ($v['experience'] === '' || in_array($v['experience'], $yacht->experiences, true))
            && ($v['hours'] === 0 || in_array($v['hours'], $this->lengthsOf($yacht), true))
            && ($v['harbor'] === '' || $yacht->harbor?->slug === $v['harbor'])
            && ($v['price'] === '' || $in((int) $yacht->hourly_rate_cents, self::PRICES[$v['price']][1], self::PRICES[$v['price']][2]))
            && ($v['length'] === '' || $in($yacht->length_ft === null ? null : (int) $yacht->length_ft, self::LENGTHS[$v['length']][1], self::LENGTHS[$v['length']][2]))
            && array_diff($v['amenities'], $yacht->amenity_ids ?? []) === [];
    }

    /**
     * @param  Collection<int, Fluent>  $yachts
     * @return Collection<int, Fluent>
     */
    public function sort(Collection $yachts): Collection
    {
        $sorted = match ($this->values['sort']) {
            'price-asc' => $yachts->sortBy('hourly_rate_cents'),
            'price-desc' => $yachts->sortByDesc('hourly_rate_cents'),
            'guests' => $yachts->sortByDesc('max_guests'),
            default => $yachts->sortByDesc('reviews_count'),
        };

        return $sorted->values();
    }

    /** What a card's "Book" link carries to the boat page's booking card. @return array<string, int|string> */
    public function bookingQuery(): array
    {
        return array_filter([
            'date' => $this->values['date'],
            'guests' => $this->values['guests'] ?: null,
            'experience' => $this->values['experience'],
            'hours' => $this->values['hours'] ?: null,
        ]);
    }
}
