<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A chargeback the card's bank opened (PLAN §6.6), as the provider's webhook reports it. Answering it (evidence)
 * happens in the provider's dashboard; the admin sees it on the booking. Set only by the webhook (forceFill).
 */
#[Hidden(['id', 'booking_id', 'payment_id', 'provider_dispute_id'])]
class Dispute extends Model
{
    use HasUlids;

    /** Stripe's dispute statuses that are over. */
    public const CLOSED = ['won', 'lost', 'warning_closed', 'charge_refunded', 'prevented'];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::CLOSED, true);
    }

    /** "needs_response" → "Needs response". */
    public function statusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'evidence_due_by' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
