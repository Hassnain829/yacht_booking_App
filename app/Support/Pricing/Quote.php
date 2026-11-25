<?php

namespace App\Support\Pricing;

/**
 * A price worked out by QuoteService (PLAN §7.1). All money in cents. The whole total is paid at checkout (D22).
 *
 * `lines` are the subtotal lines (charter, captain, the experience's paid extras, add-ons, fuel & cleaning); `adjustments` are the
 * discount, tax and gratuity lines that follow them. `items()` gives both, in booking_items order.
 * `pricingSnapshot` keeps the rates used, so a booking never changes when prices change, and a trip extension
 * (Phase 8) is priced with the rates of the booking.
 */
final readonly class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     * @param  list<QuoteLine>  $adjustments
     * @param  array<string, mixed>  $pricingSnapshot
     */
    public function __construct(
        public int $hours,
        public int $guests,
        public bool $weekend,
        public int $hourlyRateCents,
        public ?int $fixedPriceCents,
        public array $lines,
        public array $adjustments,
        public int $charterCents,
        public int $captainCents,
        public int $subtotalCents,
        public ?string $promoCode,
        public ?string $promoRejection,
        public int $discountCents,
        public int $taxCents,
        public int $gratuityPct,
        public int $gratuityCents,
        public int $totalCents,
        public array $pricingSnapshot,
    ) {}

    /**
     * Every line a booking stores: the subtotal lines, then discount, tax and gratuity (the ones that aren't zero).
     *
     * @return list<QuoteLine>
     */
    public function items(): array
    {
        return [...$this->lines, ...$this->adjustments];
    }

    /**
     * The price as the booking card and checkout show it (wrapped in App\Http\Resources\QuoteResource).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hours' => $this->hours,
            'guests' => $this->guests,
            'weekend' => $this->weekend,
            'hourly_rate_cents' => $this->hourlyRateCents,
            'fixed_price_cents' => $this->fixedPriceCents,
            'lines' => array_map(fn (QuoteLine $line) => $line->toArray(), $this->lines),
            'adjustments' => array_map(fn (QuoteLine $line) => $line->toArray(), $this->adjustments),
            'subtotal_cents' => $this->subtotalCents,
            'promo_code' => $this->promoCode,
            'promo_rejection' => $this->promoRejection,
            'discount_cents' => $this->discountCents,
            'tax_cents' => $this->taxCents,
            'gratuity_pct' => $this->gratuityPct,
            'gratuity_cents' => $this->gratuityCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
