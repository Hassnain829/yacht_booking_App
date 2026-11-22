<?php

namespace App\Services;

use App\Enums\LoginEventType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\LoginEvent;
use App\Models\User;
use App\Notifications\AccountDeleted;
use App\Notifications\SecurityContext;
use App\Notifications\TwoFactorChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only place that changes a user's role, status, two-factor reset or deletion (CLAUDE.md: one service
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

    /**
     * Why this account can't be deleted yet (empty = it can). The admin account never can.
     * Phase 4 adds upcoming trips; Phases 6–7 add an owner's boats with future bookings and money still owed.
     *
     * @return list<string>
     */
    public function deletionBlockers(User $user): array
    {
        return $user->isAdmin() ? ["The admin account can't be deleted."] : [];
    }

    /**
     * Delete an account at its owner's request (PLAN §8.5). Personal data is anonymized and the row is
     * soft-deleted, so bookings and payments keep pointing at it (financial records are kept about 7 years).
     * Every session ends, the security log forgets the email, and a confirmation goes to the old address.
     */
    public function deleteAccount(User $user): void
    {
        $blockers = $this->deletionBlockers($user);
        if ($blockers !== []) {
            throw new InvalidArgumentException($blockers[0]);
        }

        $email = $user->email;
        $context = SecurityContext::fromRequest();

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'first_name' => 'Deleted',
                'last_name' => 'account',
                'email' => 'deleted-'.$user->ulid.'@deleted.invalid', // frees the real address for a new sign-up
                'phone' => null,
                'marketing_opt_in' => false,
                'email_verified_at' => null,
                'password' => Str::random(64),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'last_login_ip' => null,
            ])->save();
            $user->delete();

            LoginEvent::query()->where('user_id', $user->getKey())->update(['email' => null]);
        });

        $this->logOutEverywhere($user);
        $this->log->record(LoginEventType::AccountDeleted, $user);
        $this->audit->record('user.deleted', $user, after: ['anonymized' => true], actor: $user);
        Notification::route('mail', $email)->notify(new AccountDeleted($context));
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
