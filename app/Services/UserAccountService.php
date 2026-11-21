<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place that changes a user's role or status (CLAUDE.md: one service method per status change).
 * Phase 2.4 adds an audit-log entry to every change here; Phase 3 wires the admin Users screen to it.
 */
class UserAccountService
{
    /**
     * A customer becomes an owner when they start their first listing (Phase 6).
     * Admins never change role. The caller regenerates the session if this is the current user (PLAN §8.1).
     */
    public function makeOwner(User $user): bool
    {
        if (! $user->isCustomer()) {
            return false;
        }
        $user->forceFill(['role' => Role::Owner])->save();

        return true;
    }

    /** Suspend, ban or re-activate an account. Suspending or banning logs it out everywhere. */
    public function setStatus(User $user, UserStatus $status, User $actor, ?string $reason = null): void
    {
        if (! $actor->isAdmin()) {
            throw new InvalidArgumentException('Only an admin can change an account status.');
        }
        if ($actor->is($user)) {
            throw new InvalidArgumentException('Admins cannot change their own status.');
        }
        if ($status !== UserStatus::Active && blank($reason)) {
            throw new InvalidArgumentException('A reason is required to suspend or ban an account.');
        }

        $user->forceFill([
            'status' => $status,
            'status_reason' => $status === UserStatus::Active ? null : mb_substr((string) $reason, 0, 500),
        ])->save();

        if (! $status->canLogIn()) {
            $this->logOutEverywhere($user);
        }
    }

    /** Delete every stored session of the user and rotate the remember-me token. */
    public function logOutEverywhere(User $user): void
    {
        $user->forceFill(['remember_token' => null])->save();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }
    }
}
