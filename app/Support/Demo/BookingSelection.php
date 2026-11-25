<?php

namespace App\Support\Demo;

use App\Services\PublicCatalog;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;

/**
 * The visitor's booking choices (experience, yacht, guests, date, time, hours, add-ons, occasion),
 * cleaned from untrusted input (query string or hidden form fields).
 *
 * Every value is checked against the catalog; anything unknown, out of range or inconsistent is
 * dropped. Values are never echoed back unchecked. Since 4.3 the choices come from the boat page's booking card:
 * the experience is optional (D21) and free, the lengths are the boat's (PublicCatalog `lengths`; an experience has
 * none of its own), and a start time must be on the start-time grid and inside the experience's departure window.
 * Add-ons are optional and picked at checkout (2026-11-25). 4.5 replaces this with a real hold, checked by
 * AvailabilityService inside the boat-row lock.
 */
class BookingSelection
{
    public const OCCASIONS = ['Birthday', 'Bachelorette / Bachelor', 'Anniversary', 'Proposal', 'Corporate event', 'Family outing', 'Other'];

    public const MAX_GUESTS = 80;

    public const MAX_ADDONS = 10;

    /** How far ahead a date may be picked. */
    public const MAX_MONTHS_AHEAD = 18;

    /** Same-day departures must be more than this many hours away. */
    public const MIN_NOTICE_HOURS = 2;

    public ?string $experience = null;

    public ?string $yacht = null;

    public int $guests = 6;

    public ?string $date = null;

    public ?string $time = null;

    public ?int $hours = null;

    /** @var list<string> */
    public array $addons = [];

    public ?string $occasion = null;

    public function __construct(private PublicCatalog $catalog) {}

    /** @param array<string, mixed> $input */
    public static function from(array $input, PublicCatalog $catalog): self
    {
        $selection = new self($catalog);
        $str = fn (string $key) => isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : null;

        $experience = $catalog->experience((string) $str('experience'));
        $selection->experience = $experience?->slug;

        $yacht = $catalog->yacht((string) $str('yacht'));
        if ($yacht && $yacht->booking_enabled && (! $experience || in_array($experience->slug, $yacht->experiences, true))) {
            $selection->yacht = $yacht->slug;
        } else {
            $yacht = null;
        }

        $guests = filter_var($str('guests'), FILTER_VALIDATE_INT);
        $selection->guests = max(1, min(self::MAX_GUESTS, $guests === false ? 6 : $guests));
        if ($yacht && $selection->guests > $yacht->max_guests) {
            $selection->guests = $yacht->max_guests; // cap guests rather than dropping the boat
        }

        if ($yacht) {
            $durations = $yacht->lengths;
            $hours = filter_var($str('hours'), FILTER_VALIDATE_INT);
            $selection->hours = in_array($hours, $durations, true) ? $hours : $durations[0];

            $selection->date = self::validDate($str('date'), $experience);
            $time = $str('time');
            $times = $experience ? StartTimes::normalize($experience->time_slots) : StartTimes::grid();
            $selection->time = $selection->date && in_array($time, $times, true) && self::hasNotice($selection->date, $time) ? $time : null;
        }

        $addons = $input['addons'] ?? [];
        if (is_string($addons)) {
            $addons = explode(',', $addons);
        }
        $known = $catalog->addons()->pluck('slug')->all();
        $selection->addons = is_array($addons)
            ? array_values(array_slice(array_unique(array_intersect(array_filter($addons, 'is_string'), $known)), 0, self::MAX_ADDONS))
            : [];

        $occasion = $str('occasion');
        $selection->occasion = in_array($occasion, self::OCCASIONS, true) ? $occasion : null;

        return $selection;
    }

    /** A real calendar date, today or later (Chicago time), within the booking window, on an allowed weekday. */
    public static function validDate(?string $date, ?Fluent $experience): ?string
    {
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, config('app.display_timezone'));
        } catch (\Throwable) {
            return null;
        }
        if (! $day || $day->toDateString() !== $date) {
            return null; // e.g. 2026-02-30
        }
        $today = CarbonImmutable::now(config('app.display_timezone'))->startOfDay();
        if ($day->lt($today) || $day->gt($today->addMonths(self::MAX_MONTHS_AHEAD))) {
            return null;
        }
        if ($experience?->allowed_weekdays && ! in_array($day->dayOfWeek, $experience->allowed_weekdays, true)) {
            return null;
        }

        return $date;
    }

    /**
     * Same-day departures need more than MIN_NOTICE_HOURS' notice (Chicago time). 4.5: the boat's own advance
     * notice, through AvailabilityService.
     */
    public static function hasNotice(string $date, string $time): bool
    {
        $now = CarbonImmutable::now(config('app.display_timezone'));
        if ($date !== $now->toDateString()) {
            return true;
        }

        return (int) substr($time, 0, 2) > $now->hour + self::MIN_NOTICE_HOURS;
    }

    /** Yacht, date, start time and length are chosen (the experience is optional, D21). */
    public function isComplete(): bool
    {
        return $this->yacht && $this->date && $this->time && $this->hours;
    }

    public function yachtModel(): ?Fluent
    {
        return $this->yacht ? $this->catalog->yacht($this->yacht) : null;
    }

    public function experienceModel(): ?Fluent
    {
        return $this->experience ? $this->catalog->experience($this->experience) : null;
    }

    /** @return list<Fluent> */
    public function addonModels(): array
    {
        return $this->catalog->addons()->whereIn('slug', $this->addons)->values()->all();
    }

    /** @return array<string, mixed> Query string for the boat page or /checkout (empty values dropped). */
    public function toQuery(): array
    {
        return array_filter([
            'experience' => $this->experience,
            'yacht' => $this->yacht,
            'guests' => $this->guests,
            'date' => $this->date,
            'time' => $this->time,
            'hours' => $this->hours,
            'addons' => $this->addons ? implode(',', $this->addons) : null,
            'occasion' => $this->occasion,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
