<?php

namespace App\Support\Payments;

/** A card the provider keeps for a customer: its id there, brand, last 4 digits and expiry (never the number). */
final readonly class SavedCard
{
    public function __construct(
        public string $paymentMethodId,
        public string $brand,
        public string $last4,
        public int $expMonth,
        public int $expYear,
    ) {}
}
