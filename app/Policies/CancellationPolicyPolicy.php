<?php

namespace App\Policies;

/** Cancellation policies (PLAN 3.4.5): edit only: managed by the admin only (`manage-catalog`). */
class CancellationPolicyPolicy extends ManagedRecordPolicy
{
    protected function ability(): string
    {
        return 'manage-catalog';
    }
}
