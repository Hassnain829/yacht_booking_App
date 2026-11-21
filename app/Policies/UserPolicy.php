<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who can see or change an account. People manage only their own account; the admin manages others'
 * status (suspend / ban) but never their own, and nobody can change a role through a request.
 * Phase 3 (admin Users screen) and 2.5 (account pages) use these checks.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('manage-users');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) || $actor->can('manage-users');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->is($user) && $actor->isActive();
    }

    public function changeStatus(User $actor, User $user): bool
    {
        return $actor->can('manage-users') && ! $actor->is($user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->is($user) && ! $actor->isAdmin();
    }
}
