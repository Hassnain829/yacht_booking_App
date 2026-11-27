<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CustomerPaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A card a guest saved with the payment provider (PLAN §6.6, 5.3). Only a display cache: the brand, the last 4
 * digits and the expiry — the card itself stays with Stripe (PCI SAQ-A). Set by SavedCards (forceFill).
 */
#[Hidden(['id', 'user_id', 'provider_payment_method_id'])]
class CustomerPaymentMethod extends Model
{
    /** @use HasFactory<CustomerPaymentMethodFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The cards of the payment provider in use (a card saved with the fake gateway can't pay through Stripe).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    /** "Visa •••• 4242". */
    public function label(): string
    {
        return $this->brandName().' •••• '.$this->last4;
    }

    public function brandName(): string
    {
        return match (strtolower($this->brand)) {
            'amex', 'american express' => 'Amex',
            'mastercard' => 'Mastercard',
            'visa' => 'Visa',
            'discover' => 'Discover',
            default => ucfirst(strtolower($this->brand)),
        };
    }

    /** "12/28". */
    public function expires(): string
    {
        return sprintf('%02d/%02d', $this->exp_month, $this->exp_year % 100);
    }

    /** Past the end of its expiry month (Chicago time). */
    public function isExpired(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now(config('app.display_timezone'));
        $lastDay = CarbonImmutable::create($this->exp_year, $this->exp_month, 1, 0, 0, 0, config('app.display_timezone'))->endOfMonth();

        return $now->greaterThan($lastDay);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
