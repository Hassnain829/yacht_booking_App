<?php

namespace Database\Factories;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'amount_cents' => 50000,
            'fee_cents' => 0,
            'reason' => RefundReason::CustomerCancel,
            'status' => RefundStatus::Requested,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(['status' => RefundStatus::Succeeded, 'refunded_at' => now()]);
    }
}
