<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A promo code used on a paid booking (PLAN §6.5). Written by ConfirmPaidBooking; nothing is fillable. */
class PromoRedemption extends Model
{
    /** @return BelongsTo<PromoCode, $this> */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class)->withTrashed();
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return ['discount_cents' => 'integer'];
    }
}
