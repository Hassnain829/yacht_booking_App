<?php

namespace App\Enums;

/** Who a price line's money goes to (PLAN §7.5 money split; booking_items.payee). */
enum Payee: string
{
    case Platform = 'platform';
    case Owner = 'owner';
    case Captain = 'captain';
}
