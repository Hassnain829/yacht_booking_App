<?php

namespace App\Http\Controllers;

use App\Services\PublicCatalog;
use App\Support\Demo\DemoQuote;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

class ExperienceController extends Controller
{
    public const CATEGORIES = ['all' => 'All experiences', 'sightseeing' => 'Sightseeing', 'party' => 'Parties & Celebrations', 'private' => 'Private & Corporate'];

    private const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function index(Request $request, PublicCatalog $catalog): View
    {
        $category = $request->query('category');
        $category = is_string($category) && array_key_exists($category, self::CATEGORIES) ? $category : 'all';
        $yachts = $catalog->yachts();

        $experiences = $catalog->experiences()->map(function (Fluent $experience) use ($yachts) {
            $boats = $yachts->filter(fn (Fluent $y) => in_array($experience->slug, $y->experiences, true))->values();
            $bookable = $boats->where('booking_enabled', true);
            $priced = $bookable->isNotEmpty() ? $bookable : $boats;

            $experience->boats = $boats;
            $experience->from_cents = $priced->map(fn (Fluent $y) => DemoQuote::startingAt($y, $experience))->min();
            $experience->days_label = $experience->allowed_weekdays
                ? collect($experience->allowed_weekdays)->map(fn (int $d) => self::WEEKDAYS[$d])->join(', ', ' & ')
                : 'Daily';
            $experience->first_departure = CarbonImmutable::createFromFormat('H:i', $experience->time_slots[0])->format('g:i A');

            return $experience;
        });

        return view('pages.experiences', [
            'experiences' => $experiences,
            'category' => $category,
        ]);
    }
}
