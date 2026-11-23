<?php

namespace App\Models;

use App\Enums\AddonUnit;
use App\Models\Contracts\Trashable;
use Database\Factories\AddonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An extra on a booking, e.g. champagne (PLAN §6.3). Deleting moves it to the Trash (3.4); old bookings keep a snapshot. */
#[Fillable(['slug', 'name', 'description', 'icon', 'price_cents', 'unit', 'max_quantity', 'is_active', 'sort'])]
#[Hidden(['id'])]
class Addon extends Model implements Trashable
{
    /** @use HasFactory<AddonFactory> */
    use HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }

    public function trashLabel(): string
    {
        return 'add-on';
    }

    public function trashTitle(): string
    {
        return $this->name;
    }

    public function trashBlockers(): array
    {
        return [];
    }

    /** Phase 4 adds: booking lines that point at this add-on. */
    public function permanentDeleteBlockers(): array
    {
        return [];
    }

    public function ownFiles(): array
    {
        return [];
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
