<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Services\AvailabilityService;
use App\Services\BoatQuotes;
use App\Services\PublicCatalog;
use App\Support\FleetFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

class YachtController extends Controller
{
    /**
     * Fleet page (4.4, D21): a Boatsetter-style filter bar in our design — date, guests, experience, length,
     * type, sort and "More filters" (harbor, price, boat length, amenities). Filters come from the query string
     * (App\Support\FleetFilters, whitelisted), so the page works without JS and links can be shared. Every boat
     * is rendered; non-matching ones are `hidden` and the JS re-filters instantly. With a date, only boats with a
     * free start that day are shown ("Free on Jun 4"), from AvailabilityService. No map.
     */
    public function index(Request $request, PublicCatalog $catalog, BoatQuotes $quotes, AvailabilityService $availability): View
    {
        $yachts = $catalog->yachts();
        $harbors = $catalog->harbors();
        $experiences = $catalog->experiences();
        $types = $yachts->pluck('category')->unique()->values();
        $amenities = $catalog->amenityFilters();

        $filters = FleetFilters::from($request, $types->all(), $harbors->pluck('slug')->all(), $experiences->pluck('slug')->all(), $amenities->pluck('ulid')->all());
        $date = $filters->values['date'];

        $rows = $filters->sort($yachts)->map(function (Fluent $yacht) use ($filters, $quotes, $availability, $date) {
            $visible = $filters->matches($yacht);
            $free = null;
            if ($date !== '' && $visible) {
                $free = $this->freeStarts($yacht, $filters, $quotes, $availability, $date);
                $visible = $free > 0;
            }

            return ['yacht' => $yacht, 'visible' => $visible, 'free' => $free];
        });

        return view('pages.yachts.index', [
            'yachts' => $rows,
            'compare' => $yachts->sortBy('hourly_rate_cents')->values(),
            'types' => $types->prepend('All'),
            'harbors' => $harbors,
            'experiences' => $experiences,
            'amenities' => $amenities,
            'filters' => $filters->values,
            'moreCount' => $filters->moreCount(),
            'isFiltered' => $filters->isFiltered(),
            'bookingQuery' => $filters->bookingQuery(),
            'today' => now(config('app.display_timezone'))->toDateString(),
            'lastDate' => now(config('app.display_timezone'))->addMonths(18)->toDateString(),
        ]);
    }

    /**
     * How many start times are free on the date, for the chosen length (or the boat's shortest), experience and
     * guests. A boat with the Unavailable tag, or one that can't take those choices, has none.
     */
    private function freeStarts(Fluent $yacht, FleetFilters $filters, BoatQuotes $quotes, AvailabilityService $availability, string $date): int
    {
        $boat = $quotes->find($yacht->slug);
        $lengths = $filters->lengthsOf($yacht);
        if ($boat === null || ! $boat->isBookable() || $lengths === []) {
            return 0;
        }
        $v = $filters->values;
        $experience = $v['experience'] !== '' ? $boat->experiences->firstWhere('slug', $v['experience']) : null;
        $hours = in_array($v['hours'], $lengths, true) ? $v['hours'] : $lengths[0];
        $guests = max(1, min((int) $yacht->max_guests, $v['guests'] ?: 1));

        return count(array_filter($availability->slots($boat, $date, $hours, $experience, $guests), fn (array $slot) => $slot['available']));
    }

    /**
     * Yacht detail page = the booking page (4.3, D21). The booking card (resources/js/pages/yacht.js) asks the
     * availability and quote endpoints as the guest picks; the first price is rendered here so it never flashes
     * empty. `?experience=&date=&hours=&guests=&addons=` (from the fleet, an experience, a pricing package or an
     * old /book link) preset the card; values that don't fit the boat are dropped. An experience only narrows the
     * start times to its departure window; it is free and its extras are optional (user decisions 2026-11-25).
     */
    public function show(Request $request, string $slug, PublicCatalog $catalog, BoatQuotes $quotes): View
    {
        $yacht = $catalog->yacht($slug) ?? abort(404);
        $boat = $quotes->find($slug) ?? abort(404);
        $experiences = $catalog->experiences()->whereIn('slug', $yacht->experiences)->values()
            ->map(function (Fluent $experience) {
                $experience->extras_label = $experience->extras === [] ? 'Free' : 'Free · optional extras at checkout';

                return $experience;
            });

        $initial = $this->initialChoices($request, $yacht, $experiences, $catalog);
        $experience = $initial['experience'] ? $boat->experiences->firstWhere('slug', $initial['experience']) : null;
        $addons = Addon::query()->active()->whereIn('slug', $initial['addons'])->get()->sortBy(fn (Addon $addon) => array_search($addon->slug, $initial['addons'], true))->values()->all();
        $estimate = rescue(fn () => $quotes->quote($boat, $experience, $initial['date'], null, $initial['hours'], $initial['guests'], $addons), report: false);
        $sampleGuests = min(10, (int) $yacht->max_guests);

        return view('pages.yachts.show', [
            'yacht' => $yacht,
            'experiences' => $experiences,
            'similar' => $catalog->yachts()->where('slug', '!=', $slug)
                ->sortBy(fn (Fluent $other) => abs($other->max_guests - $yacht->max_guests))->take(3)->values(),
            'sample' => rescue(fn () => $quotes->quote($boat, null, null, null, $yacht->lengths[0], $sampleGuests), report: false),
            'sampleGuests' => $sampleGuests,
            'estimate' => $estimate,
            'initial' => $initial,
            'pricing' => $catalog->pricing(),
            'today' => now(config('app.display_timezone'))->toDateString(),
        ]);
    }

    /**
     * The card's starting choices from the query string, checked against the boat and the catalog. Add-ons (a
     * pricing package's) are carried to checkout, where the guest can untick them.
     *
     * @param  Collection<int, Fluent>  $experiences
     * @return array{experience: ?string, date: ?string, hours: int, guests: int, addons: list<string>}
     */
    private function initialChoices(Request $request, Fluent $yacht, Collection $experiences, PublicCatalog $catalog): array
    {
        $experience = $experiences->firstWhere('slug', $request->query('experience'));
        $lengths = $yacht->lengths;
        $addons = $request->query('addons');
        $addons = is_string($addons) ? explode(',', $addons) : (is_array($addons) ? $addons : []);
        $addons = array_values(array_unique(array_intersect(array_filter($addons, 'is_string'), $catalog->addons()->pluck('slug')->all())));
        $hours = filter_var($request->query('hours'), FILTER_VALIDATE_INT);
        $guests = filter_var($request->query('guests'), FILTER_VALIDATE_INT);
        $date = $request->query('date');
        $today = now(config('app.display_timezone'))->toDateString();
        $last = now(config('app.display_timezone'))->addMonths(18)->toDateString();
        $validDate = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            && $date >= $today && $date <= $last;

        return [
            'experience' => $experience?->slug,
            'date' => $validDate ? $date : null,
            'hours' => in_array($hours, $lengths, true) ? $hours : $lengths[0],
            'guests' => is_int($guests) ? max(1, min((int) $yacht->max_guests, $guests)) : min(6, (int) $yacht->max_guests),
            'addons' => array_slice($addons, 0, 10),
        ];
    }
}
