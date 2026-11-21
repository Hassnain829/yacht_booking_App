<?php

namespace App\Support\Demo;

use Carbon\CarbonImmutable;

/**
 * Refund when the CUSTOMER cancels (PLAN §7.4):
 *
 *   refund_pct       = from the policy, by hours between now and the trip start
 *   refundable_total = refund_pct × (total − gratuity) + gratuity      (gratuity is always returned)
 *   refund           = max(0, amount_paid − (total − refundable_total))
 *   any unpaid balance is cancelled and never charged
 *
 * Phase 4/5: RefundService uses the booking's policy_snapshot and creates the Stripe refund.
 */
class RefundPreview
{
    /** @return list<array{0: int, 1: int}> [min hours before start, percent], longest first */
    public static function rules(string $policy): array
    {
        return config('listing.cancellation_rules.'.$policy) ?? config('listing.cancellation_rules.flexible');
    }

    public static function percent(string $policy, float $hoursBefore): int
    {
        foreach (self::rules($policy) as [$minHours, $percent]) {
            if ($hoursBefore >= $minHours) {
                return $percent;
            }
        }

        return 0;
    }

    /**
     * @return array{percent: int, refund: int, kept: int, cancelled_balance: int, hours_before: float}
     */
    public static function calculate(string $policy, CarbonImmutable $start, CarbonImmutable $now, int $total, int $gratuity, int $paid): array
    {
        $hours = max(0, $now->diffInMinutes($start, false) / 60);
        $percent = self::percent($policy, $hours);
        $refundable = (int) round(($total - $gratuity) * $percent / 100) + $gratuity;
        $refund = max(0, $paid - ($total - $refundable));

        return [
            'percent' => $percent,
            'refund' => $refund,
            'kept' => $paid - $refund,
            'cancelled_balance' => $total - $paid,
            'hours_before' => $hours,
        ];
    }

    /**
     * When the refund next drops: ['at' => time, 'percent' => percent after that time], or null.
     *
     * @return array{at: CarbonImmutable, percent: int}|null
     */
    public static function nextDrop(string $policy, CarbonImmutable $start, CarbonImmutable $now): ?array
    {
        $hours = $now->diffInMinutes($start, false) / 60;
        $rules = self::rules($policy);
        foreach ($rules as $i => [$minHours]) {
            if ($hours >= $minHours && isset($rules[$i + 1])) {
                return ['at' => $start->subHours($minHours), 'percent' => $rules[$i + 1][1]];
            }
        }

        return null;
    }
}
