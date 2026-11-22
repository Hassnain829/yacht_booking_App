<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A checked promo code (POST /checkout/promo): only the discount rule — never other codes, limits or
 * usage counts. Phase 1: the code comes from the demo catalog (an array); Phase 5: the promo_codes table.
 */
class PromoCodeResource extends ApiResource
{
    /**
     * @return array{code: string, type: string, value: int, label: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => (string) $this->resource['code'],
            'type' => (string) $this->resource['type'],
            'value' => (int) $this->resource['value'],
            'label' => (string) $this->resource['label'],
        ];
    }
}
