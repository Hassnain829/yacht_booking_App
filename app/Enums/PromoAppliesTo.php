<?php

namespace App\Enums;

/** What a promo discount is taken from: the yacht charter line only, or the whole subtotal (promo_codes.applies_to). */
enum PromoAppliesTo: string
{
    case Charter = 'charter';
    case Subtotal = 'subtotal';
}
