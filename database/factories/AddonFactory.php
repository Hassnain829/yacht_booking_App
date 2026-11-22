<?php

namespace Database\Factories;

use App\Enums\AddonUnit;
use App\Models\Addon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'icon' => 'gift',
            'price_cents' => 2500,
            'unit' => AddonUnit::Flat,
            'max_quantity' => 1,
            'is_active' => true,
        ];
    }

    public function perGuest(int $cents = 1500): static
    {
        return $this->state(['unit' => AddonUnit::PerGuest, 'price_cents' => $cents]);
    }
}
