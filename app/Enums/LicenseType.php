<?php

namespace App\Enums;

/** USCG captain licenses (PLAN §6.4, §7.8): an OUPV ("six-pack") captain may carry at most 6 passengers. */
enum LicenseType: string
{
    case Oupv = 'oupv';
    case Master = 'master';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Oupv => 'OUPV (six-pack)',
            self::Master => 'USCG Master',
            self::Other => 'Other',
        };
    }
}
