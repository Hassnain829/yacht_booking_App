<?php

namespace App\Http\Resources\BookingCard;

use App\Http\Resources\ApiResource;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteLine;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The live price on the booking card (GET /yachts/{slug}/quote), straight from QuoteService: every line, the tax
 * and the total, paid in full at checkout (D22). `estimate` = no date picked yet (a weekday price).
 * `free_cancellation_until` comes from the boat's policy once a start time is picked. Money in cents.
 */
class QuoteResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Quote $quote */
        $quote = $this->resource['quote'];
        /** @var ?CarbonImmutable $freeUntil */
        $freeUntil = $this->resource['free_until'];
        $line = fn (QuoteLine $line) => [
            'type' => $line->type->value,
            'key' => $line->key,
            'label' => $line->label,
            'description' => $line->description,
            'amount_cents' => $line->amountCents,
        ];

        return [
            'estimate' => (bool) $this->resource['estimate'],
            'hours' => $quote->hours,
            'guests' => $quote->guests,
            'weekend' => $quote->weekend,
            'hourly_rate_cents' => $quote->hourlyRateCents,
            'lines' => array_map($line, $quote->lines),
            'adjustments' => array_map($line, $quote->adjustments),
            'subtotal_cents' => $quote->subtotalCents,
            'tax_cents' => $quote->taxCents,
            'total_cents' => $quote->totalCents,
            'policy' => $this->resource['policy'],
            'free_cancellation_until' => self::iso($freeUntil),
            'free_cancellation_label' => $freeUntil?->setTimezone(config('app.display_timezone'))->format('M j, g:i A'),
        ];
    }
}
