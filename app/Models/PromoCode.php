<?php

namespace App\Models;

use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Support\Pricing\PromoTerms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A code guests type at checkout (PLAN §6.5), e.g. WELCOME10. A percentage `value` is in basis points (1000 = 10%),
 * a fixed one in cents. Whether a guest may use it now is PromoService's job; QuoteService only sees the terms.
 * Managed in Admin → Promo codes (4.8); seeded with the mockup's two codes.
 */
#[Fillable(['code', 'type', 'value', 'applies_to', 'min_subtotal_cents', 'max_discount_cents', 'starts_at', 'ends_at', 'max_redemptions', 'max_per_user', 'is_active'])]
#[Hidden(['id'])]
class PromoCode extends Model
{
    use HasUlids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return HasMany<PromoRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }

    public function terms(): PromoTerms
    {
        return new PromoTerms($this->code, $this->type, (int) $this->value, $this->applies_to, $this->min_subtotal_cents, $this->max_discount_cents);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => PromoType::class,
            'applies_to' => PromoAppliesTo::class,
            'value' => 'integer',
            'min_subtotal_cents' => 'integer',
            'max_discount_cents' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'max_redemptions' => 'integer',
            'max_per_user' => 'integer',
            'redemptions_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
