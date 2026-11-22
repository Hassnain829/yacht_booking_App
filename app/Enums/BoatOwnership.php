<?php

namespace App\Enums;

/** House boats belong to the business; owner boats are listed by owners (PLAN §6.2). */
enum BoatOwnership: string
{
    case House = 'house';
    case Owner = 'owner';
}
