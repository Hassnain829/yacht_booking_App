<?php

namespace App\Enums;

/** A promo code takes a percentage off, or a fixed amount (PLAN §6.5 promo_codes.type). */
enum PromoType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
