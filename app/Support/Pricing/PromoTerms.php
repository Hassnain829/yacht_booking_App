<?php

namespace App\Support\Pricing;

use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Support\Money;

/**
 * What a promo code takes off, as QuoteService applies it (PLAN §7.1). Whether the code may be used at all
 * (dates, redemption limits, per-customer limit) is checked before, when the code is looked up (4.4); these
 * are only the money terms. A percentage is in basis points (1000 = 10%), a fixed amount in cents.
 */
final readonly class PromoTerms
{
    public function __construct(
        public string $code,
        public PromoType $type,
        public int $value,
        public PromoAppliesTo $appliesTo,
        public ?int $minSubtotalCents = null,
        public ?int $maxDiscountCents = null,
    ) {}

    /** e.g. WELCOME10: 10% off the yacht charter. */
    public static function percent(string $code, int $basisPoints, PromoAppliesTo $appliesTo = PromoAppliesTo::Charter, ?int $minSubtotalCents = null, ?int $maxDiscountCents = null): self
    {
        return new self(strtoupper(trim($code)), PromoType::Percent, $basisPoints, $appliesTo, $minSubtotalCents, $maxDiscountCents);
    }

    /** e.g. SUNSET50: $50 off the booking. */
    public static function fixed(string $code, int $amountCents, PromoAppliesTo $appliesTo = PromoAppliesTo::Subtotal, ?int $minSubtotalCents = null, ?int $maxDiscountCents = null): self
    {
        return new self(strtoupper(trim($code)), PromoType::Fixed, $amountCents, $appliesTo, $minSubtotalCents, $maxDiscountCents);
    }

    /** "10% off yacht charter", "$50 off your booking". */
    public function label(): string
    {
        $amount = $this->type === PromoType::Percent ? Money::percent($this->value) : Money::format($this->value);

        return $amount.' off '.($this->appliesTo === PromoAppliesTo::Charter ? 'yacht charter' : 'your booking');
    }

    /**
     * @return array{code: string, type: string, value: int, applies_to: string, min_subtotal_cents: ?int, max_discount_cents: ?int}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => $this->value,
            'applies_to' => $this->appliesTo->value,
            'min_subtotal_cents' => $this->minSubtotalCents,
            'max_discount_cents' => $this->maxDiscountCents,
        ];
    }
}
