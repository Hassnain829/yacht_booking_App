<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Boat;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::now()->addDays(14)->setTime(18, 0);

        return [
            'reference' => Booking::newReference(),
            'boat_id' => Boat::factory(),
            'status' => BookingStatus::Confirmed,
            'starts_at' => $start,
            'ends_at' => $start->addHours(3),
            'hours' => 3,
            'guests' => 6,
            'confirmed_at' => CarbonImmutable::now(),
        ];
    }

    /** A trip from `$startsAt` (UTC) for `$hours`. */
    public function at(CarbonImmutable $startsAt, int $hours = 3): static
    {
        return $this->state(['starts_at' => $startsAt, 'ends_at' => $startsAt->addHours($hours), 'hours' => $hours]);
    }

    public function hold(?CarbonImmutable $expiresAt = null): static
    {
        return $this->state(['status' => BookingStatus::Hold, 'confirmed_at' => null, 'hold_expires_at' => $expiresAt ?? CarbonImmutable::now()->addMinutes(15)]);
    }

    public function requested(?CarbonImmutable $expiresAt = null): static
    {
        return $this->state(['status' => BookingStatus::Requested, 'confirmed_at' => null, 'hold_expires_at' => $expiresAt ?? CarbonImmutable::now()->addDay()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => BookingStatus::Cancelled, 'cancelled_at' => CarbonImmutable::now(), 'cancelled_by_role' => 'customer']);
    }
}
