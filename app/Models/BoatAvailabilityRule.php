<?php

namespace App\Models;

use Database\Factories\BoatAvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A boat's opening hours for one weekday (0 = Sunday), in Chicago local time (PLAN §6.2, §7.2). */
#[Fillable(['weekday', 'is_open', 'opens_at', 'closes_at'])]
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
        ];
    }
}
