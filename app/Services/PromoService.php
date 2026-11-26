<?php

namespace App\Services;

use App\Exceptions\QuoteException;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Promo codes at checkout (PLAN §6.5, 4.5): whether a guest may use a code now — shown, inside its dates, not used
 * up, not past the per-guest limit — and the redemption once the booking is paid. QuoteService applies the money
 * terms (and a code's minimum booking amount). Codes are typed in any case and kept in upper case.
 */
class PromoService
{
    /**
     * @throws QuoteException on `code`
     */
    public function check(string $code, ?User $user, ?CarbonImmutable $now = null): PromoCode
    {
        $now ??= CarbonImmutable::now();
        $code = strtoupper(trim($code));
        $promo = preg_match('/^[A-Z0-9]{1,30}$/', $code) ? PromoCode::query()->where('code', $code)->first() : null;

        if ($promo === null || ! $promo->is_active || ($promo->starts_at && $promo->starts_at->greaterThan($now)) || ($promo->ends_at && $promo->ends_at->lessThan($now))) {
            throw QuoteException::on('code', 'That promo code is not valid.');
        }
        if ($promo->max_redemptions !== null && $promo->redemptions_count >= $promo->max_redemptions) {
            throw QuoteException::on('code', "{$promo->code} has been used up.");
        }
        if ($user && $promo->max_per_user !== null
            && PromoRedemption::query()->where('promo_code_id', $promo->getKey())->where('user_id', $user->getKey())->count() >= $promo->max_per_user) {
            throw QuoteException::on('code', "You've already used {$promo->code}.");
        }

        return $promo;
    }

    /** Inside the transaction that confirms a paid booking: one redemption, the code's count up by one. */
    public function redeem(PromoCode $promo, Booking $booking, int $discountCents): void
    {
        if (PromoRedemption::query()->where('booking_id', $booking->getKey())->exists()) {
            return; // the same payment reported twice
        }
        (new PromoRedemption)->forceFill([
            'promo_code_id' => $promo->getKey(),
            'booking_id' => $booking->getKey(),
            'user_id' => $booking->customer_id,
            'discount_cents' => $discountCents,
        ])->save();
        PromoCode::query()->whereKey($promo->getKey())->increment('redemptions_count');
    }
}
