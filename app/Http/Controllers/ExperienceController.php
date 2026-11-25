<?php

namespace App\Http\Controllers;

use App\Services\BoatQuotes;
use App\Services\PublicCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/**
 * Experiences page (4.4, D21): Chicago trip themes listed like categories. Each one shows the boats that offer
 * it, and every "Book" goes to a boat page with the experience already picked in its booking card. An experience
 * is free, has no length of its own (the boat is booked by the hour) and departs in its own window; its extras are
 * optional, offered at checkout (user decisions 2026-11-25). "From" prices come from QuoteService.
 */
class ExperienceController extends Controller
{
    public const CATEGORIES = ['all' => 'All experiences', 'sightseeing' => 'Sightseeing', 'party' => 'Parties & Celebrations', 'private' => 'Private & Corporate'];

    public function index(Request $request, PublicCatalog $catalog, BoatQuotes $quotes): View
    {
        $category = $request->query('category');
        $category = is_string($category) && array_key_exists($category, self::CATEGORIES) ? $category : 'all';
        $yachts = $catalog->yachts();

        $experiences = $catalog->experiences()->map(function (Fluent $experience) use ($yachts, $quotes) {
            $boats = $yachts->filter(fn (Fluent $y) => in_array($experience->slug, $y->experiences, true))
                ->sortByDesc(fn (Fluent $y) => (int) $y->booking_enabled)->values();
            $bookable = $boats->where('booking_enabled', true)->values();
            $priced = $bookable->isNotEmpty() ? $bookable : $boats;

            $experience->boats = $boats;
            $experience->from_cents = $priced->map(function (Fluent $yacht) use ($quotes, $experience) {
                $boat = $quotes->find($yacht->slug);
                $offered = $boat?->experiences->firstWhere('slug', $experience->slug);

                return $boat && $offered ? $quotes->startingAt($boat, $offered) : null;
            })->filter()->min();
            $experience->book_url = $bookable->count() === 1
                ? route('yachts.show', [$bookable->first()->slug, 'experience' => $experience->slug]).'#book'
                : route('yachts.index', ['experience' => $experience->slug]);
            $experience->extras_label = $experience->extras === []
                ? null
                : collect($experience->extras)->map(fn (array $extra) => $extra['name'].' '.money($extra['price_cents']).($extra['unit'] === 'per_guest' ? '/guest' : ''))->join(', ');

            return $experience;
        });

        return view('pages.experiences', [
            'experiences' => $experiences,
            'category' => $category,
        ]);
    }
}
