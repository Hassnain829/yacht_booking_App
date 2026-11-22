<?php

namespace App\Enums;

/** What happened, in the `login_events` security log (PLAN §5). */
enum LoginEventType: string
{
    case Login = 'login';
    case Failed = 'failed';
    case Logout = 'logout';
    case Lockout = 'lockout';
    case TwoFactorFailed = '2fa_failed';
    case PasswordChanged = 'password_changed';
    case TwoFactorEnabled = '2fa_enabled';
    case TwoFactorDisabled = '2fa_disabled';
    case RecoveryCodeUsed = 'recovery_code_used';
    case OtherDevicesLoggedOut = 'other_devices_logged_out';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Logged in',
            self::Failed => 'Failed login',
            self::Logout => 'Logged out',
            self::Lockout => 'Too many attempts',
            self::TwoFactorFailed => 'Wrong two-factor code',
            self::PasswordChanged => 'Password changed',
            self::TwoFactorEnabled => 'Two-factor login turned on',
            self::TwoFactorDisabled => 'Two-factor login turned off',
            self::RecoveryCodeUsed => 'Recovery code used',
            self::OtherDevicesLoggedOut => 'Logged out other devices',
        };
    }
}
