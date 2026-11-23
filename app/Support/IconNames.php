<?php

namespace App\Support;

/**
 * Icons the admin can pick for experiences, add-ons and amenities (PLAN 3.4). Each name must be registered in
 * resources/js/lib/icons.js — a test reads that file and checks every name, so a missing icon fails the build
 * instead of showing an empty box on the site. Validate with `Rule::in(IconNames::PICKER)`.
 */
final class IconNames
{
    /** @var list<string> */
    public const PICKER = [
        'anchor', 'bath', 'bed-double', 'briefcase', 'building-2', 'camera', 'clock', 'cloud-sun', 'compass',
        'gift', 'heart', 'heart-handshake', 'image', 'life-buoy', 'lightbulb', 'map-pin', 'monitor', 'music',
        'navigation', 'party-popper', 'sailboat', 'ship-wheel', 'smartphone', 'smile', 'snowflake', 'sparkles',
        'star', 'sun', 'sunset', 'tag', 'timer', 'users', 'utensils', 'waves', 'wine', 'zap',
    ];

    /** @return array<string, string> name => label, for a <select> */
    public static function options(): array
    {
        return collect(self::PICKER)->mapWithKeys(fn (string $name) => [$name => ucfirst(str_replace('-', ' ', $name))])->all();
    }
}
