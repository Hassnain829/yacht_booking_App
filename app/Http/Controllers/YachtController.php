<?php

namespace App\Http\Controllers;

use App\Services\PublicCatalog;
use App\Support\Demo\DemoQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

class YachtController extends Controller
{
    /** Group-size filter: option value => smallest boat capacity that fits (mockup logic). */
    public const GUEST_FILTERS = [6 => 1, 13 => 7, 49 => 14, 80 => 50];

    public const SORTS = ['popular' => 'Most popular', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'guests' => 'Largest groups'];

    /**
     * Fleet page. Filters come from the query string (?type=&guests=&harbor=&sort=) so the page
     * works without JS and links are shareable. Unknown values are ignored, never echoed raw.
     * Every boat is rendered; non-matching ones are `hidden` and the JS re-filters instantly.
     */
    public function index(Request $request, PublicCatalog $catalog): View
    {
        $yachts = $catalog->yachts();
        $harbors = $catalog->harbors();
        $types = $yachts->pluck('category')->unique()->values();

        $filters = [
            'type' => $this->pick($request->query('type'), $types->all(), 'All'),
            'guests' => (int) $this->pick($request->query('guests'), array_map('strval', array_keys(self::GUEST_FILTERS)), '0'),
            'harbor' => $this->pick($request->query('harbor'), $harbors->pluck('slug')->all(), ''),
            'sort' => $this->pick($request->query('sort'), array_keys(self::SORTS), 'popular'),
        ];

        $sorted = $this->sort($yachts, $filters['sort']);
        $matches = fn (Fluent $yacht) => ($filters['type'] === 'All' || $yacht->category === $filters['type'])
            && ($filters['harbor'] === '' || $yacht->harbor?->slug === $filters['harbor'])
            && ($filters['guests'] === 0 || $yacht->max_guests >= self::GUEST_FILTERS[$filters['guests']]);

        return view('pages.yachts.index', [
            'yachts' => $sorted->map(fn (Fluent $yacht) => ['yacht' => $yacht, 'visible' => $matches($yacht)]),
            'compare' => $yachts->sortBy('hourly_rate_cents')->values(),
            'types' => $types->prepend('All'),
            'harbors' => $harbors,
            'filters' => $filters,
            'isFiltered' => $filters !== ['type' => 'All', 'guests' => 0, 'harbor' => '', 'sort' => 'popular'],
        ]);
    }

    /** Yacht detail page. */
    public function show(string $slug, PublicCatalog $catalog): View
    {
        $yacht = $catalog->yacht($slug) ?? abort(404);
        $experiences = $catalog->experiences()->whereIn('slug', $yacht->experiences)->values();
        $quote = DemoQuote::fromSettings();

        // First experience + its shortest duration = the default shown in the booking card.
        $firstExperience = $experiences->first();
        $defaultGuests = min(6, $yacht->max_guests);

        return view('pages.yachts.show', [
            'yacht' => $yacht,
            'experiences' => $experiences->map(fn (Fluent $e) => tap($e, fn () => $e->offered_durations = DemoQuote::durations($yacht, $e))),
            'similar' => $catalog->yachts()->where('slug', '!=', $slug)
                ->sortBy(fn (Fluent $other) => abs($other->max_guests - $yacht->max_guests))->take(3)->values(),
            'sample' => $quote->quote($yacht, $catalog->experience('private'), ['hours' => $yacht->min_hours, 'guests' => min(10, $yacht->max_guests)]),
            'estimate' => $quote->quote($yacht, $firstExperience, ['hours' => DemoQuote::durations($yacht, $firstExperience)[0], 'guests' => $defaultGuests]),
            'defaultGuests' => $defaultGuests,
            'pricing' => $catalog->pricing(),
            'today' => now(config('app.display_timezone'))->toDateString(),
        ]);
    }

    /** Return $value only if it is one of the allowed strings, otherwise the default. */
    private function pick(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    private function sort(Collection $yachts, string $sort): Collection
    {
        $sorted = match ($sort) {
            'price-asc' => $yachts->sortBy('hourly_rate_cents'),
            'price-desc' => $yachts->sortByDesc('hourly_rate_cents'),
            'guests' => $yachts->sortByDesc('max_guests'),
            default => $yachts->sortByDesc('reviews_count'),
        };

        return $sorted->values();
    }
}
