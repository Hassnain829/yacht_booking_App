<?php

namespace App\Policies;

/** Harbors are managed by the admin only (`manage-fleet`, PLAN 3.4.1). */
class HarborPolicy extends ManagedRecordPolicy
{
    protected function ability(): string
    {
        return 'manage-fleet';
    }
}
