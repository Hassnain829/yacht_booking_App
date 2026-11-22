<?php

namespace App\Notifications\Concerns;

use App\Models\User;

/**
 * Queued emails load the user again when they are sent. If the account was deleted in between, its address
 * is already anonymized (deleted-…@deleted.invalid): skip the email instead of sending it into a bounce.
 * (The deletion itself is confirmed by AccountDeleted, sent to the real address.)
 */
trait SkipsDeletedAccounts
{
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! ($notifiable instanceof User && $notifiable->trashed());
    }
}
