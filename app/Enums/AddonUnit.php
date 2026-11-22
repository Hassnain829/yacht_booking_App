<?php

namespace App\Enums;

/** How an add-on is priced: once per booking, or per guest. */
enum AddonUnit: string
{
    case Flat = 'flat';
    case PerGuest = 'per_guest';
}
