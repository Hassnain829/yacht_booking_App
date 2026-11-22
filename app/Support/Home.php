<?php

namespace App\Support;

use App\Models\User;

/**
 * Where a signed-in user lands (PLAN §4.2) when there is no page they were trying to reach:
 * the admin → the admin dashboard, an owner → the owner dashboard, everyone else → My trips.
 */
final class Home
{
    public static function for(?User $user): string
    {
        return match (true) {
            $user?->isAdmin() === true => route('admin.dashboard'),
            $user?->isOwner() === true => route('owner.dashboard'),
            default => route('account.trips.index'),
        };
    }
}
