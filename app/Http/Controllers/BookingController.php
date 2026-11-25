<?php

namespace App\Http\Controllers;

use App\Services\PublicCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /book — the old booking-wizard address (retired in 4.3, D21: a trip is picked on the boat page, like on
 * Boatsetter). Old links, the home page search and bookmarks keep working:
 *
 *   /book?yacht=azure&experience=sunset&date=2027-06-04&hours=3&guests=6  →  /yachts/azure?…#book
 *   /book?experience=sunset&date=2027-06-04&guests=6                     →  /yachts?… (the fleet, filtered)
 *
 * Only values in the expected shape are passed on (unknown boats and experiences, bad dates, numbers out of
 * range are dropped); the page they land on checks them again.
 */
class BookingController extends Controller
{
    public function __invoke(Request $request, PublicCatalog $catalog): RedirectResponse
    {
        $text = fn (string $key, string $pattern) => is_string($value = $request->query($key)) && preg_match($pattern, $value) ? $value : null;
        $number = fn (string $key, int $max) => ($value = filter_var($request->query($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]])) === false ? null : $value;

        $yacht = $catalog->yacht((string) $text('yacht', '/^[a-z0-9-]{1,80}$/'));
        $experience = $catalog->experience((string) $text('experience', '/^[a-z0-9-]{1,80}$/'))?->slug;
        $choices = array_filter([
            'experience' => $yacht === null || in_array($experience, $yacht->experiences, true) ? $experience : null,
            'date' => $text('date', '/^\d{4}-\d{2}-\d{2}$/'),
            'guests' => $number('guests', 500),
        ], fn ($value) => $value !== null);

        if ($yacht === null) {
            return redirect()->route('yachts.index', $choices);
        }

        $hours = $number('hours', 24);

        return redirect()->to(route('yachts.show', [$yacht->slug, ...$choices, ...($hours ? ['hours' => $hours] : [])]).'#book');
    }
}
