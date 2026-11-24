<?php

namespace App\Models;

use App\Enums\BookingItemType;
use App\Enums\Payee;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One price line of a booking, copied from the quote (QuoteLine) when it is made (PLAN §6.5). Written only by
 * the booking services (forceFill), so nothing is fillable.
 */
#[Hidden(['id', 'booking_id', 'addon_id', 'booking_extension_id'])]
class BookingItem extends Model
{
    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Addon, $this> */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class)->withTrashed();
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => BookingItemType::class,
            'payee' => Payee::class,
            'quantity' => 'integer',
            'unit_amount_cents' => 'integer',
            'amount_cents' => 'integer',
            'sort' => 'integer',
        ];
    }
}
