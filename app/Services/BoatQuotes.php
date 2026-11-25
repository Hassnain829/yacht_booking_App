<?php

namespace App\Services;

use App\Exceptions\QuoteException;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Boats as the booking card, the fleet's date filter and the price examples need them (PLAN 4.3–4.4): published
 * boats by slug with what AvailabilityService and QuoteService read, loaded once per request. Prices still come
 * only from QuoteService; this class just asks it.
 */
class BoatQuotes
{
    /** @var Collection<string, Boat>|null */
    private ?Collection $boats = null;

    public function __construct(private QuoteService $quotes) {}

    /** A published boat (Unavailable tag or not), or null: hidden, a draft, in the Trash or unknown. */
    public function find(string $slug): ?Boat
    {
        return $this->all()->get($slug);
    }

    /** @return Collection<string, Boat> every published boat, keyed by slug */
    public function all(): Collection
    {
        return $this->boats ??= Boat::query()
            ->listed()
            ->with(['availabilityRules', 'cancellationPolicy', 'experiences'])
            ->get()
            ->keyBy('slug');
    }

    /**
     * The guest's choices must fit the boat before its calendar or start times are worth showing.
     *
     * @throws QuoteException on experience, hours or guests
     */
    public function ensureFits(Boat $boat, ?Experience $experience, int $hours, int $guests): void
    {
        if ($experience !== null && $boat->offering($experience) === null) {
            throw QuoteException::on('experience', 'This boat doesn\'t offer that experience.');
        }
        $lengths = $this->quotes->durations($boat);
        if (! in_array($hours, $lengths, true)) {
            throw QuoteException::on('hours', 'Choose one of the trip lengths offered: '.implode(', ', $lengths).' hours.');
        }
        if ($guests < 1 || $guests > (int) $boat->max_guests) {
            throw QuoteException::on('guests', "This boat takes 1 to {$boat->max_guests} guests.");
        }
    }

    /**
     * The price of a trip. Without a date it is a weekday estimate (the next Monday–Thursday); without a start
     * time, that day.
     *
     * @param  list<Addon>  $addons
     *
     * @throws QuoteException when the choices don't fit the boat
     */
    public function quote(Boat $boat, ?Experience $experience, ?string $date, ?string $time, int $hours, int $guests, array $addons = []): Quote
    {
        return $this->quotes->quote(new QuoteRequest(
            boat: $boat,
            experience: $experience,
            startsAt: QuoteRequest::localStart($date ?? $this->weekday(), $date === null ? '12:00' : $time),
            hours: $hours,
            guests: $guests,
            addons: $addons,
        ));
    }

    /**
     * "From" price: the shortest length on a weekday for one guest, before tax (charter, captain, fuel & cleaning;
     * an experience is free and its extras are optional). Null when the boat can't be priced with that experience.
     */
    public function startingAt(Boat $boat, ?Experience $experience = null): ?int
    {
        try {
            return $this->quote($boat, $experience, null, null, $this->quotes->durations($boat)[0], 1)->subtotalCents;
        } catch (QuoteException) {
            return null;
        }
    }

    /** The lengths the boat offers (QuoteService::durations; the same with any experience). @return list<int> */
    public function lengths(Boat $boat): array
    {
        return $this->quotes->durations($boat);
    }

    /** Until when a cancellation still gets everything back under the boat's policy; null when that has passed. */
    public function freeCancellationUntil(Boat $boat, CarbonImmutable $startsAt): ?CarbonImmutable
    {
        $hours = $boat->cancellationPolicy?->freeCancellationHours();
        if ($hours === null) {
            return null;
        }
        $until = $startsAt->subHours($hours);

        return $until->isFuture() ? $until : null;
    }

    /** The next Monday–Thursday in Chicago ("2027-06-07"): weekday prices for estimates. */
    public function weekday(): string
    {
        $day = CarbonImmutable::now(config('app.display_timezone'))->addDay();
        while (! in_array($day->dayOfWeek, [1, 2, 3, 4], true)) {
            $day = $day->addDay();
        }

        return $day->toDateString();
    }
}
