<?php

namespace App\Models;

use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Models\Contracts\Trashable;
use App\Support\Pricing\PromoTerms;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A code guests type at checkout (PLAN §6.5), e.g. WELCOME10. A percentage `value` is in basis points (1000 = 10%),
 * a fixed one in cents. Whether a guest may use it now is PromoService's job; QuoteService only sees the terms.
 * Managed in Admin → Promo codes (4.8): in the Trash a code stops working; one used on bookings can't be deleted
 * permanently (the bookings keep their discount).
 */
#[Fillable(['code', 'type', 'value', 'applies_to', 'min_subtotal_cents', 'max_discount_cents', 'starts_at', 'ends_at', 'max_redemptions', 'max_per_user', 'is_active'])]
#[Hidden(['id'])]
class PromoCode extends Model implements Trashable
{
    use HasUlids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
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

    /** "Active", "Scheduled", "Ended", "Used up" or "Hidden", for the admin list. */
    public function state(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return match (true) {
            ! $this->is_active => 'Hidden',
            $this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions => 'Used up',
            $this->ends_at !== null && $this->ends_at->lessThan($now) => 'Ended',
            $this->starts_at !== null && $this->starts_at->greaterThan($now) => 'Scheduled',
            default => 'Active',
        };
    }

    public function trashLabel(): string
    {
        return 'promo code';
    }

    public function trashTitle(): string
    {
        return $this->code;
    }

    /** Nothing blocks the Trash: a code in the Trash simply stops working at checkout. */
    public function trashBlockers(): array
    {
        return [];
    }

    public function permanentDeleteBlockers(): array
    {
        $used = $this->redemptions()->count();

        return $used === 0 ? [] : [trans_choice(
            '{1} It was used on 1 booking, which keeps its discount. Keep it in the Trash for the records.|[2,*] It was used on :count bookings, which keep their discount. Keep it in the Trash for the records.',
            $used,
        )];
    }

    public function ownFiles(): array
    {
        return [];
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
