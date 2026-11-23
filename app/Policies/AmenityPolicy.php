<?php

namespace App\Policies;

/** Amenities (PLAN 3.4.5): managed by the admin only (`manage-fleet`). */
class AmenityPolicy extends ManagedRecordPolicy
{
    protected function ability(): string
    {
        return 'manage-fleet';
    }
}
