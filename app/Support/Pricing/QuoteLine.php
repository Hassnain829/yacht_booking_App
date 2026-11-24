<?php

namespace App\Support\Pricing;

use App\Enums\BookingItemType;
use App\Enums\Payee;

/**
 * One price line of a quote, shaped like a booking_items row (PLAN §6.5): what it is, the text the customer
 * reads, quantity × unit amount, and who the money goes to (§7.5). A discount line is negative.
 */
final readonly class QuoteLine
{
    public function __construct(
        public BookingItemType $type,
        public string $key,
        public string $label,
        public string $description,
        public int $quantity,
        public int $unitAmountCents,
        public int $amountCents,
        public Payee $payee,
        public ?int $addonId = null,
    ) {}

    /**
     * What the customer sees (never the add-on's numeric id).
     *
     * @return array{type: string, key: string, label: string, description: string, quantity: int, unit_amount_cents: int, amount_cents: int}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount_cents' => $this->unitAmountCents,
            'amount_cents' => $this->amountCents,
        ];
    }
}
