<?php

namespace App\Enums;

/**
 * The lists boats pick their features from (PLAN §6.2, 3.7). Feature / Extra / Navigation are also the owner
 * wizard's lists; Cabin, Galley, Bathroom and Deck were added for cruisers and yachts. A boat's own selling
 * points are its `highlights` lines — the old shared Highlight group is kept only so a rollback can restore it.
 */
enum AmenityGroup: string
{
    case Highlight = 'highlight';
    case Feature = 'feature';
    case Cabin = 'cabin';
    case Galley = 'galley';
    case Bathroom = 'bathroom';
    case Deck = 'deck';
    case Extra = 'extra';
    case Navigation = 'navigation';

    public function label(): string
    {
        return match ($this) {
            self::Highlight => 'Highlights (old shared list)',
            self::Feature => 'Comfort & tech',
            self::Cabin => 'Cabin & sleeping',
            self::Galley => 'Galley (kitchen)',
            self::Bathroom => 'Bathroom',
            self::Deck => 'Deck & swim',
            self::Extra => 'Water toys & extras',
            self::Navigation => 'Navigation & safety',
        };
    }

    /** @return list<self> the groups shown on the boat form and the Amenities screen, in this order */
    public static function forms(): array
    {
        return [self::Feature, self::Cabin, self::Galley, self::Bathroom, self::Deck, self::Extra, self::Navigation];
    }
}
