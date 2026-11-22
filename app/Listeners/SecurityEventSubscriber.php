<?php

namespace App\Listeners;

use App\Enums\LoginEventType;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
use App\Notifications\PasswordChanged;
use App\Notifications\SecurityContext;
use App\Notifications\TwoFactorChanged;
use App\Services\LoginEventService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Fortify;

/**
 * Auth events → the security log (PLAN §8.5) and the security emails (§8.1, §11).
 * Registered in AppServiceProvider. The methods are named on…, not handle…, so Laravel's
 * listener discovery doesn't register them a second time.
 *
 * Passwords, 2FA codes and recovery codes in these events are never read or stored.
 */
class SecurityEventSubscriber
{
    public function __construct(private LoginEventService $log) {}

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            Login::class => 'onLogin',
            Failed::class => 'onFailed',
            Lockout::class => 'onLockout',
            Logout::class => 'onLogout',
            PasswordReset::class => 'onPasswordChanged',
            PasswordUpdatedViaController::class => 'onPasswordChanged',
            TwoFactorAuthenticationFailed::class => 'onTwoFactorFailed',
            TwoFactorAuthenticationConfirmed::class => 'onTwoFactorEnabled',
            TwoFactorAuthenticationDisabled::class => 'onTwoFactorDisabled',
            RecoveryCodeReplaced::class => 'onRecoveryCodeUsed',
        ];
    }

    /**
     * Every successful login, including "remember me" and the end of the 2FA challenge.
     * Owners and the admin get an email when it comes from a browser not seen for their account before.
     */
    public function onLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }
        $user = $event->user;
        $device = $this->log->currentDevice();
        $isNewDevice = $this->log->hasLoggedInBefore($user) && ! $this->log->knowsDevice($user, $device);

        $this->log->record(LoginEventType::Login, $user, deviceId: $device);
        DB::table('users')->where('id', $user->getKey())->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);

        if ($isNewDevice && ($user->isOwner() || $user->isAdmin())) {
            $user->notify(new NewDeviceLogin(SecurityContext::fromRequest()));
        }
    }

    /** A wrong password or an unknown email. The row is linked to the account when the email exists. */
    public function onFailed(Failed $event): void
    {
        $email = $event->credentials[Fortify::username()] ?? null;
        $email = is_string($email) ? $email : null;
        $user = $event->user instanceof User ? $event->user : $this->userByEmail($email);

        $this->log->record(LoginEventType::Failed, $user, $email);
    }

    public function onLockout(Lockout $event): void
    {
        $email = $event->request->input(Fortify::username());
        $email = is_string($email) ? $email : null;

        $this->log->record(LoginEventType::Lockout, $this->userByEmail($email), $email);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->log->record(LoginEventType::Logout, $event->user);
        }
    }

    /** A reset from the email link, or a change on Profile & security. */
    public function onPasswordChanged(PasswordReset|PasswordUpdatedViaController $event): void
    {
        if ($event->user instanceof User) {
            $this->log->record(LoginEventType::PasswordChanged, $event->user);
            $event->user->notify(new PasswordChanged(SecurityContext::fromRequest()));
        }
    }

    public function onTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->log->record(LoginEventType::TwoFactorFailed, $event->user instanceof User ? $event->user : null);
    }

    public function onTwoFactorEnabled(TwoFactorAuthenticationConfirmed $event): void
    {
        if ($event->user instanceof User) {
            $this->log->record(LoginEventType::TwoFactorEnabled, $event->user);
            $event->user->notify(new TwoFactorChanged(enabled: true, context: SecurityContext::fromRequest()));
        }
    }

    public function onTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        if ($event->user instanceof User) {
            $this->log->record(LoginEventType::TwoFactorDisabled, $event->user);
            $event->user->notify(new TwoFactorChanged(enabled: false, context: SecurityContext::fromRequest()));
        }
    }

    public function onRecoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        if ($event->user instanceof User) {
            $this->log->record(LoginEventType::RecoveryCodeUsed, $event->user);
        }
    }

    private function userByEmail(?string $email): ?User
    {
        return $email === null || $email === '' ? null : User::query()->where('email', Str::lower(trim($email)))->first();
    }
}
