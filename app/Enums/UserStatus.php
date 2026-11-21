<?php

namespace App\Enums;

/** Account status (PLAN §6.1). Only active accounts can log in; changes go through UserAccountService. */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Banned => 'Banned',
        };
    }

    public function canLogIn(): bool
    {
        return $this === self::Active;
    }
}
