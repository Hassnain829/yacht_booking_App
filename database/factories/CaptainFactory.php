<?php

namespace Database\Factories;

use App\Enums\LicenseType;
use App\Models\Captain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An active captain with a USCG Master license valid for a year.
 *
 * @extends Factory<Captain>
 */
class CaptainFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Capt. '.fake()->firstName().' '.fake()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '(312) 555-'.fake()->numerify('####'),
            'license_type' => LicenseType::Master,
            'license_number' => 'ML-'.fake()->numerify('#######'),
            'license_expires_on' => now()->addYear()->toDateString(),
            'max_passengers_licensed' => 49,
            'years_experience' => 10,
            'languages' => ['English'],
            'pay_rate_cents' => 4500,
            'is_active' => true,
            'is_public' => true,
        ];
    }

    /** An OUPV ("six-pack") captain: at most 6 passengers. */
    public function oupv(): static
    {
        return $this->state(['license_type' => LicenseType::Oupv, 'max_passengers_licensed' => 6]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
