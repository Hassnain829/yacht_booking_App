<?php

namespace App\Models;

use App\Models\Contracts\Trashable;
use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** A package guests book, e.g. Sunset Skyline Cruise (PLAN §6.3). URLs use the slug; managed in Admin → Experiences (3.4.2). */
#[Fillable([
    'slug', 'name', 'category', 'icon', 'image_path', 'tagline', 'description', 'includes',
    'durations', 'time_slots', 'allowed_weekdays', 'season_starts_on', 'season_ends_on',
    'package_fee_cents', 'is_active', 'is_featured', 'sort', 'seo_title', 'seo_description',
])]
#[Hidden(['id'])]
class Experience extends Model implements Trashable
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

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : Storage::disk('public')->url($this->image_path);
    }

    public function trashLabel(): string
    {
        return 'experience';
    }

    public function trashTitle(): string
    {
        return $this->name;
    }

    /** Nothing blocks it yet; Phase 4 keeps old bookings intact through their snapshot. */
    public function trashBlockers(): array
    {
        return [];
    }

    /** Bookings of this experience keep a snapshot, but the record stays for their history. */
    public function permanentDeleteBlockers(): array
    {
        $bookings = Booking::query()->where('experience_id', $this->getKey())->count();

        return $bookings === 0 ? [] : [trans_choice(
            '{1} 1 booking is for this experience and keeps it for the records. Keep it hidden instead.|[2,*] :count bookings are for this experience and keep it for the records. Keep it hidden instead.',
            $bookings,
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
