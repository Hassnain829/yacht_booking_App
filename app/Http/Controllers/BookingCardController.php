<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingCard\AvailabilityRequest;
use App\Http\Requests\BookingCard\PriceRequest;
use App\Http\Resources\BookingCard\CalendarResource;
use App\Http\Resources\BookingCard\QuoteResource;
use App\Http\Resources\BookingCard\StartTimesResource;
use App\Services\AvailabilityService;
use App\Services\BoatQuotes;
use App\Support\Pricing\QuoteRequest;

/**
 * The boat page's booking card (PLAN 4.3, D21): read-only JSON, so plain GETs with a named rate limiter
 * (§8.2). The calendar and start times come from AvailabilityService, the price from QuoteService. A boat that
 * isn't published (draft, hidden, in the Trash) answers 404; choices that don't fit it answer 422.
 *
 * BoatQuotes is injected per call, never into the constructor: it keeps the request's boats, and a route keeps
 * its controller instance, so a constructor-held copy would outlive the request.
 */
class BookingCardController extends Controller
{
    /** ?month=2027-06 → each day's status; ?date=2027-06-04 → that day's start times. */
    public function availability(AvailabilityRequest $request, string $slug, BoatQuotes $boats, AvailabilityService $availability): CalendarResource|StartTimesResource
    {
        $boat = $boats->find($slug) ?? abort(404);
        $experience = $request->experience();
        $boats->ensureFits($boat, $experience, $request->hours(), $request->guests());

        $month = $request->validated('month');
        if ($month !== null) {
            return new CalendarResource(['month' => $month, 'days' => $availability->calendar($boat, $month, $request->hours(), $experience, $request->guests())]);
        }

        $date = $request->validated('date');

        return new StartTimesResource(['date' => $date, 'slots' => $availability->slots($boat, $date, $request->hours(), $experience, $request->guests())]);
    }

    /** Every price line for the choices so far; the free-cancellation date once a start time is picked. */
    public function quote(PriceRequest $request, string $slug, BoatQuotes $boats): QuoteResource
    {
        $boat = $boats->find($slug) ?? abort(404);
        $date = $request->tripDate();
        $time = $request->tripTime();
        $quote = $boats->quote($boat, $request->experience(), $date, $time, $request->hours(), $request->guests(), $request->addons());
        $policy = $boat->cancellationPolicy;

        return new QuoteResource([
            'quote' => $quote,
            'estimate' => $date === null,
            'policy' => $policy === null ? null : ['name' => $policy->name, 'url' => route('legal.cancellation')],
            'free_until' => $date !== null && $time !== null ? $boats->freeCancellationUntil($boat, QuoteRequest::localStart($date, $time)) : null,
        ]);
    }
}
