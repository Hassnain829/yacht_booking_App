<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A provider event, stored once (unique `event_id`) before it is handled, so a replay or a duplicate delivery
 * does nothing (PLAN §6.6, §8.4). Nothing is fillable: the webhook controller sets every field.
 */
class WebhookEvent extends Model
{
    public $timestamps = false;

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
