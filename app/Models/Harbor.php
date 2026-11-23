<?php

namespace App\Models;

use App\Models\Contracts\Trashable;
use Database\Factories\HarborFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Where guests board (PLAN §6.2). The admin manages harbors in Admin → Harbors (3.4.1); URLs use the slug. */
#[Fillable(['slug', 'name', 'area', 'address', 'lat', 'lng', 'map_query', 'notes', 'image_path', 'is_active', 'sort'])]
#[Hidden(['id'])]
class Harbor extends Model implements Trashable
{
    /** @use HasFactory<HarborFactory> */
    use HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Boats that depart from here (not the ones in the Trash). @return HasMany<Boat, $this> */
    public function boats(): HasMany
    {
        return $this->hasMany(Boat::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : Storage::disk('public')->url($this->image_path);
    }

    public function trashLabel(): string
    {
        return 'harbor';
    }

    public function trashTitle(): string
    {
        return $this->name;
    }

    /** A harbor that boats still depart from can't go: their pages would lose the departure point. */
    public function trashBlockers(): array
    {
        $boats = $this->boats()->count();

        return $boats === 0 ? [] : [trans_choice(
            '{1} 1 boat departs from this harbor. Move it to another harbor first, or hide the harbor instead.|[2,*] :count boats depart from this harbor. Move them to another harbor first, or hide the harbor instead.',
            $boats,
        )];
    }

    /** Boats in the Trash still point here and could be restored, so they block a permanent delete too. */
    public function permanentDeleteBlockers(): array
    {
        $boats = Boat::withTrashed()->where('harbor_id', $this->getKey())->count();

        return $boats === 0 ? [] : [trans_choice(
            '{1} 1 boat (in the Trash or not) still uses this harbor.|[2,*] :count boats (in the Trash or not) still use this harbor.',
            $boats,
        )];
    }

    public function ownFiles(): array
    {
        return $this->image_path === null ? [] : ['public' => [$this->image_path]];
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
