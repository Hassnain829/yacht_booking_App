<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One status change of a booking (PLAN §7.3): from → to, who did it and why. Written by the booking service that
 * changes the status (CLAUDE.md: one service method per status change, which also writes the history).
 */
#[Hidden(['id', 'booking_id', 'actor_id'])]
class BookingStatusHistory extends Model
{
    protected $table = 'booking_status_history';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'from_status' => BookingStatus::class,
            'to_status' => BookingStatus::class,
        ];
    }
}
