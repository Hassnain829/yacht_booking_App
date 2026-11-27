<?php

namespace App\Models;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money going back to a guest's card (PLAN §7.4, D23). A guest's cancellation creates one waiting for the admin;
 * the admin approves (it goes to the provider), changes the amount or declines it. Nothing here is fillable:
 * RefundService sets every field (forceFill), never a request.
 */
#[Hidden(['id', 'booking_id', 'payment_id', 'provider_refund_id', 'idempotency_key', 'requested_by', 'decided_by'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
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

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    /**
     * Guests' refunds waiting for the admin.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAwaitingApproval(Builder $query): void
    {
        $query->where('status', RefundStatus::Requested);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'reason' => RefundReason::class,
            'amount_cents' => 'integer',
            'fee_cents' => 'integer',
            'policy_pct' => 'integer',
            'decided_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
