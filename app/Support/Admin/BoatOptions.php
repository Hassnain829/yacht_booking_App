<?php

namespace App\Support\Admin;

/**
 * The choices on the admin boat form (PLAN 3.4.3), shared by BoatRequest (validation) and the view, so a value
 * can only be one the form offers. Lists that the owner wizard also uses come from config/listing.php.
 */
final class BoatOptions
{
    /** @var array<int, string> minutes => label */
    public const ADVANCE_NOTICE = [0 => 'None', 60 => '1 hour', 180 => '3 hours', 360 => '6 hours', 720 => '12 hours', 1440 => '1 day', 4320 => '3 days', 10080 => '7 days'];

    /** @var array<int, string> minutes => label */
    public const BUFFERS = [0 => 'None', 30 => '30 min', 60 => '1 hour', 120 => '2 hours', 180 => '3 hours'];

    /** @var list<int> trip lengths (hours) a boat can offer */
    public const DURATIONS = [1, 2, 3, 4, 5, 6, 8, 10, 12];

    /** Default weekly hours for a new boat (Chicago time). */
    public const OPENS = '08:00';

    public const CLOSES = '23:00';

    /**
     * The wizard's categories, plus `$current` when a boat already has another one (the demo boats use the
     * mockup's labels, e.g. "Luxury Yacht"), so editing such a boat never forces a new category.
     *
     * @return array<string, string>
     */
    public static function categories(?string $current = null): array
    {
        $categories = self::same(config('listing.categories'));

        return filled($current) && ! isset($categories[$current]) ? [$current => $current] + $categories : $categories;
    }

    /** @return array<string, string> */
    public static function engineTypes(): array
    {
        return self::same(config('listing.engine_types'));
    }

    /** @return array<string, string> */
    public static function states(): array
    {
        return self::same(config('listing.states'));
    }

    /** @return list<string> things that can be "not allowed on board" */
    public static function rules(): array
    {
        return config('listing.rules');
    }

    /** @return array<string, string> markup in basis points => label; '' = the site setting */
    public static function weekendMarkups(): array
    {
        return collect(config('listing.pricing.weekend_markups_bp'))
            ->mapWithKeys(fn (int $bp) => [(string) $bp => $bp === 0 ? 'No weekend markup' : '+'.($bp / 100).'% on Fri–Sun'])
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function same(array $values): array
    {
        return array_combine($values, $values);
    }
}
