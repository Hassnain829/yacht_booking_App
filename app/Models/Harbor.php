<?php

namespace App\Models;

use Database\Factories\HarborFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Where guests board (PLAN §6.2). */
#[Fillable(['slug', 'name', 'area', 'address', 'lat', 'lng', 'map_query', 'notes', 'image_path', 'is_active', 'sort'])]
#[Hidden(['id'])]
class Harbor extends Model
{
    /** @use HasFactory<HarborFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Boat, $this> */
    public function boats(): HasMany
    {
        return $this->hasMany(Boat::class);
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
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }
}
