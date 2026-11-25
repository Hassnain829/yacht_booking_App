<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A trip on a boat (PLAN §6.5). URLs use the `ulid`; people quote the `reference` (CYB-7K3P9Q). Times in UTC.
 *
 * Only the customer's own words are fillable. The boat, the customer, the status, the money and the snapshots
 * are set by the booking services (forceFill) — never from a request (CLAUDE.md security standard).
 */
#[Fillable(['occasion', 'customer_notes', 'contact_first_name', 'contact_last_name', 'contact_email', 'contact_phone', 'heard_from'])]
#[Hidden(['id', 'customer_id', 'boat_id', 'experience_id', 'captain_id', 'promo_code_id'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasUlids;

    /** No 0/O, 1/I/L: easy to read out on the phone. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** A new unused reference, e.g. CYB-7K3P9Q. */
    public static function newReference(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }
            $reference = 'CYB-'.$code;
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<Boat, $this> */
    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class)->withTrashed();
    }

    /** @return BelongsTo<Experience, $this> */
    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class)->withTrashed();
    }

    /** @return BelongsTo<Captain, $this> */
    public function captain(): BelongsTo
    {
        return $this->belongsTo(Captain::class)->withTrashed();
    }

    /** @return HasMany<BookingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class)->orderBy('sort');
    }

    /** @return HasMany<BookingStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderBy('id');
    }

    /**
     * Bookings that take the boat's time now: confirmed or past trips, and holds that haven't run out.
     * Cancelled and expired ones free it.
     *
     * @param  Builder<self>  $query
     */
    public function scopeTakingTime(Builder $query, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();
        $expiring = array_map(fn (BookingStatus $s) => $s->value, array_filter(BookingStatus::takingTime(), fn (BookingStatus $s) => $s->expires()));
        $lasting = array_map(fn (BookingStatus $s) => $s->value, array_filter(BookingStatus::takingTime(), fn (BookingStatus $s) => ! $s->expires()));

        $query->where(fn (Builder $q) => $q
            ->whereIn('status', $lasting)
            ->orWhere(fn (Builder $open) => $open->whereIn('status', $expiring)
                ->where(fn (Builder $time) => $time->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', $now))));
    }

    public function takesTime(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return in_array($this->status, BookingStatus::takingTime(), true)
            && (! $this->status->expires() || $this->hold_expires_at === null || $this->hold_expires_at->greaterThan($now));
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'hours' => 'integer',
            'guests' => 'integer',
            'hold_expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'captain_assigned_at' => 'immutable_datetime',
            'captain_notified_at' => 'immutable_datetime',
            'captain_acknowledged_at' => 'immutable_datetime',
            'policy_snapshot' => 'array',
            'pricing_snapshot' => 'array',
        ];
    }
}
