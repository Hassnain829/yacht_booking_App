<?php

namespace App\Models;

use App\Support\StartTimes;
use Database\Factories\BoatAvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The start times a boat departs at on one weekday (0 = Sunday), Chicago time (PLAN §6.2, §7.2; 4.2: start times
 * replace opening hours). `start_times` are "HH:MM" on the StartTimes grid; a closed day keeps its list for when
 * it opens again. No row for a weekday = closed.
 */
#[Fillable(['weekday', 'is_open', 'start_times'])]
#[Hidden(['id', 'boat_id'])]
class BoatAvailabilityRule extends Model
{
    /** @use HasFactory<BoatAvailabilityRuleFactory> */
    use HasFactory;

    /** @return BelongsTo<Boat, $this> */
    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_open' => 'boolean',
            'start_times' => 'array',
        ];
    }

    /**
     * The times trips can depart at that day (none when the day is closed).
     *
     * @return list<string>
     */
    public function openTimes(): array
    {
        return $this->is_open ? StartTimes::normalize($this->start_times ?? []) : [];
    }
}
