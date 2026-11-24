<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The start-time grid of a boat's weekly availability (Boatsetter-style, user decision 2026-11-24): a trip can
 * depart every half hour from 5:00 AM to 10:00 PM, Chicago time. A boat ticks the times it offers on each
 * weekday; experiences pick their start times from the same grid, so the two can always meet.
 * Times are "HH:MM" strings (24-hour), like experiences.time_slots.
 */
final class StartTimes
{
    public const FIRST = '05:00';

    public const LAST = '22:00';

    /** Boatsetter's default availability: departures every half hour 8:00 AM – 6:00 PM. */
    public const DEFAULT_FIRST = '08:00';

    public const DEFAULT_LAST = '18:00';

    /** @return list<string> "05:00", "05:30" … "22:00" */
    public static function grid(): array
    {
        return self::between(self::FIRST, self::LAST);
    }

    /** @return list<string> */
    public static function defaults(): array
    {
        return self::between(self::DEFAULT_FIRST, self::DEFAULT_LAST);
    }

    /**
     * Every half hour from `$first` to `$last`, both included, kept inside the grid.
     *
     * @return list<string>
     */
    public static function between(string $first, string $last): array
    {
        $times = [];
        for ($m = max(self::minutes(self::FIRST), self::minutes($first)); $m <= min(self::minutes(self::LAST), self::minutes($last)); $m += 30) {
            $times[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return $times;
    }

    public static function onGrid(string $time): bool
    {
        return in_array($time, self::grid(), true);
    }

    /**
     * Grid times only, each once, in order.
     *
     * @param  array<int, mixed>  $times
     * @return list<string>
     */
    public static function normalize(array $times): array
    {
        $times = array_values(array_unique(array_filter($times, fn ($time) => is_string($time) && self::onGrid($time))));
        sort($times);

        return $times;
    }

    /** "18:30" → "6:30 PM". */
    public static function label(string $time): string
    {
        return CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A');
    }

    /**
     * The times as ranges of back-to-back half hours: "8:00 AM – 6:00 PM", "10:00 AM, 5:30 PM – 7:00 PM".
     *
     * @param  list<string>  $times
     */
    public static function summary(array $times): string
    {
        $ranges = [];
        foreach (self::normalize($times) as $time) {
            $last = array_key_last($ranges);
            if ($last !== null && self::minutes($time) - self::minutes(end($ranges[$last])) === 30) {
                $ranges[$last][] = $time;
            } else {
                $ranges[] = [$time];
            }
        }

        return implode(', ', array_map(fn (array $range) => count($range) === 1
            ? self::label($range[0])
            : self::label($range[0]).' – '.self::label(end($range)), $ranges));
    }

    /** "08:30" or "08:30:00" → minutes after midnight. */
    public static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
