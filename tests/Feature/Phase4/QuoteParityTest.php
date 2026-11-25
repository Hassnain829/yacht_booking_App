<?php

namespace Tests\Feature\Phase4;

use App\Enums\PromoAppliesTo;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use App\Services\QuoteService;
use App\Support\Pricing\PromoTerms;
use App\Support\Pricing\QuoteLine;
use App\Support\Pricing\QuoteRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 4.1 — price parity with the mockup (PLAN §15 Phase 4 QA): 20 cases answered by the mockup's own
 * CYB.quote() (tests/Fixtures/mockup-quotes.json) against QuoteService on the seeded catalog, which carries
 * the mockup's boats, experiences, add-ons and rates.
 *
 * Since 4.3 an experience is free and its extras are optional add-ons the guest picks (user decisions 2026-11-25):
 * the mockup's "package fee" is set up here as one suggested extra of the same price, picked first, so its line
 * still matches to the cent. And everything is paid in full (D22), so the fixture's deposit answers are no longer
 * compared.
 *
 * Every price line, the hourly rate and the subtotal match to the cent. The mockup rounds a % discount, tax and
 * gratuity to whole dollars, while QuoteService keeps cents (checkout shows exact cents), so those are compared
 * to the nearest dollar, and the total within $2.
 */
class QuoteParityTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    /** The mockup's package fees (its data.js), as suggested extras of the same price. */
    private const MOCKUP_PACKAGES = ['playpen' => 15000, 'river' => 7500, 'fireworks' => 25000, 'celebration' => 17500, 'proposal' => 29500];

    public function test_quote_service_matches_the_mockup_on_twenty_cases(): void
    {
        $this->seedPublicCatalog();
        CarbonImmutable::setTestNow('2027-04-01 12:00:00');
        foreach (self::MOCKUP_PACKAGES as $slug => $cents) {
            $extra = Addon::factory()->create(['slug' => "{$slug}-package", 'name' => 'Package', 'price_cents' => $cents]);
            Experience::query()->where('slug', $slug)->sole()->extras()->attach($extra, ['sort' => 0]);
        }
        $quotes = app(QuoteService::class);
        $promos = [
            'WELCOME10' => PromoTerms::percent('WELCOME10', 1000, PromoAppliesTo::Charter),
            'SUNSET50' => PromoTerms::fixed('SUNSET50', 5000, PromoAppliesTo::Subtotal),
        ];
        $cases = json_decode((string) file_get_contents(base_path('tests/Fixtures/mockup-quotes.json')), true)['cases'];
        $this->assertCount(20, $cases);

        foreach ($cases as $index => ['input' => $in, 'expected' => $want]) {
            $case = "case {$index} ({$in['yacht']}, {$in['experience']}, {$in['date']}, {$in['hours']} h)";
            $quote = $quotes->quote(new QuoteRequest(
                boat: Boat::query()->where('slug', $in['yacht'])->sole(),
                experience: Experience::query()->where('slug', $in['experience'])->sole(),
                startsAt: QuoteRequest::localStart($in['date'], '12:00'),
                hours: $in['hours'],
                guests: $in['guests'],
                addons: array_map(fn (string $slug) => Addon::query()->where('slug', $slug)->sole(), [
                    ...(isset(self::MOCKUP_PACKAGES[$in['experience']]) ? ["{$in['experience']}-package"] : []), // the guest picks the extra
                    ...$in['addons'],
                ]),
                promo: $promos[$in['promo']] ?? null,
                gratuityPct: $in['gratuity'],
            ));

            $this->assertSame($want['weekend'], $quote->weekend, $case);
            $this->assertSame([$want['rate'], $want['charter'], $want['captain'], $want['subtotal']], [$quote->hourlyRateCents, $quote->charterCents, $quote->captainCents, $quote->subtotalCents], $case);
            $lines = collect($want['lines'])->mapWithKeys(fn (int $cents, string $key) => [$key === 'package' ? "addon-{$in['experience']}-package" : $key => $cents])->all();
            $this->assertSame($lines, collect($quote->lines)->mapWithKeys(fn (QuoteLine $line) => [$line->key => $line->amountCents])->all(), $case);

            foreach (['discount' => $quote->discountCents, 'tax' => $quote->taxCents, 'gratuity' => $quote->gratuityCents] as $name => $cents) {
                $this->assertSame($want[$name], (int) round($cents / 100) * 100, "{$case}: {$name} to the dollar");
            }
            $this->assertLessThanOrEqual(200, abs($want['total'] - $quote->totalCents), "{$case}: total within \$2 (mockup {$want['total']}, ours {$quote->totalCents})");
            $this->assertSame($quote->totalCents, array_sum(array_map(fn (QuoteLine $line) => $line->amountCents, $quote->items())), "{$case}: the lines add up to the total");
        }
    }
}
