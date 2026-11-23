<?php

namespace App\Policies;

/**
 * Boats — house and owner (PLAN 3.4.3). The admin manages every boat (`manage-fleet`). Owners get their own
 * checks on their own boats in Phase 6 (another owner's boat → 404).
 */
class BoatPolicy extends ManagedRecordPolicy
{
    protected function ability(): string
    {
        return 'manage-fleet';
    }
}
