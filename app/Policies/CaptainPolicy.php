<?php

namespace App\Policies;

/** Captain profiles (PLAN 3.4.6); accounts come in Phase 8: managed by the admin only (`manage-captains`). */
class CaptainPolicy extends ManagedRecordPolicy
{
    protected function ability(): string
    {
        return 'manage-captains';
    }
}
