<?php

namespace App\Models;

use App\Enums\PaymentIntentStatus;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay a booking in full (PLAN §6.6, D22), with the provider's intent. Card data stays with the
 * provider (PCI SAQ-A): only the brand and last 4 digits are kept, for display. Nothing here is fillable: the
 * checkout actions set every field (forceFill).
 */
#[Hidden(['id', 'booking_id', 'user_id', 'provider_intent_id', 'provider_charge_id', 'provider_payment_method_id', 'idempotency_key', 'meta'])]
class Payment extends Model
{
    use HasUlids;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What of this payment can still go back to the card. */
    public function refundableCents(): int
    {
        return $this->status === PaymentIntentStatus::Succeeded ? max(0, $this->amount_cents - $this->amount_refunded_cents) : 0;
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => PaymentIntentStatus::class,
            'amount_cents' => 'integer',
            'fee_cents' => 'integer',
            'amount_refunded_cents' => 'integer',
            'save_card' => 'boolean',
            'paid_at' => 'immutable_datetime',
            'meta' => 'array',
        ];
    }
}
