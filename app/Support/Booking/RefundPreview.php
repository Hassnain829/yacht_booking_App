<?php

namespace App\Support\Booking;

use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * What a guest would get back if they cancelled now (PLAN §7.4), from the policy the booking was made under
 * (`policy_snapshot.rules`: min hours before the start → refund %), never today's policy:
 *
 *   refund % = the first rule (longest notice first) whose hours the trip is still away
 *   back     = refund % × (total − gratuity) + gratuity   (the crew gratuity always comes back)
 *              — never more than what was paid
 *
 * Shown on the trip page (4.7). Phase 5's cancellation service uses the same rules to make the refund.
 */
final class RefundPreview
{
    /**
     * @return array{percent: int, refund_cents: int, next: array{at: CarbonImmutable, percent: int}|null}|null null when nothing can be cancelled
     */
    public static function for(Booking $booking, ?CarbonImmutable $now = null): ?array
    {
        $now ??= CarbonImmutable::now();
        if ($booking->starts_at->lessThanOrEqualTo($now)) {
            return null;
        }
        $rules = self::rules($booking);
        $hours = $now->diffInMinutes($booking->starts_at) / 60;
        $percent = 0;
        $next = null;
        foreach ($rules as $index => $rule) {
            if ($hours >= $rule['min_hours_before']) {
                $percent = $rule['refund_pct'];
                $following = $rules[$index + 1] ?? null;
                $next = $following ? ['at' => $booking->starts_at->subHours($rule['min_hours_before']), 'percent' => $following['refund_pct']] : null;
                break;
            }
        }
        $gratuity = (int) $booking->gratuity_cents;
        $refundable = (int) round(((int) $booking->total_cents - $gratuity) * $percent / 100) + $gratuity;

        return [
            'percent' => $percent,
            'refund_cents' => min((int) $booking->amount_paid_cents, $refundable),
            'next' => $next,
        ];
    }

    /**
     * The booking's rules, longest notice first.
     *
     * @return list<array{min_hours_before: int, refund_pct: int}>
     */
    public static function rules(Booking $booking): array
    {
        return collect($booking->policy_snapshot['rules'] ?? [])
            ->filter(fn ($rule) => is_array($rule) && isset($rule['min_hours_before'], $rule['refund_pct']))
            ->map(fn (array $rule) => ['min_hours_before' => (int) $rule['min_hours_before'], 'refund_pct' => (int) $rule['refund_pct']])
            ->sortByDesc('min_hours_before')
            ->values()
            ->all();
    }
}
