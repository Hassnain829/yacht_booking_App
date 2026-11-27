<?php

namespace App\Services\Payments;

use App\Models\CustomerPaymentMethod;
use App\Models\User;
use App\Support\Payments\SavedCard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A guest's saved cards (PLAN 5.3). The provider keeps the cards; we keep a display cache (brand, last 4, expiry)
 * in customer_payment_methods. Cards are saved by paying with "Save this card" or from the account's "Add a card";
 * the first one becomes the default. Only the cards of the provider in use are offered.
 */
class SavedCards
{
    public function __construct(private PaymentGateway $gateway) {}

    /** The guest's customer at the provider, created the first time it is needed (Stripe's id is kept). */
    public function customerId(User $user): string
    {
        if ($this->gateway->name() !== 'stripe') {
            return $this->gateway->createCustomer($user);
        }
        if (is_string($user->stripe_customer_id) && str_starts_with($user->stripe_customer_id, 'cus_')) {
            return $user->stripe_customer_id;
        }
        $id = $this->gateway->createCustomer($user);
        $user->forceFill(['stripe_customer_id' => $id])->save();

        return $id;
    }

    /**
     * The guest's cards for the provider in use, the default first.
     *
     * @return Collection<int, CustomerPaymentMethod>
     */
    public function for(User $user): Collection
    {
        return $user->paymentMethods()->forProvider($this->gateway->name())->get();
    }

    /** Keep a card the provider saved (again: it is updated, not doubled). The first card is the default. */
    public function remember(User $user, SavedCard $card): CustomerPaymentMethod
    {
        return DB::transaction(function () use ($user, $card) {
            $saved = CustomerPaymentMethod::query()->forProvider($this->gateway->name())->where('provider_payment_method_id', $card->paymentMethodId)->first()
                ?? (new CustomerPaymentMethod)->forceFill(['user_id' => $user->getKey(), 'provider' => $this->gateway->name(), 'provider_payment_method_id' => $card->paymentMethodId]);
            if ($saved->exists && $saved->user_id !== $user->getKey()) {
                return $saved; // never move another guest's card
            }
            $saved->forceFill([
                'brand' => mb_substr(strtolower($card->brand), 0, 20),
                'last4' => $card->last4,
                'exp_month' => $card->expMonth,
                'exp_year' => $card->expYear,
                'is_default' => $saved->exists ? $saved->is_default : ! $this->for($user)->contains('is_default', true),
            ])->save();

            return $saved;
        });
    }

    public function makeDefault(CustomerPaymentMethod $card): void
    {
        DB::transaction(function () use ($card) {
            CustomerPaymentMethod::query()->where('user_id', $card->user_id)->where('provider', $card->provider)->where('id', '!=', $card->getKey())->update(['is_default' => false]);
            $card->forceFill(['is_default' => true])->save();
        });
    }

    /** Forget a card at the provider and here; the next one becomes the default. */
    public function remove(CustomerPaymentMethod $card): void
    {
        $this->gateway->detach($card->provider_payment_method_id);
        DB::transaction(function () use ($card) {
            $card->delete();
            if ($card->is_default) {
                CustomerPaymentMethod::query()->where('user_id', $card->user_id)->where('provider', $card->provider)->latest('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });
    }

    /** Every card of a deleted account. */
    public function removeAll(User $user): void
    {
        foreach ($user->paymentMethods()->get() as $card) {
            if ($card->provider === $this->gateway->name()) {
                $this->gateway->detach($card->provider_payment_method_id);
            }
            $card->delete();
        }
    }
}
