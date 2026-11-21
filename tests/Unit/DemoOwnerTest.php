<?php

namespace Tests\Unit;

use App\Support\Demo\DemoOwner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;
use Tests\TestCase;

/** Earnings rules for owners (PLAN §7.5–7.6), as the demo data calculates them. */
class DemoOwnerTest extends TestCase
{
    private function owner(string $now = '2026-07-14 10:00:00'): DemoOwner
    {
        return new DemoOwner(CarbonImmutable::parse($now, 'America/Chicago')); // a Tuesday
    }

    private function booking(DemoOwner $owner, string $ref): Fluent
    {
        return $owner->bookings()->firstWhere('ref', $ref);
    }

    public function test_charter_uses_the_whole_dollar_weekend_rate_or_the_fixed_price(): void
    {
        $pricing = ['hourly_cents' => 38000, 'weekend_markup_bp' => 1500, 'fixed' => ['4' => 150000]];
        $tuesday = CarbonImmutable::parse('2026-07-14 18:00', 'America/Chicago');
        $friday = CarbonImmutable::parse('2026-07-17 18:00', 'America/Chicago');
        $sunday = CarbonImmutable::parse('2026-07-19 18:00', 'America/Chicago');

        $this->assertSame(114000, DemoOwner::charterCents($pricing, $tuesday, 3));
        $this->assertSame(131100, DemoOwner::charterCents($pricing, $friday, 3)); // $437/h
        $this->assertSame(131100, DemoOwner::charterCents($pricing, $sunday, 3));
        $this->assertSame(150000, DemoOwner::charterCents($pricing, $friday, 4), 'fixed price ignores the weekend markup');
    }

    public function test_net_is_charter_minus_commission_plus_cleaning(): void
    {
        $trip = $this->booking($this->owner(), 'CYB-7Q2M4K'); // Friday, 3 hours

        $this->assertSame(131100, $trip->charter_cents);
        $this->assertSame(26220, $trip->commission_cents, '20% of the charter only');
        $this->assertSame(12000, $trip->cleaning_cents);
        $this->assertSame(116880, $trip->net_cents);
    }

    public function test_earnings_become_available_72_hours_after_the_trip_ends(): void
    {
        // One trip 3 days ago, 07:00–10:00 → available today at 10:00.
        config([
            'demo_owner.bookings' => [['ref' => 'CYB-TEST01', 'boat' => 0, 'experience' => 'Test', 'day' => -3, 'time' => '07:00', 'hours' => 3, 'guests' => 2, 'customer' => 'A B.', 'status' => 'completed', 'captain' => null]],
            'demo_owner.complaints' => [],
            'demo_owner.payouts' => [],
        ]);

        $this->assertSame('pending', $this->booking($this->owner('2026-07-14 09:59:00'), 'CYB-TEST01')->earning_status);
        $this->assertSame('available', $this->booking($this->owner('2026-07-14 10:00:00'), 'CYB-TEST01')->earning_status);
    }

    public function test_open_complaints_hold_earnings_and_cancelled_trips_earn_nothing(): void
    {
        $owner = $this->owner();

        $this->assertSame('on_hold', $this->booking($owner, 'CYB-R4J6MV')->earning_status);
        $cancelled = $this->booking($owner, 'CYB-6MQ8HN');
        $this->assertSame('refunded', $cancelled->earning_status);
        $this->assertSame(0, $cancelled->net_cents);
        $this->assertSame('paid', $this->booking($owner, 'CYB-H7V3QS')->earning_status);
        $this->assertSame('pending', $this->booking($owner, 'CYB-K8D2PZ')->earning_status, 'upcoming trips are pending');
    }

    public function test_balances_add_up(): void
    {
        $owner = $this->owner();
        $bookings = $owner->bookings();
        $balances = $owner->balances();
        $deductions = $owner->deductions()->sum('deduction_cents');

        $this->assertSame(15000, $deductions);
        $this->assertSame($bookings->where('earning_status', 'available')->sum('net_cents') - $deductions, $balances['available']);
        $this->assertSame($bookings->where('earning_status', 'pending')->sum('net_cents'), $balances['pending']);
        $this->assertSame($this->booking($owner, 'CYB-R4J6MV')->net_cents, $balances['on_hold']);
        $this->assertSame($bookings->where('earning_status', 'paid')->sum('net_cents'), $balances['paid']);
        $this->assertSame(
            $balances['available'] + $balances['pending'] + $balances['on_hold'],
            $owner->ledger()->sum('amount_cents'),
            'The statement (earnings − deductions − payouts) equals what is still owed',
        );
    }

    public function test_payouts_total_the_trips_they_paid(): void
    {
        $owner = $this->owner();
        $payout = $owner->payouts()->firstWhere('ref', 'PO-2N8K4D');

        $this->assertSame(2, $payout->trips);
        $this->assertSame($this->booking($owner, 'CYB-H7V3QS')->net_cents + $this->booking($owner, 'CYB-D9X4LT')->net_cents, $payout->amount_cents);
    }
}
