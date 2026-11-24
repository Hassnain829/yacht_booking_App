<?php

namespace App\Enums;

/** The kind of a price line on a quote / booking (PLAN §6.5 booking_items.type). */
enum BookingItemType: string
{
    case Charter = 'charter';
    case Captain = 'captain';
    case Package = 'package';
    case Addon = 'addon';
    case Cleaning = 'cleaning';
    case Discount = 'discount';
    case Tax = 'tax';
    case Gratuity = 'gratuity';
    case Fee = 'fee';
}
