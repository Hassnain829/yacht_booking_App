<?php

namespace Database\Factories;

use App\Models\Experience;
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
            'durations' => [2, 3, 4],
            'time_slots' => ['10:00', '14:00', '18:00'],
            'package_fee_cents' => 0,
            'is_active' => true,
        ];
    }
}
