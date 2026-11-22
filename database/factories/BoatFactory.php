<?php

namespace Database\Factories;

use App\Enums\BoatOwnership;
use App\Enums\BoatStatus;
use App\Enums\BoatType;
use App\Enums\CaptainSource;
use App\Models\Boat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A published, bookable house boat by default.
 *
 * @extends Factory<Boat>
 */
class BoatFactory extends Factory
{
    public function definition(): array
    {
        $title = 'The '.Str::title(fake()->unique()->word()).' '.fake()->randomElement(['Star', 'Wave', 'Breeze', 'Grace', 'Horizon']);

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'ownership' => BoatOwnership::House,
            'status' => BoatStatus::Published,
            'booking_enabled' => true,
            'short' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'type' => BoatType::Power,
            'category' => 'Motor yacht',
            'make' => 'Sea Ray',
            'model' => 'Sundancer',
            'year' => 2019,
            'length_ft' => 45,
            'max_guests' => 12,
            'staterooms' => 2,
            'heads' => 1,
            'captain_source' => CaptainSource::Platform,
            'hourly_rate_cents' => 35000,
            'min_hours' => 2,
            'durations' => [2, 3, 4],
            'cleaning_fee_cents' => 7500,
            'captain_rate_cents' => 6000,
            'crew_count' => 1,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => BoatStatus::Draft, 'published_at' => null]);
    }

    /** Listed with the "Unavailable" tag. */
    public function unavailable(string $reason = 'Not taking bookings this season'): static
    {
        return $this->state(['booking_enabled' => false, 'unavailable_reason' => $reason]);
    }

    public function ownedBy(User $owner): static
    {
        return $this->state(['ownership' => BoatOwnership::Owner, 'owner_id' => $owner->getKey(), 'captain_source' => CaptainSource::Owner]);
    }
}
