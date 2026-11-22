<?php

namespace App\Enums;

/** Who provides the captain for a boat's trips: the owner, or our platform captains (PLAN §6.2, §7.8). */
enum CaptainSource: string
{
    case Owner = 'owner';
    case Platform = 'platform';
}
