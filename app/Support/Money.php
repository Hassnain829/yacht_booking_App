<?php

namespace App\Support;

/**
 * Money is stored and calculated as integer cents (docs/PLAN.md §6).
 * This class formats cents for display and converts the admin's form inputs ("35.50", "15" %) to cents /
 * basis points. It never calculates prices.
 */
final class Money
{
    /** An admin form's dollar amount ("35", "35.5", "35.50", already validated as decimal:0,2) → cents. */
    public static function toCents(mixed $dollars): ?int
    {
        return $dollars === null || $dollars === '' ? null : (int) round(((float) $dollars) * 100);
    }

    /** A percent from an admin form ("15", "12.5") → basis points. */
    public static function toBasisPoints(mixed $percent): ?int
    {
        return $percent === null || $percent === '' ? null : (int) round(((float) $percent) * 100);
    }

    /** Cents → the value an admin form field shows: 3500 → "35", 3550 → "35.50", null → "". */
    public static function input(?int $amountCents): string
    {
        if ($amountCents === null) {
            return '';
        }

        return $amountCents % 100 === 0 ? (string) intdiv($amountCents, 100) : number_format($amountCents / 100, 2, '.', '');
    }

    /** Basis points → the value a percent field shows: 1500 → "15", 1250 → "12.5". */
    public static function percentInput(?int $basisPoints): string
    {
        return $basisPoints === null ? '' : rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.');
    }

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
