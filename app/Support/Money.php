<?php

namespace App\Support;

/**
 * Money is stored and calculated as integer cents (docs/PLAN.md §6).
 * This class only formats cents for display. It never calculates prices.
 */
final class Money
{
    /**
     * 125000 → "$1,250"   125050 → "$1,250.50"   -5000 → "-$50"
     *
     * @param  'auto'|'always'|'never'  $cents  'auto' shows cents only when they are not .00
     */
    public static function format(int $amountCents, string $cents = 'auto'): string
    {
        $showCents = $cents === 'always' || ($cents === 'auto' && $amountCents % 100 !== 0);
        $dollars = abs($amountCents) / 100;
        $text = '$'.number_format($dollars, $showCents ? 2 : 0);

        return ($amountCents < 0 && $text !== '$0') ? '-'.$text : $text;
    }

    /** Basis points → percent text. 2000 → "20%", 1250 → "12.5%". */
    public static function percent(int $basisPoints): string
    {
        return rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.').'%';
    }
}
