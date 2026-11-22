<?php

namespace Database\Factories;

use App\Enums\AmenityGroup;
use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(2, true)),
            'group' => AmenityGroup::Feature,
        ];
    }

    public function highlight(): static
    {
        return $this->state(['group' => AmenityGroup::Highlight]);
    }
}
