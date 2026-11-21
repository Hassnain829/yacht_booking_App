<?php

use App\Support\Money;

if (! function_exists('money')) {
    /**
     * Format integer cents for display, e.g. {{ money($yacht->hourly_rate_cents) }} → "$450".
     *
     * @param  'auto'|'always'|'never'  $cents
     */
    function money(int $amountCents, string $cents = 'auto'): string
    {
        return Money::format($amountCents, $cents);
    }
}
