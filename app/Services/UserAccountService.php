<?php

namespace App\Services;

use App\Enums\LoginEventType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\SecurityContext;
use App\Notifications\TwoFactorChanged;
use InvalidArgumentException;

/**
 * The only place that changes a user's role, status or two-factor reset (CLAUDE.md: one service
 * method per status change). Every change is written to the audit log (PLAN §8.5).
 * Phase 3 wires the admin Users screen to it.
 */
class UserAccountService
{
    public function __construct(
        private AuditService $audit,
        private SessionService $sessions,
        private LoginEventService $log,
    ) {}

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
        $this->audit->record('user.role_changed', $user, ['role' => Role::Customer->value], ['role' => Role::Owner->value], actor: $user);

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

        $before = $user->status;
        $user->forceFill([
            'status' => $status,
            'status_reason' => $status === UserStatus::Active ? null : mb_substr((string) $reason, 0, 500),
        ])->save();
        $this->audit->record('user.status_changed', $user, ['status' => $before->value], ['status' => $status->value], $reason, $actor);

        if (! $status->canLogIn()) {
            $this->logOutEverywhere($user);
        }
    }

    /** Delete every stored session of the user and rotate the remember-me token. */
    public function logOutEverywhere(User $user): void
    {
        $this->sessions->logOutEverywhere($user);
    }

    /**
     * Turn two-factor login off for someone who lost both their authenticator and their recovery codes
     * (`php artisan app:reset-two-factor`; the admin Users screen in Phase 3). The account is logged out
     * everywhere and told by email; they can turn two-factor login on again after logging in.
     *
     * @param  User|null  $actor  the admin doing it; null = the command line (someone with server access)
     */
    public function resetTwoFactor(User $user, ?User $actor, string $reason): void
    {
        if ($actor !== null && ! $actor->isAdmin()) {
            throw new InvalidArgumentException('Only an admin can reset two-factor login.');
        }
        if (blank($reason)) {
            throw new InvalidArgumentException('A reason is required to reset two-factor login.');
        }

        $wasOn = $user->hasEnabledTwoFactorAuthentication();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->logOutEverywhere($user);
        $this->log->record(LoginEventType::TwoFactorDisabled, $user);
        $this->audit->record('user.two_factor_reset', $user, ['two_factor' => $wasOn], ['two_factor' => false], $reason, $actor);
        $user->notify(new TwoFactorChanged(enabled: false, context: SecurityContext::fromRequest(), byOurTeam: true));
    }
}
