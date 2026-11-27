<?php

namespace Database\Factories;

use App\Models\CustomerPaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A saved card of the fake gateway (tests): Visa •••• 4242, expiring in two years.
 *
 * @extends Factory<CustomerPaymentMethod>
 */
class CustomerPaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'fake',
            'provider_payment_method_id' => 'fake_pm_'.Str::lower((string) Str::ulid()),
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => (int) now()->addYears(2)->format('Y'),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
