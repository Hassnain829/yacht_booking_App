<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Every permission is a named Gate (PLAN §4.3). Controllers, routes and views check these names only
 * (`can('approve-payouts')`, `@can('access-admin')`), so staff roles can be added later by changing this
 * list — nothing else. Ownership of a record ("is this MY boat?") is checked by Policies (app/Policies).
 *
 * Suspended or banned accounts get no abilities at all.
 */
class AuthServiceProvider extends ServiceProvider
{
    /** ability => roles that have it. */
    public const ABILITIES = [
        // Areas
        'access-admin' => ['admin'],
        'access-owner-area' => ['owner'],
        // Customers and owners
        'list-boats' => ['customer', 'owner'],
        'request-withdrawal' => ['owner'],
        // Admin
        'manage-bookings' => ['admin'],
        'assign-captains' => ['admin'],
        'review-listings' => ['admin'],
        'manage-catalog' => ['admin'],
        'approve-payouts' => ['admin'],
        'issue-refunds' => ['admin'],
        'resolve-complaints' => ['admin'],
        'manage-users' => ['admin'],
        'manage-settings' => ['admin'],
        'view-audit-log' => ['admin'],
    ];

    public function boot(): void
    {
        foreach (self::ABILITIES as $ability => $roles) {
            Gate::define($ability, fn (User $user) => $user->isActive() && in_array($user->role->value, $roles, true));
        }
    }
}
