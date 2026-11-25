<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Services\BoatQuotes;
use App\Services\PublicCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/** Content pages: pricing, about, contact. */
class PageController extends Controller
{
    /**
     * Pricing page. Every price on it comes from QuoteService (via BoatQuotes, since 4.3): the example packages,
     * the "starting at" column and the estimator's first answer; the estimator then asks the quote endpoint.
     */
    public function pricing(PublicCatalog $catalog, BoatQuotes $quotes): View
    {
        $yachts = $catalog->yachts();
        $experiences = $catalog->experiences();
        $addons = $catalog->addons();
        $pricing = $catalog->pricing();
        $dates = ['weekday' => $quotes->weekday(), 'weekend' => $this->nextWeekend()];

        // A package whose boat, experience or add-on is hidden / in the Trash, or no longer fits, is left out.
        $packages = collect([
            ['title' => 'Sunset for Two', 'yacht' => 'windward', 'experience' => 'sunset', 'hours' => 2, 'guests' => 2, 'weekend' => false, 'addons' => ['champagne'], 'perks' => ['2-hour sunset sail', 'Licensed captain', 'Champagne toast', 'Blankets & ice'], 'featured' => false],
            ['title' => 'Playpen Party', 'yacht' => 'azure', 'experience' => 'playpen', 'hours' => 4, 'guests' => 12, 'weekend' => true, 'addons' => ['lilypad'], 'perks' => ['4 hours at the Playpen', 'Captain included', 'Lily pad & floats', 'Saturday rate'], 'featured' => true],
            ['title' => 'Corporate Cruise', 'yacht' => 'skyline', 'experience' => 'corporate', 'hours' => 3, 'guests' => 40, 'weekend' => false, 'addons' => ['catering'], 'perks' => ['3-hour skyline cruise', 'Captain + 2 crew', 'Catering for 40', 'Event coordinator'], 'featured' => false],
        ])->map(function (array $package) use ($catalog, $quotes, $dates) {
            $yacht = $catalog->yacht($package['yacht']);
            $boat = $quotes->find($package['yacht']);
            $experience = $boat?->experiences->firstWhere('slug', $package['experience']);
            if ($yacht === null || $experience === null || $catalog->experience($package['experience']) === null) {
                return null;
            }
            $extras = Addon::query()->active()->whereIn('slug', $package['addons'])->get()->all();
            $quote = rescue(fn () => $quotes->quote($boat, $experience, $dates[$package['weekend'] ? 'weekend' : 'weekday'], null, $package['hours'], $package['guests'], $extras), report: false);

            return $quote === null ? null : new Fluent($package + ['yachtModel' => $yacht, 'quote' => $quote]);
        })->filter()->values();

        // The estimator starts on the first live boat that offers an experience (none live → no estimator).
        $first = $yachts->first(fn (Fluent $yacht) => $yacht->experiences !== []) ?? $yachts->first();
        $firstExperiences = $first ? $experiences->whereIn('slug', $first->experiences)->values() : collect();
        $firstExperience = $firstExperiences->first();
        $firstBoat = $first ? $quotes->find($first->slug) : null;
        $firstLengths = $first ? $first->lengths : [];
        $firstGuests = $first ? min(8, (int) $first->max_guests) : 0;

        return view('pages.pricing', [
            'packages' => $packages,
            'yachts' => $yachts,
            'experiences' => $experiences,
            'addons' => $addons,
            'pricing' => $pricing,
            'startingAt' => $yachts->mapWithKeys(fn (Fluent $yacht) => [$yacht->slug => ($boat = $quotes->find($yacht->slug)) ? $quotes->startingAt($boat) : null])->all(),
            'estimator' => $firstBoat === null ? null : [
                'yacht' => $first,
                'experiences' => $firstExperiences,
                'durations' => $firstLengths,
                'guests' => $firstGuests,
                'quote' => $quotes->quote($firstBoat, $firstExperience ? $firstBoat->experiences->firstWhere('slug', $firstExperience->slug) : null, $dates['weekday'], null, $firstLengths[0], $firstGuests),
                'dates' => $dates,
            ],
        ]);
    }

    /** The next Friday–Sunday in Chicago: the weekend example and the estimator's "Fri – Sun" choice. */
    private function nextWeekend(): string
    {
        $day = CarbonImmutable::now(config('app.display_timezone'))->addDay();
        while (! in_array($day->dayOfWeek, [0, 5, 6], true)) {
            $day = $day->addDay();
        }

        return $day->toDateString();
    }

    public function about(PublicCatalog $catalog): View
    {
        return view('pages.about', [
            'harbors' => $catalog->harbors(),
            'team' => $catalog->content('team'),
            'rating' => $catalog->content('rating'),
        ]);
    }

    public function contact(PublicCatalog $catalog): View
    {
        return view('pages.contact', [
            'experiences' => $catalog->experiences(),
            'quickAnswers' => $catalog->content('quick_answers'),
            'harbor' => $catalog->harbors()->first(),
            'today' => now(config('app.display_timezone'))->toDateString(),
        ]);
    }
}
