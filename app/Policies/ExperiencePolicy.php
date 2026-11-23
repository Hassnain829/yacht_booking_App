<?php

namespace App\Policies;

/** Experiences (PLAN 3.4.2): managed by the admin only (`manage-catalog`). */
class ExperiencePolicy extends ManagedRecordPolicy
{
    protected function ability(): string
    {
        return 'manage-catalog';
    }
}
