<?php

namespace Tests\Unit;

use App\Support\Demo\DemoQuote;
use Illuminate\Support\Fluent;
use Tests\TestCase;

/**
 * Price maths (docs/PLAN.md §7.1). The same three scenarios are asserted in
 * tests/js/demo-quote.test.js, so the PHP and JS calculators can never drift apart.
 */
class DemoQuoteTest extends TestCase
{
    private function yacht(string $slug): Fluent
    {
        return new Fluent(collect(config('demo.yachts'))->firstWhere('slug', $slug));
    }

    private function experience(string $slug): Fluent
    {
        return new Fluent(collect(config('demo.experiences'))->firstWhere('slug', $slug));
    }

    private function addon(string $slug): Fluent
    {
        return new Fluent(collect(config('demo.addons'))->firstWhere('slug', $slug));
    }

    public function test_weekday_private_charter(): void
    {
        $q = DemoQuote::fromConfig()->quote($this->yacht('azure'), $this->experience('private'), ['date' => '2026-11-23', 'hours' => 3, 'guests' => 10]);

        $this->assertFalse($q['weekend']);
        $this->assertSame([135000, 25500, 175500, 0, 15795, 0, 191295, 95648], [$q['charter'], $q['captain'], $q['subtotal'], $q['discount'], $q['tax'], $q['gratuity'], $q['total'], $q['deposit']]);
        $this->assertSame(['charter', 'captain', 'cleaning'], array_column($q['lines'], 'key'));
    }

    public function test_weekend_with_package_addons_percent_promo_gratuity_paid_in_full(): void
    {
        $q = DemoQuote::fromConfig()->quote($this->yacht('azure'), $this->experience('playpen'), [
            'date' => '2026-11-21', 'hours' => 4, 'guests' => 10,
            'addons' => [$this->addon('catering'), $this->addon('champagne')],
            'promo' => ' welcome10 ', 'gratuity' => 18, 'plan' => 'full',
        ]);

        $this->assertTrue($q['weekend']);
        $this->assertSame(51800, $q['rate'], 'Weekend rate is 1.15× rounded to whole dollars');
        $this->assertSame([207200, 34000, 318200, 20720, 26773, 43416, 367669, 367669, 0], [$q['charter'], $q['captain'], $q['subtotal'], $q['discount'], $q['tax'], $q['gratuity'], $q['total'], $q['dueNow'], $q['balance']]);
        $this->assertSame('Licensed USCG captain', $q['lines'][1]['label'], 'Single-crew boats show "Licensed USCG captain"');
        $this->assertSame('$35 × 10 guests', collect($q['lines'])->firstWhere('key', 'addon-catering')['sub']);
    }

    public function test_fixed_promo_and_deposit(): void
    {
        $q = DemoQuote::fromConfig()->quote($this->yacht('windward'), $this->experience('fireworks'), [
            'date' => '2026-11-25', 'hours' => 2, 'guests' => 6, 'promo' => 'SUNSET50', 'gratuity' => 15,
        ]);

        $this->assertSame([45000, 13000, 90500, 5000, 7695, 8700, 101895, 50948, 50947], [$q['charter'], $q['captain'], $q['subtotal'], $q['discount'], $q['tax'], $q['gratuity'], $q['total'], $q['deposit'], $q['balance']]);
    }

    public function test_unknown_promo_is_ignored(): void
    {
        $q = DemoQuote::fromConfig()->quote($this->yacht('azure'), null, ['hours' => 3, 'promo' => '<script>']);

        $this->assertSame(0, $q['discount']);
        $this->assertNull($q['promo']);
    }

    public function test_durations_never_go_below_the_boat_minimum(): void
    {
        $this->assertSame([3, 4], DemoQuote::durations($this->yacht('lakeside'), $this->experience('sunset')));
        $this->assertSame([2, 3, 4, 6, 8], DemoQuote::durations($this->yacht('windward'), $this->experience('private')));
        $this->assertSame([3], DemoQuote::durations($this->yacht('skyline'), $this->experience('proposal')));
        $this->assertSame([3], DemoQuote::durations($this->yacht('azure'), null));
    }

    public function test_starting_at_and_weekend_detection(): void
    {
        $this->assertSame(160500, DemoQuote::startingAt($this->yacht('azure'), $this->experience('sunset')));
        $this->assertSame([false, true, true, true, false], array_map(
            fn ($d) => DemoQuote::isWeekend($d),
            ['2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22', '2026-11-23'],
        ));
        $this->assertFalse(DemoQuote::isWeekend('not-a-date'));
    }
}
