<?php

namespace App\Http\Resources\Checkout;

use App\Http\Resources\ApiResource;
use App\Models\PromoCode;
use Illuminate\Http\Request;

/** A promo code applied at checkout: its code and what it takes off. Never the list of codes or their limits. */
class PromoResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PromoCode $promo */
        $promo = $this->resource;

        return [
            'code' => $promo->code,
            'label' => $promo->terms()->label(),
        ];
    }
}
