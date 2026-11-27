<?php

namespace App\Support\Payments;

use App\Enums\PaymentIntentStatus;

/**
 * A provider's payment intent as we read it: its id, the secret the browser needs to confirm it (never stored), its
 * status, the amount it is for and — once paid — the card (brand, last 4 digits, expiry, the provider's id for it)
 * for display and saving, and the provider's processing fee when it is known.
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
        public ?string $paymentMethodId = null,
        public ?int $cardExpMonth = null,
        public ?int $cardExpYear = null,
        public ?int $feeCents = null,
    ) {}

    /** The paying card, when the provider gave enough to save it. */
    public function card(): ?SavedCard
    {
        if ($this->paymentMethodId === null || $this->cardLast4 === null || $this->cardExpMonth === null || $this->cardExpYear === null) {
            return null;
        }

        return new SavedCard($this->paymentMethodId, (string) $this->cardBrand, $this->cardLast4, $this->cardExpMonth, $this->cardExpYear);
    }
}
