<?php

namespace App\Policies;

/** Add-ons (PLAN 3.4.2): managed by the admin only (`manage-catalog`). */
class AddonPolicy extends ManagedRecordPolicy
{
    protected function ability(): string
    {
        return 'manage-catalog';
    }
}
