<?php

namespace App\Enums;

/** Why a boat (or, with no boat, every boat) can't be booked for a while (PLAN §6.2). */
enum BlockType: string
{
    case OwnerBlock = 'owner_block';
    case Maintenance = 'maintenance';
    case Blackout = 'blackout';
    case Weather = 'weather';
}
