<?php

namespace App\Services;

use App\Enums\PaymentIntentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Refund;
use Carbon\CarbonImmutable;

/**
 * What a guest gets back when a trip is cancelled (PLAN §7.4, D23), from the policy the booking was made under
 * (`policy_snapshot.rules`: min hours before the start → refund %), never today's policy:
 *
 *   refund %   = the first rule (longest notice first) whose hours the trip is still away (0% once it started)
 *   by policy  = refund % × (total − gratuity) + gratuity       (the crew gratuity always comes back)
 *                — never more than what can still be refunded
 *   back       = by policy − the card processing fee            (the fee isn't refundable: the guest bears it, D23)
 *
 * The trip page shows it, the guest's cancellation asks for it, the admin's cancel dialog offers it.
 */
class CancellationPolicyService
{
    /**
     * @return array{percent: int, policy_cents: int, fee_cents: int, refund_cents: int, next: array{at: CarbonImmutable, percent: int}|null}
     */
    public function preview(Booking $booking, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        [$percent, $next] = $this->percentAt($booking, $now);
        $gratuity = (int) $booking->gratuity_cents;
        $byPolicy = min($this->refundableCents($booking), (int) round(((int) $booking->total_cents - $gratuity) * $percent / 100) + $gratuity);
        $fee = min($byPolicy, $this->feeToKeep($booking));

        return [
            'percent' => $percent,
            'policy_cents' => $byPolicy,
            'fee_cents' => $fee,
            'refund_cents' => max(0, $byPolicy - $fee),
            'next' => $next,
        ];
    }

    /**
     * The policy's refund % now, and when (and to what) it drops next.
     *
     * @return array{0: int, 1: array{at: CarbonImmutable, percent: int}|null}
     */
    public function percentAt(Booking $booking, CarbonImmutable $now): array
    {
        if ($booking->starts_at->lessThanOrEqualTo($now)) {
            return [0, null];
        }
        $rules = $this->rules($booking);
        $hours = $now->diffInMinutes($booking->starts_at) / 60;
        foreach ($rules as $index => $rule) {
            if ($hours >= $rule['min_hours_before']) {
                $following = $rules[$index + 1] ?? null;

                return [$rule['refund_pct'], $following ? ['at' => $booking->starts_at->subHours($rule['min_hours_before']), 'percent' => $following['refund_pct']] : null];
            }
        }

        return [0, null];
    }

    /**
     * The booking's rules, longest notice first.
     *
     * @return list<array{min_hours_before: int, refund_pct: int}>
     */
    public function rules(Booking $booking): array
    {
        return collect($booking->policy_snapshot['rules'] ?? [])
            ->filter(fn ($rule) => is_array($rule) && isset($rule['min_hours_before'], $rule['refund_pct']))
            ->map(fn (array $rule) => ['min_hours_before' => (int) $rule['min_hours_before'], 'refund_pct' => (int) $rule['refund_pct']])
            ->sortByDesc('min_hours_before')
            ->values()
            ->all();
    }

    /**
     * What can still go back to the guest's card: the paid payments' unrefunded money, less the refunds already
     * asked for or on their way (`$except`: the refund being approved now).
     */
    public function refundableCents(Booking $booking, ?Refund $except = null): int
    {
        $left = $booking->payments()->where('status', PaymentIntentStatus::Succeeded)->get()->sum(fn ($payment) => $payment->refundableCents());
        $inFlight = $booking->refunds()->whereIn('status', [RefundStatus::Requested, RefundStatus::Pending])
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->sum('amount_cents');

        return max(0, $left - (int) $inFlight);
    }

    /** The card processing fee of the booking's payments: the provider's when known, else the estimate. */
    public function cardFeeCents(Booking $booking): int
    {
        return (int) $booking->payments()->where('status', PaymentIntentStatus::Succeeded)->get()
            ->sum(fn ($payment) => $payment->fee_cents ?? self::estimateFee($payment->amount_cents));
    }

    /** The part of the card fee not kept back by an earlier refund yet (it is kept once per booking). */
    public function feeToKeep(Booking $booking, ?Refund $except = null): int
    {
        $kept = $booking->refunds()->whereIn('status', [RefundStatus::Requested, RefundStatus::Pending, RefundStatus::Succeeded])
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->sum('fee_cents');

        return max(0, $this->cardFeeCents($booking) - (int) $kept);
    }

    /** The card fee estimate (payments.card_fee): 2.9% + 30¢ by default. */
    public static function estimateFee(int $amountCents): int
    {
        if ($amountCents <= 0) {
            return 0;
        }

        return (int) round($amountCents * (int) config('payments.card_fee.percent_bp', 290) / 10000) + (int) config('payments.card_fee.fixed_cents', 30);
    }
}
