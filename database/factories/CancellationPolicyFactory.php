<?php

namespace Database\Factories;

use App\Models\CancellationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The three policies (PLAN §7.4, the mockup's rules). Moderate by default.
 *
 * @extends Factory<CancellationPolicy>
 */
class CancellationPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'moderate',
            'name' => 'Moderate',
            'points' => ['Free cancellations until 5 days before the booking start time.'],
            'rules' => [['min_hours_before' => 120, 'refund_pct' => 100], ['min_hours_before' => 72, 'refund_pct' => 50], ['min_hours_before' => 0, 'refund_pct' => 0]],
            'is_active' => true,
        ];
    }

    public function flexible(): static
    {
        return $this->state([
            'slug' => 'flexible', 'name' => 'Flexible', 'badge' => 'Recommended',
            'rules' => [['min_hours_before' => 24, 'refund_pct' => 100], ['min_hours_before' => 0, 'refund_pct' => 0]],
        ]);
    }

    public function strict(): static
    {
        return $this->state([
            'slug' => 'strict', 'name' => 'Strict',
            'rules' => [['min_hours_before' => 720, 'refund_pct' => 100], ['min_hours_before' => 360, 'refund_pct' => 50], ['min_hours_before' => 0, 'refund_pct' => 0]],
        ]);
    }
}
