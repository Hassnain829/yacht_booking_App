<?php

namespace App\Services;

use App\Exceptions\AvailabilityException;
use App\Models\Boat;
use App\Models\BoatBlock;
use App\Models\Booking;
use App\Models\Experience;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;

/**
 * When a boat can depart (PLAN §7.2; Boatsetter-style start times, user decision 2026-11-24). Everything is
 * decided in Chicago time; stored times are UTC.
 *
 * A start is free only if all of these hold:
 *  - the boat is published, bookable ("Unavailable" tag off) and not in the Trash;
 *  - the guests fit (1 … max_guests) and the length is one the boat offers (QuoteService; an experience has no
 *    length of its own);
 *  - the boat offers the experience, and the experience runs that weekday and inside its season;
 *  - the time is one of the boat's start times that weekday — and inside the experience's departure window
 *    (a sunset cruise departs in the evening, the fireworks at night);
 *  - it is at least the boat's advance notice ahead of now;
 *  - the trip [start, start + hours) doesn't touch a block (the boat's, or a fleet-wide one), nor another trip
 *    that takes the boat's time (confirmed, or a hold / request that hasn't run out) widened by the boat's buffer;
 *  - and, for a boat that takes one booking a day, no other trip starts that day.
 *
 * busyIntervals() is the one source of the boat's busy time: the booking card, holds (4.5), captain offers
 * and trip extensions (Phase 8) all read it, so they can never disagree. A day or a month needs one query for
 * trips and one for blocks.
 */
class AvailabilityService
{
    public function __construct(private QuoteService $quotes) {}

    /**
     * The start times offered on a date, each free or taken (`reason`: notice, booked, blocked, one_a_day).
     * Empty when the boat or the experience doesn't run that day at all.
     *
     * @return list<array{time: string, label: string, starts_at: CarbonImmutable, available: bool, reason: ?string}>
     */
    public function slots(Boat $boat, string $date, int $hours, ?Experience $experience = null, int $guests = 1): array
    {
        $boat->loadMissing(['availabilityRules', 'experiences']);
        $local = $this->localDate($date);
        $busy = $this->busyIntervals($boat, $local->subDay()->utc(), $local->addDays(2)->utc());

        return $this->daySlots($boat, $local, $hours, $experience, $guests, $busy);
    }

    /**
     * Each date of a month ("2027-06"): past, unavailable (the boat or the experience doesn't run that day),
     * soldout (every start taken), limited (fewer than half free) or available.
     *
     * @return array<string, string> "2027-06-01" => "available", …
     */
    public function calendar(Boat $boat, string $month, int $hours, ?Experience $experience = null, int $guests = 1): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \InvalidArgumentException('Expected a YYYY-MM month.');
        }
        $boat->loadMissing(['availabilityRules', 'experiences']);
        $first = $this->localDate($month.'-01');
        $busy = $this->busyIntervals($boat, $first->subDay()->utc(), $first->endOfMonth()->addDays(2)->utc());
        $today = CarbonImmutable::now(config('app.display_timezone'))->startOfDay();

        $days = [];
        for ($day = $first; $day->month === $first->month; $day = $day->addDay()) {
            $slots = $this->daySlots($boat, $day, $hours, $experience, $guests, $busy);
            $free = count(array_filter($slots, fn (array $slot) => $slot['available']));
            $days[$day->toDateString()] = match (true) {
                $day->lessThan($today) => 'past',
                $slots === [] => 'unavailable',
                $free === 0 => 'soldout',
                $free * 2 < count($slots) => 'limited',
                default => 'available',
            };
        }

        return $days;
    }

    /**
     * Why this exact trip can't take place, or null when it can. `$checkNotice` off: a payment that came in after
     * its hold ran out is not refused for the advance notice it already met when it was held.
     *
     * @return array{field: string, message: string}|null
     */
    public function problem(Boat $boat, CarbonImmutable $startsAt, int $hours, ?Experience $experience = null, int $guests = 1, ?Booking $ignore = null, bool $checkNotice = true): ?array
    {
        $boat->loadMissing(['availabilityRules', 'experiences']);
        $local = $startsAt->setTimezone(config('app.display_timezone'));
        $day = $local->startOfDay();
        if (($reason = $this->dayProblem($boat, $day, $hours, $experience, $guests)) !== null) {
            return $reason;
        }
        $time = $local->format('H:i');
        if (! in_array($time, $this->candidates($boat, $day, null), true) || $local->second !== 0) {
            return ['field' => 'time', 'message' => 'The boat doesn\'t depart at that time. Pick one of the start times shown.'];
        }
        if ($experience !== null && ! in_array($time, $this->candidates($boat, $day, $experience), true)) {
            return ['field' => 'time', 'message' => self::outsideWindow($experience, $time)];
        }

        $busy = $this->busyIntervals($boat, $day->subDay()->utc(), $day->addDays(2)->utc(), $ignore);
        $taken = $this->slotProblem($boat, $startsAt->utc(), $hours, $day, $busy, $checkNotice);

        return $taken === null ? null : ['field' => 'time', 'message' => self::message($taken, $boat)];
    }

    /**
     * For holds (4.4), inside the transaction that locks the boat row: throws a 422 when the trip isn't free.
     *
     * @throws AvailabilityException
     */
    public function ensureFree(Boat $boat, CarbonImmutable $startsAt, int $hours, ?Experience $experience = null, int $guests = 1, ?Booking $ignore = null): void
    {
        $problem = $this->problem($boat, $startsAt, $hours, $experience, $guests, $ignore);
        if ($problem !== null) {
            throw AvailabilityException::on($problem['field'], $problem['message']);
        }
    }

    /**
     * The boat's busy time that touches [from, to): its trips that take its time (widened by its buffer on both
     * sides) and blocks (its own and fleet-wide ones).
     *
     * @return list<array{kind: 'booking'|'block', starts_at: CarbonImmutable, ends_at: CarbonImmutable, trip_starts_at: ?CarbonImmutable}>
     */
    public function busyIntervals(Boat $boat, CarbonImmutable $from, CarbonImmutable $to, ?Booking $ignore = null): array
    {
        $buffer = max(0, (int) $boat->buffer_minutes);

        $trips = $boat->bookings()->takingTime()
            ->where('starts_at', '<', $to->addMinutes($buffer))
            ->where('ends_at', '>', $from->subMinutes($buffer))
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['id', 'starts_at', 'ends_at'])
            ->map(fn (Booking $trip) => [
                'kind' => 'booking',
                'starts_at' => $trip->starts_at->subMinutes($buffer),
                'ends_at' => $trip->ends_at->addMinutes($buffer),
                'trip_starts_at' => $trip->starts_at,
            ]);

        $blocks = BoatBlock::query()->overlapping($boat, $from, $to)->get(['starts_at', 'ends_at'])
            ->map(fn (BoatBlock $block) => ['kind' => 'block', 'starts_at' => $block->starts_at, 'ends_at' => $block->ends_at, 'trip_starts_at' => null]);

        return $trips->concat($blocks)->sortBy(fn (array $interval) => $interval['starts_at']->getTimestamp())->values()->all();
    }

    /**
     * @param  list<array{kind: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, trip_starts_at: ?CarbonImmutable}>  $busy
     * @return list<array{time: string, label: string, starts_at: CarbonImmutable, available: bool, reason: ?string}>
     */
    private function daySlots(Boat $boat, CarbonImmutable $day, int $hours, ?Experience $experience, int $guests, array $busy): array
    {
        if ($this->dayProblem($boat, $day, $hours, $experience, $guests) !== null) {
            return [];
        }

        $slots = [];
        foreach ($this->candidates($boat, $day, $experience) as $time) {
            $startsAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $day->toDateString().' '.$time, config('app.display_timezone'))->utc();
            $reason = $this->slotProblem($boat, $startsAt, $hours, $day, $busy);
            $slots[] = ['time' => $time, 'label' => StartTimes::label($time), 'starts_at' => $startsAt, 'available' => $reason === null, 'reason' => $reason];
        }

        return $slots;
    }

    /**
     * What rules the whole day out, if anything.
     *
     * @return array{field: string, message: string}|null
     */
    private function dayProblem(Boat $boat, CarbonImmutable $day, int $hours, ?Experience $experience, int $guests): ?array
    {
        if (! $boat->isBookable() || $boat->trashed()) {
            return ['field' => 'boat', 'message' => 'This boat isn\'t taking bookings right now.'];
        }
        if ($guests < 1 || $guests > (int) $boat->max_guests) {
            return ['field' => 'guests', 'message' => "This boat takes 1 to {$boat->max_guests} guests."];
        }
        if ($experience !== null) {
            $offered = $boat->offering($experience);
            if ($offered === null) {
                return ['field' => 'experience', 'message' => 'This boat doesn\'t offer that experience.'];
            }
            if ($offered->allowed_weekdays !== null && ! in_array($day->dayOfWeek, array_map('intval', $offered->allowed_weekdays), true)) {
                return ['field' => 'time', 'message' => "{$offered->name} doesn't run on ".$day->format('l').'s.'];
            }
            if ($offered->season_starts_on && ($day->toDateString() < $offered->season_starts_on->toDateString() || $day->toDateString() > $offered->season_ends_on?->toDateString())) {
                return ['field' => 'time', 'message' => "{$offered->name} runs from ".$offered->season_starts_on->format('M j').' to '.$offered->season_ends_on?->format('M j, Y').'.'];
            }
        }
        $lengths = $this->quotes->durations($boat);
        if (! in_array($hours, $lengths, true)) {
            return ['field' => 'hours', 'message' => 'Choose one of the trip lengths offered: '.implode(', ', $lengths).' hours.'];
        }
        if ($this->candidates($boat, $day, null) === []) {
            return ['field' => 'time', 'message' => 'The boat doesn\'t depart on '.$day->format('l, M j').'. Pick another day.'];
        }
        if ($experience !== null && $this->candidates($boat, $day, $experience) === []) {
            return ['field' => 'time', 'message' => "{$experience->name} departs ".StartTimes::summary($experience->time_slots ?? []).', and the boat doesn\'t depart then on '.$day->format('l, M j').'. Pick another day or just book the boat.'];
        }

        return null;
    }

    /**
     * The boat's start times that weekday — and, with an experience, only those inside its departure window.
     *
     * @return list<string>
     */
    private function candidates(Boat $boat, CarbonImmutable $day, ?Experience $experience): array
    {
        $rule = $boat->availabilityRules->firstWhere('weekday', $day->dayOfWeek);
        $times = $rule ? $rule->openTimes() : [];

        return $experience ? array_values(array_intersect($times, StartTimes::normalize($experience->time_slots ?? []))) : $times;
    }

    /**
     * Why a start is taken, or null when it is free.
     *
     * @param  list<array{kind: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, trip_starts_at: ?CarbonImmutable}>  $busy
     */
    private function slotProblem(Boat $boat, CarbonImmutable $startsAt, int $hours, CarbonImmutable $day, array $busy, bool $checkNotice = true): ?string
    {
        if ($checkNotice && $startsAt->lessThan(CarbonImmutable::now()->addMinutes(max(0, (int) $boat->advance_notice_minutes)))) {
            return 'notice';
        }

        $endsAt = $startsAt->addHours($hours);
        $date = $day->toDateString();
        foreach ($busy as $interval) {
            if ($interval['kind'] === 'booking' && ! $boat->allow_multiple_bookings
                && $interval['trip_starts_at']->setTimezone(config('app.display_timezone'))->toDateString() === $date) {
                return 'one_a_day';
            }
            if ($startsAt->lessThan($interval['ends_at']) && $endsAt->greaterThan($interval['starts_at'])) {
                return $interval['kind'] === 'block' ? 'blocked' : 'booked';
            }
        }

        return null;
    }

    /** A Chicago calendar date ("2027-06-04") at midnight. */
    private function localDate(string $date): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Expected a YYYY-MM-DD date.');
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, config('app.display_timezone'));
    }

    /** "Sunset Skyline Cruise isn't available at 10:00 AM: it departs 5:00 PM – 7:30 PM." */
    public static function outsideWindow(Experience $experience, string $time): string
    {
        return "{$experience->name} isn't available at ".StartTimes::label($time).': it departs '.StartTimes::summary($experience->time_slots ?? []).'. Pick a start time inside that window, or just book the boat.';
    }

    /** What the customer reads for a taken start. */
    public static function message(string $reason, Boat $boat): string
    {
        return match ($reason) {
            'notice' => 'This trip starts too soon: the boat needs '.self::notice((int) $boat->advance_notice_minutes).' notice. Pick a later time.',
            'booked' => 'That time is already booked. Pick another time.',
            'blocked' => 'The boat isn\'t available at that time. Pick another time.',
            'one_a_day' => 'This boat takes one trip a day, and that day is booked. Pick another day.',
            default => 'That time isn\'t available. Pick another time.',
        };
    }

    /** 90 → "1.5 hours'", 1440 → "1 day's". */
    private static function notice(int $minutes): string
    {
        if ($minutes >= 1440 && $minutes % 1440 === 0) {
            $days = intdiv($minutes, 1440);

            return $days.' '.($days === 1 ? "day's" : "days'");
        }
        $hours = round($minutes / 60, 1);

        return rtrim(rtrim(number_format($hours, 1), '0'), '.').' '.($hours == 1.0 ? "hour's" : "hours'");
    }
}
