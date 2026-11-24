<?php

namespace Database\Factories;

use App\Models\Boat;
use App\Models\BoatAvailabilityRule;
use App\Support\StartTimes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Departures every half hour 8:00 AM – 6:00 PM on the given weekday (Chicago time).
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
            'start_times' => StartTimes::defaults(),
        ];
    }
}
