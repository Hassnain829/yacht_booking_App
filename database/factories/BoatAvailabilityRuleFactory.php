<?php

namespace Database\Factories;

use App\Models\Boat;
use App\Models\BoatAvailabilityRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Open 9:00–21:00 on the given weekday (Chicago time).
 *
 * @extends Factory<BoatAvailabilityRule>
 */
class BoatAvailabilityRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'boat_id' => Boat::factory(),
            'weekday' => 6,
            'is_open' => true,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
        ];
    }
}
