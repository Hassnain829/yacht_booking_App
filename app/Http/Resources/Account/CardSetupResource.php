<?php

namespace App\Http\Resources\Account;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * What the browser needs to add a card (5.3): with Stripe, the SetupIntent's client secret (never stored on our
 * side), where to post the finished setup and Stripe's return URL; with the fake gateway, its test card endpoint.
 */
class CardSetupResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->resource['provider'],
            'client_secret' => $this->resource['client_secret'] ?? null,
            'store_url' => $this->resource['provider'] === 'stripe' ? route('account.cards.store') : null,
            'return_url' => $this->resource['provider'] === 'stripe' ? route('account.cards.added') : null,
            'test_card_url' => $this->resource['provider'] === 'fake' ? route('account.cards.test-card') : null,
        ];
    }
}
