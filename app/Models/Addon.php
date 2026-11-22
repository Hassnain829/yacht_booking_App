<?php

namespace App\Models;

use App\Enums\AddonUnit;
use Database\Factories\AddonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** An extra on a booking, e.g. champagne (PLAN §6.3). Deactivate instead of deleting: old bookings keep a snapshot. */
#[Fillable(['slug', 'name', 'description', 'icon', 'price_cents', 'unit', 'max_quantity', 'is_active', 'sort'])]
#[Hidden(['id'])]
class Addon extends Model
{
    /** @use HasFactory<AddonFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => AddonUnit::class,
            'is_active' => 'boolean',
        ];
    }
}
