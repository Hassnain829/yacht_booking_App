<?php

namespace App\Models;

use App\Enums\LoginEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the security log (PLAN §5). Written only by LoginEventService. Kept for a year.
 *
 * @property LoginEventType $event
 */
#[Fillable(['user_id', 'email', 'event', 'ip_address', 'user_agent', 'device_id'])]
class LoginEvent extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subYear());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => LoginEventType::class,
            'created_at' => 'datetime',
        ];
    }
}
