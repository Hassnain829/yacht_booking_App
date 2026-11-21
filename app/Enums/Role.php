<?php

namespace App\Enums;

/**
 * Account roles (PLAN §4.3). Set by the system only — never from a request:
 * sign-up → customer, first listing → owner (Phase 6), `php artisan app:create-admin` → admin.
 */
enum Role: string
{
    case Customer = 'customer';
    case Owner = 'owner';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Owner => 'Boat owner',
            self::Admin => 'Administrator',
        };
    }
}
