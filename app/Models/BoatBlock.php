<?php

namespace App\Models;

use App\Enums\BlockType;
use Database\Factories\BoatBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time a boat can't be booked (PLAN §6.2); no boat = every boat (e.g. a city blackout). Times in UTC.
 * boat_id and created_by are set by the code (`$boat->blocks()->create()`, forceFill), never from a request.
 * URLs use the `ulid` (3.4.4).
 */
#[Fillable(['starts_at', 'ends_at', 'type', 'reason'])]
#[Hidden(['id'])]
class BoatBlock extends Model
{
    /** @use HasFactory<BoatBlockFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<Boat, $this> */
    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Blocks that touch [from, to) — for this boat or for every boat. @param Builder<self> $query */
    public function scopeOverlapping(Builder $query, Boat $boat, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->where(fn (Builder $q) => $q->where('boat_id', $boat->getKey())->orWhereNull('boat_id'))
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
