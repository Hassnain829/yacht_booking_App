<?php

namespace App\Http\Resources\Checkout;

use App\Http\Resources\ApiResource;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteLine;
use Illuminate\Http\Request;

/**
 * The checkout page's live price (PLAN 4.5), straight from QuoteService: every line, the discount, tax, gratuity
 * and the total, paid in full (D22). `promo_rejection` explains a code that gives nothing on this booking (its
 * minimum amount). Money in cents.
 */
class CheckoutQuoteResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Quote $quote */
        $quote = $this->resource;
        $line = fn (QuoteLine $line) => [
            'type' => $line->type->value,
            'key' => $line->key,
            'label' => $line->label,
            'description' => $line->description,
            'amount_cents' => $line->amountCents,
        ];

        return [
            'lines' => array_map($line, $quote->lines),
            'adjustments' => array_map($line, $quote->adjustments),
            'charter_cents' => $quote->charterCents,
            'captain_cents' => $quote->captainCents,
            'subtotal_cents' => $quote->subtotalCents,
            'discount_cents' => $quote->discountCents,
            'tax_cents' => $quote->taxCents,
            'gratuity_pct' => $quote->gratuityPct,
            'gratuity_cents' => $quote->gratuityCents,
            'total_cents' => $quote->totalCents,
            'promo_code' => $quote->promoCode,
            'promo_rejection' => $quote->promoRejection,
        ];
    }
}
