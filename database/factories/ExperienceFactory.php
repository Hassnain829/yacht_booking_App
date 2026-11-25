<?php

namespace Database\Factories;

use App\Models\Experience;
use App\Support\StartTimes;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true)).' Cruise';

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'category' => 'sightseeing',
            'icon' => 'sunset',
            'tagline' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'includes' => ['Ice, water & cups', 'Bluetooth playlist'],
            'time_slots' => StartTimes::between('10:00', '18:00'), // its departure window
            'is_active' => true,
        ];
    }
}
