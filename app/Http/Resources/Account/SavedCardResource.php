<?php

namespace App\Http\Resources\Account;

use App\Http\Resources\ApiResource;
use App\Models\CustomerPaymentMethod;
use Illuminate\Http\Request;

/** A saved card (5.3): what the account page shows — never the provider's id for it. */
class SavedCardResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CustomerPaymentMethod $card */
        $card = $this->resource;

        return [
            'id' => $this->publicId(),
            'brand' => $card->brandName(),
            'last4' => $card->last4,
            'expires' => $card->expires(),
            'is_default' => $card->is_default,
        ];
    }
}
