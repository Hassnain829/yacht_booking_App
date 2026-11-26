<?php

namespace App\Services;

use App\Enums\BookingItemType;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\User;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteLine;

/**
 * The one way a booking's status changes (PLAN §7.3, CLAUDE.md): every change writes its history line. Also puts
 * a QuoteService answer on a booking (money, lines, snapshot), so the booking always matches what was priced.
 */
class BookingService
{
    /**
     * @param  array<string, mixed>  $attributes  set together with the status (e.g. confirmed_at)
     */
    public function transition(Booking $booking, BookingStatus $to, ?User $actor = null, ?string $note = null, array $attributes = []): Booking
    {
        $from = $booking->exists ? $booking->getOriginal('status') : null;
        $booking->forceFill(['status' => $to] + $attributes)->save();

        (new BookingStatusHistory)->forceFill([
            'booking_id' => $booking->getKey(),
            'from_status' => $from instanceof BookingStatus ? $from->value : $from,
            'to_status' => $to->value,
            'actor_id' => $actor?->getKey(),
            'note' => $note === null ? null : mb_substr($note, 0, 500),
            'created_at' => now(),
        ])->save();

        return $booking;
    }

    /** The quote's money, its lines (replacing the old ones) and its pricing snapshot. The booking must be saved. */
    public function applyQuote(Booking $booking, Quote $quote): void
    {
        $booking->forceFill([
            'hours' => $quote->hours,
            'guests' => $quote->guests,
            'subtotal_cents' => $quote->subtotalCents,
            'discount_cents' => $quote->discountCents,
            'tax_cents' => $quote->taxCents,
            'gratuity_bp' => $quote->gratuityPct * 100,
            'gratuity_cents' => $quote->gratuityCents,
            'total_cents' => $quote->totalCents,
            'pricing_snapshot' => $quote->pricingSnapshot,
        ])->save();

        $booking->items()->whereNull('booking_extension_id')->delete();
        foreach ($quote->items() as $sort => $line) {
            /** @var QuoteLine $line */
            $booking->items()->forceCreate([
                'type' => $line->type,
                'addon_id' => $line->type === BookingItemType::Addon ? $line->addonId : null,
                'label' => mb_substr($line->label, 0, 120),
                'description' => mb_substr($line->description, 0, 255),
                'quantity' => $line->quantity,
                'unit_amount_cents' => $line->unitAmountCents,
                'amount_cents' => $line->amountCents,
                'payee' => $line->payee,
                'sort' => $sort,
            ]);
        }
        $booking->unsetRelation('items');
    }
}
