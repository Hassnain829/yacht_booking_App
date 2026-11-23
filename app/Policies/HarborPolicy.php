<?php

namespace App\Policies;

use App\Models\Harbor;
use App\Models\User;

/** Harbors are managed by the admin only (`manage-fleet`, PLAN 3.4.1). */
class HarborPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-fleet');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-fleet');
    }

    public function update(User $user, Harbor $harbor): bool
    {
        return $user->can('manage-fleet') && ! $harbor->trashed();
    }

    /** Move to the Trash. */
    public function delete(User $user, Harbor $harbor): bool
    {
        return $user->can('manage-fleet');
    }

    public function restore(User $user, Harbor $harbor): bool
    {
        return $user->can('manage-fleet');
    }

    public function forceDelete(User $user, Harbor $harbor): bool
    {
        return $user->can('manage-fleet');
    }
}
