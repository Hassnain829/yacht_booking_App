<?php

namespace App\Models;

use App\Enums\BoatOwnership;
use App\Enums\BoatStatus;
use App\Enums\BoatType;
use App\Enums\CaptainSource;
use App\Models\Contracts\Trashable;
use Database\Factories\BoatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A boat — the business's own ("house") or an owner's (PLAN §6.2). The public id is `ulid`.
 *
 * NOT fillable (set only by services): ulid, ownership, owner_id, status and the review workflow fields —
 * a request can never make a boat someone else's or publish it (CLAUDE.md security standard).
 */
#[Fillable([
    'slug', 'title', 'short', 'description',
    'type', 'category', 'make', 'model', 'year', 'length_ft', 'registration_no', 'uscg_inspected', 'max_guests',
    'staterooms', 'sleeps', 'beds', 'heads', 'engine_count', 'engine_type', 'horsepower', 'engine_year',
    'harbor_id', 'location_type', 'street', 'city', 'state', 'zip', 'country', 'lat', 'lng',
    'cancellation_policy_id', 'rules', 'advance_notice_minutes', 'allow_multiple_bookings', 'buffer_minutes', 'extensions_enabled',
    'renter_operated_allowed', 'captain_source', 'fishing_charter', 'fuel_policy',
    'hourly_rate_cents', 'weekend_multiplier_bp', 'min_hours', 'durations', 'duration_prices',
    'cleaning_fee_cents', 'captain_rate_cents', 'crew_count',
    'badge', 'is_featured', 'sort', 'rating_avg', 'reviews_count',
    'booking_enabled', 'unavailable_reason', 'wizard_step',
])]
#[Hidden(['id'])]
class Boat extends Model implements Trashable
{
    /** @use HasFactory<BoatFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** New boats start as house drafts with platform captains (the same defaults as the database). */
    protected $attributes = [
        'ownership' => 'house',
        'status' => 'draft',
        'captain_source' => 'platform',
        'booking_enabled' => true,
        'extensions_enabled' => true,
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Harbor, $this> */
    public function harbor(): BelongsTo
    {
        return $this->belongsTo(Harbor::class);
    }

    /** @return BelongsTo<CancellationPolicy, $this> */
    public function cancellationPolicy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class);
    }

    /** @return BelongsToMany<Amenity, $this> */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)->orderBy('sort');
    }

    /** Experiences this boat offers; the pivot can override the package fee. @return BelongsToMany<Experience, $this> */
    public function experiences(): BelongsToMany
    {
        return $this->belongsToMany(Experience::class)->withPivot(['package_fee_override_cents', 'is_active'])->withTimestamps();
    }

    /** @return HasMany<BoatAvailabilityRule, $this> */
    public function availabilityRules(): HasMany
    {
        return $this->hasMany(BoatAvailabilityRule::class)->orderBy('weekday');
    }

    /** @return HasMany<BoatBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(BoatBlock::class);
    }

    /** Gallery, in order (the first one is the cover). @return MorphMany<Media, $this> */
    public function photos(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')->where('collection', 'photos')->orderBy('sort')->orderBy('id');
    }

    /** Shown on the public site. @param Builder<self> $query */
    public function scopeListed(Builder $query): void
    {
        $query->where('status', BoatStatus::Published);
    }

    /** Shown and bookable (no "Unavailable" tag). @param Builder<self> $query */
    public function scopeBookable(Builder $query): void
    {
        $query->where('status', BoatStatus::Published)->where('booking_enabled', true);
    }

    public function isHouse(): bool
    {
        return $this->ownership === BoatOwnership::House;
    }

    public function isBookable(): bool
    {
        return $this->status === BoatStatus::Published && $this->booking_enabled;
    }

    /** The first photo (load `photos` first to avoid a query per boat in lists). */
    public function cover(): ?Media
    {
        return $this->photos->first();
    }

    public function trashLabel(): string
    {
        return 'boat';
    }

    public function trashTitle(): string
    {
        return $this->title;
    }

    /** Phase 4 adds: upcoming confirmed trips (cancel or move them first). */
    public function trashBlockers(): array
    {
        return [];
    }

    /** Phase 4 adds: any booking or payment of this boat (financial records are kept, PLAN §8.5). */
    public function permanentDeleteBlockers(): array
    {
        return [];
    }

    /** Photos are `media` rows (TrashService deletes them); opening hours, blocks and pivots cascade in the database. */
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
            'ownership' => BoatOwnership::class,
            'status' => BoatStatus::class,
            'type' => BoatType::class,
            'captain_source' => CaptainSource::class,
            'booking_enabled' => 'boolean',
            'extensions_enabled' => 'boolean', // the admin's "Allow trip extensions" switch (PLAN R17)
            'uscg_inspected' => 'boolean',
            'allow_multiple_bookings' => 'boolean',
            'renter_operated_allowed' => 'boolean',
            'fishing_charter' => 'boolean',
            'is_featured' => 'boolean',
            'rules' => 'array',
            'durations' => 'array',
            'duration_prices' => 'array',
            'pending_changes' => 'array',
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
            'rating_avg' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
