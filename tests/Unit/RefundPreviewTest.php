<?php

namespace Tests\Unit;

use App\Support\Demo\RefundPreview;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Customer cancellation refunds (PLAN §7.4, R2). */
class RefundPreviewTest extends TestCase
{
    /** @return array<string, array{0: string, 1: float, 2: int}> */
    public static function windows(): array
    {
        return [
            'flexible, exactly 24 h' => ['flexible', 24, 100],
            'flexible, just under 24 h' => ['flexible', 23.99, 0],
            'moderate, 5 days' => ['moderate', 120, 100],
            'moderate, just under 5 days' => ['moderate', 119.9, 50],
            'moderate, exactly 3 days' => ['moderate', 72, 50],
            'moderate, under 3 days' => ['moderate', 71.9, 0],
            'strict, 30 days' => ['strict', 720, 100],
            'strict, 15 days' => ['strict', 360, 50],
            'strict, under 15 days' => ['strict', 359, 0],
            'unknown policy falls back to flexible' => ['nope', 30, 100],
        ];
    }

    #[DataProvider('windows')]
    public function test_refund_percent_by_hours_before_the_start(string $policy, float $hours, int $percent): void
    {
        $this->assertSame($percent, RefundPreview::percent($policy, $hours));
    }

    public function test_the_plan_example_deposit_paid(): void
    {
        $start = CarbonImmutable::parse('2026-08-10 18:00', 'America/Chicago');

        // $1,000 trip, $500 deposit paid. 50% window: owes $500, already paid → no refund, balance never charged.
        $half = RefundPreview::calculate('moderate', $start, $start->subHours(100), 100000, 0, 50000);
        $this->assertSame(['percent' => 50, 'refund' => 0, 'kept' => 50000, 'cancelled_balance' => 50000], array_diff_key($half, ['hours_before' => 0]));

        // Full-refund window → the deposit comes back.
        $full = RefundPreview::calculate('moderate', $start, $start->subDays(10), 100000, 0, 50000);
        $this->assertSame(50000, $full['refund']);
        $this->assertSame(0, $full['kept']);
    }

    public function test_gratuity_is_always_refunded(): void
    {
        $start = CarbonImmutable::parse('2026-08-10 18:00', 'America/Chicago');

        // Paid in full ($1,000 including $100 gratuity), cancelled inside the no-refund window.
        $result = RefundPreview::calculate('flexible', $start, $start->subHours(2), 100000, 10000, 100000);
        $this->assertSame(0, $result['percent']);
        $this->assertSame(10000, $result['refund']);
    }

    public function test_next_drop_is_when_the_refund_gets_smaller(): void
    {
        $start = CarbonImmutable::parse('2026-08-10 18:00', 'America/Chicago');

        $drop = RefundPreview::nextDrop('moderate', $start, $start->subHours(200));
        $this->assertTrue($drop['at']->equalTo($start->subHours(120)));
        $this->assertSame(50, $drop['percent']);

        $this->assertSame(0, RefundPreview::nextDrop('moderate', $start, $start->subHours(100))['percent']);
        $this->assertNull(RefundPreview::nextDrop('flexible', $start, $start->subHours(3)), 'already in the last window');
    }
}
