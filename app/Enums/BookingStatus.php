<?php

namespace App\Enums;

/**
 * Where a booking is in its life (PLAN §7.3). Payment status and the captain are tracked separately.
 *
 *   hold (15 min) ──paid──▶ confirmed ──trip ends──▶ completed        (house boats: booked at once)
 *   hold ──request sent──▶ requested ──owner accepts──▶ confirmed     (owner boats: request to book, 2026-11-24)
 *                                     └─ declined / expired
 *   any open booking ──▶ cancelled · a hold or request that runs out ──▶ expired · confirmed ──▶ no_show
 */
enum BookingStatus: string
{
    case Hold = 'hold';
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Declined = 'declined';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Hold => 'On hold',
            self::Requested => 'Waiting for the owner',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Declined => 'Declined',
            self::NoShow => 'No-show',
        };
    }

    /** A hold or a request keeps the boat only until `hold_expires_at`. */
    public function expires(): bool
    {
        return $this === self::Hold || $this === self::Requested;
    }

    /**
     * The statuses that take the boat's time (AvailabilityService). Holds and requests only until they expire.
     *
     * @return list<self>
     */
    public static function takingTime(): array
    {
        return [self::Hold, self::Requested, self::Confirmed, self::Completed, self::NoShow];
    }
}
