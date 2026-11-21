<?php

namespace App\Http\Controllers;

use App\Support\Demo\BookingSelection;
use App\Support\Demo\DemoCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * The 4-step booking wizard (rendered by resources/js/pages/booking.js).
     * Deep links like /book?yacht=azure&experience=sunset are cleaned by BookingSelection first.
     *
     * Phase 1: catalog + demo availability run in the browser.
     * Phase 4: GET /book/availability and POST /book/quote JSON endpoints, then POST /book/hold.
     */
    public function create(Request $request, DemoCatalog $catalog): View
    {
        $selection = BookingSelection::from($request->query(), $catalog);
        $pricing = $catalog->pricing();

        return view('booking.create', [
            'wizard' => [
                'initial' => [
                    'experience' => $selection->experience,
                    'yacht' => $selection->yacht,
                    'guests' => $selection->guests,
                    'date' => $selection->date,
                    'time' => $selection->time,
                    'hours' => $selection->hours,
                    'addons' => $selection->addons,
                    'occasion' => $selection->occasion ?? '',
                ],
                'yachts' => $catalog->yachts()->map(fn (Fluent $y) => [
                    'slug' => $y->slug,
                    'title' => $y->title,
                    'category' => $y->category,
                    'length_ft' => $y->length_ft,
                    'max_guests' => $y->max_guests,
                    'min_hours' => $y->min_hours,
                    'hourly_rate_cents' => $y->hourly_rate_cents,
                    'captain_rate_cents' => $y->captain_rate_cents,
                    'crew_count' => $y->crew_count,
                    'cleaning_fee_cents' => $y->cleaning_fee_cents,
                    'rating_avg' => $y->rating_avg,
                    'reviews_count' => $y->reviews_count,
                    'harbor' => $y->harbor?->name,
                    'experiences' => $y->experiences,
                    'booking_enabled' => (bool) $y->booking_enabled,
                    'cover' => asset($y->cover),
                    'url' => route('yachts.show', $y->slug),
                ])->values(),
                'experiences' => $catalog->experiences()->map(fn (Fluent $e) => [
                    'slug' => $e->slug,
                    'name' => $e->name,
                    'icon' => $e->icon,
                    'tagline' => $e->tagline,
                    'image' => asset($e->image),
                    'durations' => $e->durations,
                    'time_slots' => $e->time_slots,
                    'package_fee_cents' => $e->package_fee_cents,
                    'allowed_weekdays' => $e->allowed_weekdays,
                ])->values(),
                'addons' => $catalog->addons()->map(fn (Fluent $a) => collect($a->toArray())->only(['slug', 'name', 'description', 'price_cents', 'unit', 'icon']))->values(),
                'pricing' => collect($pricing)->only(['weekend_multiplier_bp', 'tax_rate_bp', 'deposit_bp']),
                'occasions' => BookingSelection::OCCASIONS,
                'checkoutUrl' => route('checkout.create'),
            ],
        ]);
    }
}
