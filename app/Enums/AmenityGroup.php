<?php

namespace App\Enums;

/** Highlights are a boat's own selling points; the other groups are the standard lists of the wizard. */
enum AmenityGroup: string
{
    case Highlight = 'highlight';
    case Feature = 'feature';
    case Extra = 'extra';
    case Navigation = 'navigation';
}
