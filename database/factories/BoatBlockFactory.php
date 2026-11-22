<?php

namespace Database\Factories;

use App\Enums\BlockType;
use App\Models\Boat;
use App\Models\BoatBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tomorrow, 4 hours of maintenance.
 *
 * @extends Factory<BoatBlock>
 */
class BoatBlockFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->startOfHour();

        return [
            'boat_id' => Boat::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(4),
            'type' => BlockType::Maintenance,
            'reason' => 'Engine service',
        ];
    }

    /** No boat: blocks every boat (e.g. a city-wide blackout). */
    public function everyBoat(): static
    {
        return $this->state(['boat_id' => null, 'type' => BlockType::Blackout]);
    }
}
