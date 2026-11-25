<?php

namespace App\Enums;

/**
 * Where a booking is in its life (PLAN §7.3). Payment status and the captain are tracked separately.
 * Every booking is a direct checkout paid in full (D22, 2026-11-25): no request to book, no deposit.
 *
 *   hold (15 min) ──paid in full──▶ confirmed ──trip ends──▶ completed
 *   any open booking ──▶ cancelled · a hold that runs out ──▶ expired · confirmed ──▶ no_show
 */
enum BookingStatus: string
{
    case Hold = 'hold';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Hold => 'On hold',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::NoShow => 'No-show',
        };
    }

    /** A hold keeps the boat only until `hold_expires_at`. */
    public function expires(): bool
    {
        return $this === self::Hold;
    }

    /**
     * The statuses that take the boat's time (AvailabilityService). Holds only until they expire.
     *
     * @return list<self>
     */
    public static function takingTime(): array
    {
        return [self::Hold, self::Confirmed, self::Completed, self::NoShow];
    }
}
