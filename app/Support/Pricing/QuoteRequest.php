<?php

namespace App\Support\Pricing;

use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The customer's choices for a price (PLAN §8.2: the browser sends only choices, never prices). The boat,
 * experience and add-ons are already looked up by their public keys; QuoteService checks that they fit together.
 */
final readonly class QuoteRequest
{
    /**
     * @param  CarbonImmutable  $startsAt  the trip start (any time zone; Fri–Sun is decided in Chicago time)
     * @param  list<Addon>  $addons  each chosen once; a per-guest add-on counts every guest
     * @param  int  $gratuityPct  0, or one of the gratuity options in Admin → Settings
     */
    public function __construct(
        public Boat $boat,
        public ?Experience $experience,
        public CarbonImmutable $startsAt,
        public int $hours,
        public int $guests,
        public array $addons = [],
        public ?PromoTerms $promo = null,
        public int $gratuityPct = 0,
    ) {}

    /**
     * A start typed in Chicago time ("2027-06-04" + "18:30") as a UTC instant. Without a time (the booking card
     * before a start time is picked) it is the start of that day, which is enough for the weekend rate.
     */
    public static function localStart(string $date, ?string $time = null): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ($time !== null && ! preg_match('/^\d{2}:\d{2}$/', $time))) {
            throw new InvalidArgumentException('Expected a YYYY-MM-DD date and an optional HH:MM time.');
        }

        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.($time ?? '00:00'), config('app.display_timezone'))->utc();
    }
}
