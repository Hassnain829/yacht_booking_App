<?php

namespace App\Support\Booking;

/** Why a guest cancels their trip online (the cancel dialog on the trip page, PLAN 5.4). */
final class CancelReasons
{
    public const OPTIONS = [
        'plans' => 'Change of plans',
        'date' => 'Need a different date',
        'weather' => 'Worried about the weather',
        'group' => 'Our group changed',
        'price' => 'Found a better price',
        'other' => 'Something else',
    ];

    public static function label(string $key): string
    {
        return self::OPTIONS[$key] ?? 'Something else';
    }
}
