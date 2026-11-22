<?php

namespace Database\Factories;

use App\Models\Harbor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Harbor>
 */
class HarborFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word()).' Harbor';

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'area' => 'Chicago lakefront',
            'map_query' => $name.' Chicago',
            'is_active' => true,
        ];
    }
}
