<?php

namespace Tests\Feature\Phase4;

use App\Enums\PaymentPlan;
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
 * Every price line, the hourly rate and the subtotal match to the cent. The mockup rounds a % discount, tax,
 * gratuity and the deposit to whole dollars, while QuoteService keeps cents (checkout shows exact cents), so
 * those are compared to the nearest dollar, and the total, deposit, amount due now and balance within $2.
 */
class QuoteParityTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    public function test_quote_service_matches_the_mockup_on_twenty_cases(): void
    {
        $this->seedPublicCatalog();
        CarbonImmutable::setTestNow('2027-04-01 12:00:00'); // every case is more than 7 days away: a deposit is allowed
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
                addons: array_map(fn (string $slug) => Addon::query()->where('slug', $slug)->sole(), $in['addons']),
                promo: $promos[$in['promo']] ?? null,
                gratuityPct: $in['gratuity'],
                plan: PaymentPlan::from($in['plan']),
            ));

            $this->assertSame($want['weekend'], $quote->weekend, $case);
            $this->assertSame([$want['rate'], $want['charter'], $want['captain'], $want['subtotal']], [$quote->hourlyRateCents, $quote->charterCents, $quote->captainCents, $quote->subtotalCents], $case);
            $this->assertSame($want['lines'], collect($quote->lines)->mapWithKeys(fn (QuoteLine $line) => [$line->key => $line->amountCents])->all(), $case);

            foreach (['discount' => $quote->discountCents, 'tax' => $quote->taxCents, 'gratuity' => $quote->gratuityCents] as $name => $cents) {
                $this->assertSame($want[$name], (int) round($cents / 100) * 100, "{$case}: {$name} to the dollar");
            }
            foreach (['total' => $quote->totalCents, 'deposit' => $quote->depositCents, 'due_now' => $quote->dueNowCents, 'balance' => $quote->balanceCents] as $name => $cents) {
                $this->assertLessThanOrEqual(200, abs($want[$name] - $cents), "{$case}: {$name} within \$2 (mockup {$want[$name]}, ours {$cents})");
            }
            $this->assertSame($quote->totalCents, array_sum(array_map(fn (QuoteLine $line) => $line->amountCents, $quote->items())), "{$case}: the lines add up to the total");
        }
    }
}
