<?php

namespace App\Models;

use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A package guests book, e.g. Sunset Skyline Cruise (PLAN §6.3). URLs use the slug. */
#[Fillable([
    'slug', 'name', 'category', 'icon', 'image_path', 'tagline', 'description', 'includes',
    'durations', 'time_slots', 'allowed_weekdays', 'season_starts_on', 'season_ends_on',
    'package_fee_cents', 'is_active', 'is_featured', 'sort', 'seo_title', 'seo_description',
])]
#[Hidden(['id'])]
class Experience extends Model
{
    /** @use HasFactory<ExperienceFactory> */
    use HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<Boat, $this> */
    public function boats(): BelongsToMany
    {
        return $this->belongsToMany(Boat::class)->withPivot(['package_fee_override_cents', 'is_active'])->withTimestamps();
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
            'includes' => 'array',
            'durations' => 'array',
            'time_slots' => 'array',
            'allowed_weekdays' => 'array',
            'season_starts_on' => 'date',
            'season_ends_on' => 'date',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }
}
