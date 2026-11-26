<?php

namespace App\Support\Calendar;

use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * A booking as a calendar event (.ics, RFC 5545): attached to the confirmation email and downloaded from the
 * confirmation page (PLAN 4.6). Times are UTC ("…Z"), so every calendar shows them in its own time zone; text is
 * escaped and long lines are folded at 75 octets, with CRLF line ends.
 */
final class TripCalendar
{
    public static function ics(Booking $booking, ?CarbonImmutable $now = null): string
    {
        $booking->loadMissing(['boat.harbor', 'experience']);
        $boat = $booking->boat;
        $harbor = $boat?->harbor;
        $business = config('site.business');
        $title = ($booking->experience?->name ?? 'Yacht charter').' on '.($boat?->title ?? 'your yacht');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Chicago Yacht Booking//Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$booking->ulid.'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'chicagoyachtbooking.com'),
            'DTSTAMP:'.self::utc($now ?? CarbonImmutable::now()),
            'DTSTART:'.self::utc($booking->starts_at),
            'DTEND:'.self::utc($booking->ends_at),
            'SUMMARY:'.self::text($title),
            'LOCATION:'.self::text(collect([$harbor?->name, $harbor?->address])->filter()->implode(', ') ?: 'Chicago, IL'),
            'DESCRIPTION:'.self::text("Booking {$booking->reference} · {$booking->guests} ".($booking->guests === 1 ? 'guest' : 'guests')
                .". Please arrive 20 minutes before departure. Questions: {$business['phone']}."),
            'URL:'.self::text(route('bookings.confirmation', $booking)),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /** A file name such as "CYB-7K3P9Q.ics". */
    public static function filename(Booking $booking): string
    {
        return preg_replace('/[^A-Za-z0-9-]/', '', $booking->reference).'.ics';
    }

    private static function utc(CarbonImmutable $time): string
    {
        return $time->utc()->format('Ymd\THis\Z');
    }

    /** Escape per RFC 5545 §3.3.11. */
    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /** Lines longer than 75 octets continue on the next line after a space (§3.1), never inside a character. */
    private static function fold(string $line): string
    {
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current.$char) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ").$current;
                $current = '';
            }
            $current .= $char;
        }

        return $out === '' ? $current : $out."\r\n ".$current;
    }
}
