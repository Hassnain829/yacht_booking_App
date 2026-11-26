<?php

namespace App\Services;

use App\Exceptions\QuoteException;
use App\Models\Addon;
use App\Models\Booking;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteRequest;

/**
 * A held booking priced with the choices the guest is trying out on the checkout page (add-ons, gratuity) and the
 * promo code applied to it — QuoteService's answer, nothing saved (PrepareCheckoutPayment saves it on "Pay").
 */
class CheckoutPricing
{
    public function __construct(private QuoteService $quotes) {}

    /**
     * @param  list<Addon>  $addons
     *
     * @throws QuoteException when the choices no longer fit the boat
     */
    public function quote(Booking $booking, array $addons, int $gratuityPct): Quote
    {
        $boat = $booking->boat()->with(['experiences', 'cancellationPolicy'])->firstOrFail();

        return $this->quotes->quote(new QuoteRequest(
            boat: $boat,
            experience: $booking->experience_id ? $booking->experience : null,
            startsAt: $booking->starts_at,
            hours: $booking->hours,
            guests: $booking->guests,
            addons: $addons,
            promo: $booking->promo_code_id ? $booking->promoCode?->terms() : null,
            gratuityPct: $gratuityPct,
        ));
    }

    /**
     * What each gratuity option comes to on this booking (it is a share of the charter and captain, so the add-ons
     * don't change it), from QuoteService — for the gratuity tiles.
     *
     * @param  list<int>  $options
     * @return array<int, int> percent => cents
     */
    public function gratuityAmounts(Booking $booking, array $options): array
    {
        $amounts = [];
        foreach ($options as $pct) {
            $amounts[$pct] = $this->quote($booking, [], $pct)->gratuityCents;
        }

        return $amounts;
    }

    /** The add-ons on the booking now, in its order. @return list<Addon> */
    public function chosenAddons(Booking $booking): array
    {
        $ids = $booking->items()->whereNotNull('addon_id')->whereNull('booking_extension_id')->orderBy('sort')->pluck('addon_id')->all();

        return Addon::query()->whereIn('id', $ids)->where('is_active', true)->get()
            ->sortBy(fn (Addon $addon) => array_search($addon->getKey(), $ids, true))->values()->all();
    }
}
