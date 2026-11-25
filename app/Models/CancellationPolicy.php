<?php

namespace App\Models;

use Database\Factories\CancellationPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Flexible / Moderate / Strict (PLAN §6.3, §7.4). `points` are the sentences guests read; `rules` are what the
 * refund is calculated from: [{"min_hours_before": 120, "refund_pct": 100}, …]. Bookings keep a snapshot.
 */
#[Fillable(['slug', 'name', 'badge', 'points', 'rules', 'is_active', 'sort'])]
#[Hidden(['id'])]
class CancellationPolicy extends Model
{
    /** @use HasFactory<CancellationPolicyFactory> */
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

    /** Refund % for a cancellation this many hours before the trip starts (the first rule it reaches). */
    public function refundPercent(float $hoursBefore): int
    {
        $rules = collect($this->rules)->sortByDesc('min_hours_before');

        foreach ($rules as $rule) {
            if ($hoursBefore >= $rule['min_hours_before']) {
                return (int) $rule['refund_pct'];
            }
        }

        return 0;
    }

    /**
     * How many hours before the start a cancellation still gets everything back (the latest 100% rule), or null
     * when no rule refunds in full. The booking card shows "Free cancellation until …" from it (4.3).
     */
    public function freeCancellationHours(): ?int
    {
        $full = collect($this->rules)->filter(fn (array $rule) => (int) $rule['refund_pct'] >= 100);

        return $full->isEmpty() ? null : (int) $full->min('min_hours_before');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'array',
            'rules' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
