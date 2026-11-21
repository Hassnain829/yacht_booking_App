<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoCatalog;
use App\Support\Demo\DemoQuote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/** Content pages: pricing, about, contact. */
class PageController extends Controller
{
    public function pricing(DemoCatalog $catalog): View
    {
        $yachts = $catalog->yachts();
        $experiences = $catalog->experiences();
        $addons = $catalog->addons();
        $pricing = $catalog->pricing();
        $calculator = new DemoQuote($pricing);
        $today = CarbonImmutable::now(config('app.display_timezone'))->startOfDay();

        // Example packages use the next weekday / weekend date, so the right rate applies.
        $nextDate = function (bool $weekend) use ($today): string {
            for ($date = $today->addDay(); ; $date = $date->addDay()) {
                if (DemoQuote::isWeekend($date->toDateString()) === $weekend) {
                    return $date->toDateString();
                }
            }
        };

        $packages = collect([
            ['title' => 'Sunset for Two', 'yacht' => 'windward', 'experience' => 'sunset', 'hours' => 2, 'guests' => 2, 'weekend' => false, 'addons' => ['champagne'], 'perks' => ['2-hour sunset sail', 'Licensed captain', 'Champagne toast', 'Blankets & ice'], 'featured' => false],
            ['title' => 'Playpen Party', 'yacht' => 'azure', 'experience' => 'playpen', 'hours' => 4, 'guests' => 12, 'weekend' => true, 'addons' => ['lilypad'], 'perks' => ['4 hours at the Playpen', 'Captain included', 'Lily pad & floats', 'Saturday rate'], 'featured' => true],
            ['title' => 'Corporate Cruise', 'yacht' => 'skyline', 'experience' => 'corporate', 'hours' => 3, 'guests' => 40, 'weekend' => false, 'addons' => ['catering'], 'perks' => ['3-hour skyline cruise', 'Captain + 2 crew', 'Catering for 40', 'Event coordinator'], 'featured' => false],
        ])->map(function (array $package) use ($catalog, $addons, $calculator, $nextDate) {
            $yacht = $catalog->yacht($package['yacht']);
            $quote = $calculator->quote($yacht, $catalog->experience($package['experience']), [
                'date' => $nextDate($package['weekend']),
                'hours' => $package['hours'],
                'guests' => $package['guests'],
                'addons' => $addons->whereIn('slug', $package['addons'])->values()->all(),
            ]);

            return new Fluent($package + ['yachtModel' => $yacht, 'quote' => $quote]);
        });

        $first = $yachts->first();
        $firstExperiences = $experiences->whereIn('slug', $first->experiences)->values();

        return view('pages.pricing', [
            'packages' => $packages,
            'yachts' => $yachts,
            'experiences' => $experiences,
            'addons' => $addons,
            'pricing' => $pricing,
            'estimator' => [
                'yacht' => $first,
                'experiences' => $firstExperiences,
                'durations' => DemoQuote::durations($first, $firstExperiences->first()),
                'quote' => $calculator->quote($first, $firstExperiences->first(), ['hours' => DemoQuote::durations($first, $firstExperiences->first())[0], 'guests' => 8, 'date' => $nextDate(false)]),
                'dates' => ['weekday' => $nextDate(false), 'weekend' => $nextDate(true)],
            ],
        ]);
    }

    public function about(DemoCatalog $catalog): View
    {
        return view('pages.about', [
            'harbors' => $catalog->harbors(),
            'team' => $catalog->content('team'),
            'rating' => $catalog->content('rating'),
        ]);
    }

    public function contact(DemoCatalog $catalog): View
    {
        return view('pages.contact', [
            'experiences' => $catalog->experiences(),
            'quickAnswers' => $catalog->content('quick_answers'),
            'harbor' => $catalog->harbors()->first(),
            'today' => now(config('app.display_timezone'))->toDateString(),
        ]);
    }
}
