<?php

namespace App\Support\Payments;

use App\Enums\PaymentIntentStatus;

/**
 * A provider's payment intent as we read it: its id, the secret the browser needs to confirm it (never stored), its
 * status, the amount it is for and — once paid — the card's brand and last 4 digits for display.
 */
final readonly class Intent
{
    public function __construct(
        public string $id,
        public PaymentIntentStatus $status,
        public int $amountCents,
        public ?string $clientSecret = null,
        public ?string $chargeId = null,
        public ?string $cardBrand = null,
        public ?string $cardLast4 = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}
}
