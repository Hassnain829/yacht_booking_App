<?php

namespace Tests\Feature\Phase4;

use App\Enums\BookingItemType;
use App\Enums\CaptainSource;
use App\Enums\Payee;
use App\Enums\PromoAppliesTo;
use App\Exceptions\QuoteException;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use App\Models\User;
use App\Services\QuoteService;
use App\Services\SettingsService;
use App\Support\Pricing\PromoTerms;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteLine;
use App\Support\Pricing\QuoteRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4.1 / 4.3 — QuoteService (PLAN §7.1): the rules the mockup didn't have — each boat's weekend rate, fixed
 * prices per length, the boat's own lengths (an experience is free, has no length and its extras are optional,
 * 2026-11-25), promo limits, who each line pays (§7.5) — and the choices it refuses. Always the full price (D22). The mockup's own numbers are
 * matched in QuoteParityTest.
 */
class QuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuoteService $quotes;

    private Boat $boat;

    private Experience $sunset;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2027-05-01 15:00:00'); // a Saturday, 10 AM in Chicago
        $this->quotes = app(QuoteService::class);
        // $450/hr, captain $85/hr, fuel & cleaning $150 — the mockup's Azure.
        $this->boat = Boat::factory()->create(['hourly_rate_cents' => 45000, 'captain_rate_cents' => 8500, 'cleaning_fee_cents' => 15000, 'min_hours' => 3, 'durations' => null, 'max_guests' => 13]);
        $this->sunset = Experience::factory()->create(['name' => 'Sunset Cruise']);
        $this->boat->experiences()->attach($this->sunset);
    }

    /** @param  array<string, mixed>  $overrides */
    private function quote(array $overrides = []): Quote
    {
        return $this->quotes->quote(new QuoteRequest(...array_merge([
            'boat' => $this->boat->fresh(),
            'experience' => $this->sunset,
            'startsAt' => QuoteRequest::localStart('2027-06-02', '18:00'), // a Wednesday, a month away
            'hours' => 3,
            'guests' => 6,
        ], $overrides)));
    }

    /** @return array<string, int> line key => cents */
    private function amounts(Quote $quote): array
    {
        return collect($quote->items())->mapWithKeys(fn (QuoteLine $line) => [$line->key => $line->amountCents])->all();
    }

    public function test_a_weekday_quote_adds_up_like_the_mockup(): void
    {
        $quote = $this->quote(['gratuityPct' => 18]);

        $this->assertFalse($quote->weekend);
        $this->assertSame(['charter' => 135000, 'captain' => 25500, 'cleaning' => 15000, 'tax' => 15795, 'gratuity' => 28890], $this->amounts($quote));
        $this->assertSame([175500, 0, 15795, 28890, 220185], [$quote->subtotalCents, $quote->discountCents, $quote->taxCents, $quote->gratuityCents, $quote->totalCents]);
        $this->assertSame('$450/hr × 3 hrs', $quote->lines[0]->description);
        $this->assertSame($quote->totalCents, array_sum($this->amounts($quote)));
    }

    public function test_fri_to_sun_in_chicago_time_use_the_weekend_rate_rounded_to_whole_dollars(): void
    {
        $this->boat->forceFill(['hourly_rate_cents' => 165000])->save(); // × 1.15 = $1,897.50 → $1,898

        $friday = $this->quote(['startsAt' => QuoteRequest::localStart('2027-06-04', '09:00')]);
        $this->assertTrue($friday->weekend);
        $this->assertSame([189800, 569400], [$friday->hourlyRateCents, $friday->charterCents]);
        $this->assertSame('$1,898/hr × 3 hrs · weekend rate', $friday->lines[0]->description);

        // Thursday 11:30 PM in Chicago is already Friday in UTC: still the weekday rate.
        $thursdayNight = QuoteRequest::localStart('2027-06-03', '23:30');
        $this->assertSame('Fri 04:30', $thursdayNight->format('D H:i'), 'in UTC it is Friday');
        $this->assertFalse($this->quote(['startsAt' => $thursdayNight])->weekend);
        $this->assertTrue($this->quote(['startsAt' => QuoteRequest::localStart('2027-06-06', '20:00')])->weekend, 'Sunday');
    }

    public function test_a_boats_own_weekend_rate_wins_over_the_site_setting(): void
    {
        $saturday = ['startsAt' => QuoteRequest::localStart('2027-06-05', '12:00')];
        $this->assertSame(51800, $this->quote($saturday)->hourlyRateCents, 'site setting: +15%');

        app(SettingsService::class)->update(['weekend_multiplier_bp' => 12000], User::factory()->admin()->create(), 'Summer pricing');
        $this->assertSame(54000, $this->quote($saturday)->hourlyRateCents, 'site setting changed: +20%');

        $this->boat->forceFill(['weekend_multiplier_bp' => 10000])->save(); // this boat: no weekend markup
        $this->assertSame([45000, 10000], [$this->quote($saturday)->hourlyRateCents, $this->quote($saturday)->pricingSnapshot['weekend_multiplier_bp']]);
    }

    public function test_a_fixed_price_for_a_length_replaces_rate_times_hours_on_every_day(): void
    {
        $this->boat->forceFill(['duration_prices' => ['3' => 120000]])->save();

        foreach (['2027-06-02', '2027-06-05'] as $date) { // a Wednesday and a Saturday
            $quote = $this->quote(['startsAt' => QuoteRequest::localStart($date, '12:00')]);
            $this->assertSame([120000, 120000, 1, 'Fixed price for 3 hrs'], [$quote->charterCents, $quote->fixedPriceCents, $quote->lines[0]->quantity, $quote->lines[0]->description], $date);
        }
        $this->assertSame([180000, null], [$this->quote(['hours' => 4])->charterCents, $this->quote(['hours' => 4])->fixedPriceCents], 'other lengths use the hourly rate');
    }

    public function test_the_admin_can_add_the_weekend_rate_to_a_boats_fixed_prices(): void
    {
        $this->boat->forceFill(['duration_prices' => ['3' => 120050], 'weekend_fixed_prices' => true])->save();

        $saturday = $this->quote(['startsAt' => QuoteRequest::localStart('2027-06-05', '12:00')]);
        $this->assertSame([138100, 'Fixed price for 3 hrs · weekend rate', true], [$saturday->charterCents, $saturday->lines[0]->description, $saturday->pricingSnapshot['weekend_fixed_prices']], '$1,200.50 × 1.15 = $1,380.58, to whole dollars');
        $this->assertSame([120050, 'Fixed price for 3 hrs'], [$this->quote()->charterCents, $this->quote()->lines[0]->description], 'weekdays keep the fixed price');
    }

    public function test_lengths_are_the_boats_own_never_below_its_minimum_whatever_the_experience(): void
    {
        $this->assertSame([3, 4, 5, 6, 8], $this->quotes->durations($this->boat), 'none ticked: every standard length from the 3-hour minimum to a full day');

        $this->boat->forceFill(['durations' => [2, 4, 6]])->save();
        $this->assertSame([4, 6], $this->quotes->durations($this->boat), 'the ticked lengths, never below the minimum');
        $this->assertSame(6, $this->quote(['hours' => 6])->hours, 'a 6-hour sunset cruise: the experience has no length of its own');

        $this->boat->forceFill(['durations' => null, 'min_hours' => 10])->save();
        $this->assertSame([10], $this->quotes->durations($this->boat));
    }

    public function test_an_experience_is_free_and_its_suggested_extras_are_charged_only_when_picked(): void
    {
        $decor = Addon::factory()->create(['name' => 'Celebration décor', 'slug' => 'decor', 'price_cents' => 17500]);
        $catering = Addon::factory()->perGuest(3500)->create(['name' => 'Catering', 'slug' => 'catering']);
        $birthday = Experience::factory()->create(['name' => 'Birthdays']);
        $birthday->extras()->attach([$decor->id => ['sort' => 0], $catering->id => ['sort' => 1]]);
        $this->boat->experiences()->attach($birthday);

        $plain = $this->quote(['experience' => $birthday->fresh(), 'guests' => 8]);
        $this->assertSame(['charter', 'captain', 'cleaning'], array_keys(array_diff_key($this->amounts($plain), ['tax' => 0])), 'the experience and its extras add nothing until picked');
        $this->assertSame($this->quote(['experience' => null, 'guests' => 8])->totalCents, $plain->totalCents, 'the same price as just the boat');

        $picked = $this->quote(['experience' => $birthday->fresh(), 'guests' => 8, 'addons' => [$catering]]);
        $this->assertSame(['addon-catering' => 28000], array_intersect_key($this->amounts($picked), ['addon-catering' => 0, 'addon-decor' => 0]), 'only the extra the guest ticked');
        $line = collect($picked->lines)->firstWhere('key', 'addon-catering');
        $this->assertSame([BookingItemType::Addon, '$35 × 8 guests', $catering->id, Payee::Platform], [$line->type, $line->description, $line->addonId, $line->payee]);
        $this->assertArrayNotHasKey('experience_extras', $picked->pricingSnapshot);
    }

    public function test_add_ons_flat_once_and_per_guest_times_the_guests(): void
    {
        $catering = Addon::factory()->perGuest(3500)->create(['name' => 'Catering', 'slug' => 'catering']);
        $champagne = Addon::factory()->create(['name' => 'Champagne', 'slug' => 'champagne', 'price_cents' => 12000]);

        $quote = $this->quote(['guests' => 8, 'addons' => [$catering, $champagne, $champagne]]);

        $this->assertSame(['addon-catering' => 28000, 'addon-champagne' => 12000], array_intersect_key($this->amounts($quote), array_flip(['addon-catering', 'addon-champagne'])), 'chosen twice still counts once');
        $line = collect($quote->lines)->firstWhere('key', 'addon-catering');
        $this->assertSame([8, 3500, '$35 × 8 guests', $catering->id], [$line->quantity, $line->unitAmountCents, $line->description, $line->addonId]);
        $this->assertArrayNotHasKey('addon_id', $line->toArray(), 'the numeric id never goes to the browser');
    }

    public function test_promo_codes_percent_or_fixed_capped_and_with_a_minimum(): void
    {
        $charterOnly = $this->quote(['promo' => PromoTerms::percent('welcome10', 1000)]);
        $this->assertSame([13500, 'WELCOME10', 'Discount (WELCOME10)', '10% off yacht charter'], [$charterOnly->discountCents, $charterOnly->promoCode, $charterOnly->adjustments[0]->label, $charterOnly->adjustments[0]->description]);
        $this->assertSame(-13500, $this->amounts($charterOnly)['discount']);
        $this->assertSame((int) round((175500 - 13500) * 0.09), $charterOnly->taxCents, 'tax is on the subtotal after the discount');

        $this->assertSame(17550, $this->quote(['promo' => PromoTerms::percent('ALL10', 1000, PromoAppliesTo::Subtotal)])->discountCents);
        $this->assertSame(10000, $this->quote(['promo' => PromoTerms::percent('BIG50', 5000, PromoAppliesTo::Subtotal, maxDiscountCents: 10000)])->discountCents, 'capped');
        $this->assertSame(135000, $this->quote(['promo' => PromoTerms::fixed('HUGE', 999999, PromoAppliesTo::Charter)])->discountCents, 'never more than what it applies to');

        $short = $this->quote(['promo' => PromoTerms::fixed('SUNSET50', 5000, minSubtotalCents: 200000)]);
        $this->assertSame([0, null, 'SUNSET50 needs a booking of at least $2,000.'], [$short->discountCents, $short->promoCode, $short->promoRejection]);
        $this->assertArrayNotHasKey('discount', $this->amounts($short));
    }

    public function test_gratuity_is_on_the_charter_and_captain_only(): void
    {
        $addon = Addon::factory()->create(['price_cents' => 50000]);

        $this->assertSame((int) round((135000 + 25500) * 0.20), $this->quote(['gratuityPct' => 20, 'addons' => [$addon]])->gratuityCents);
        $this->assertArrayNotHasKey('gratuity', $this->amounts($this->quote()), 'no gratuity: no line');
    }

    public function test_the_whole_price_is_paid_at_checkout(): void
    {
        $quote = $this->quote(['gratuityPct' => 18]);

        $this->assertArrayNotHasKey('deposit_cents', $quote->toArray(), 'D22: no deposit');
        $this->assertArrayNotHasKey('balance_cents', $quote->toArray());
        $this->assertSame($quote->totalCents, $quote->toArray()['total_cents']);
    }

    public function test_each_line_says_who_the_money_goes_to(): void
    {
        $house = $this->quote(['gratuityPct' => 18, 'promo' => PromoTerms::percent('WELCOME10', 1000)]);
        $this->assertSame(['charter' => 'platform', 'captain' => 'platform', 'cleaning' => 'platform', 'discount' => 'platform', 'tax' => 'platform', 'gratuity' => 'captain'], $this->payees($house));

        $this->boat->forceFill(['ownership' => 'owner', 'owner_id' => User::factory()->create()->id, 'captain_source' => CaptainSource::Platform])->save();
        $this->assertSame(['charter' => 'owner', 'captain' => 'platform', 'cleaning' => 'owner', 'tax' => 'platform', 'gratuity' => 'captain'], $this->payees($this->quote(['gratuityPct' => 18])));

        $this->boat->forceFill(['captain_source' => CaptainSource::Owner])->save(); // the owner drives
        $this->assertSame(['charter' => 'owner', 'captain' => 'owner', 'cleaning' => 'owner', 'tax' => 'platform', 'gratuity' => 'owner'], $this->payees($this->quote(['gratuityPct' => 18])));
    }

    /** @return array<string, string> */
    private function payees(Quote $quote): array
    {
        return collect($quote->items())->mapWithKeys(fn (QuoteLine $line) => [$line->key => $line->payee->value])->all();
    }

    public function test_the_snapshot_keeps_the_rates_used(): void
    {
        $snapshot = $this->quote(['startsAt' => QuoteRequest::localStart('2027-06-05', '12:00'), 'gratuityPct' => 15, 'promo' => PromoTerms::fixed('SUNSET50', 5000)])->pricingSnapshot;

        $this->assertSame([45000, true, 11500, 51800, null, 8500, 900, 15, 'SUNSET50', 'house', 'platform'], [
            $snapshot['hourly_rate_cents'], $snapshot['weekend'], $snapshot['weekend_multiplier_bp'], $snapshot['rate_cents'], $snapshot['fixed_price_cents'],
            $snapshot['captain_rate_cents'], $snapshot['tax_rate_bp'], $snapshot['gratuity_pct'],
            $snapshot['promo']['code'], $snapshot['ownership'], $snapshot['captain_source'],
        ]);
        $this->assertArrayNotHasKey('deposit_bp', $snapshot);
        $this->assertSame(Payee::Captain, collect($this->quote(['gratuityPct' => 15])->items())->firstWhere('type', BookingItemType::Gratuity)->payee);
    }

    public function test_choices_that_dont_fit_the_boat_are_refused_on_their_field(): void
    {
        $other = Experience::factory()->create();
        $hidden = Addon::factory()->create(['is_active' => false]);
        $gone = Addon::factory()->create();
        $gone->delete();

        $this->assertRefused('hours', ['hours' => 2], 'Choose one of the trip lengths offered: 3, 4, 5, 6, 8 hours.');
        $this->assertRefused('guests', ['guests' => 14], 'This boat takes 1 to 13 guests.');
        $this->assertRefused('guests', ['guests' => 0]);
        $this->assertRefused('experience', ['experience' => $other], "This boat doesn't offer that experience.");
        $this->assertRefused('addons', ['addons' => [$hidden]]);
        $this->assertRefused('addons', ['addons' => [$gone]]);
        $this->assertRefused('gratuity', ['gratuityPct' => 12]);

        $this->boat->experiences()->updateExistingPivot($this->sunset->id, ['is_active' => false]);
        $this->assertRefused('experience', []);
        $this->boat->experiences()->updateExistingPivot($this->sunset->id, ['is_active' => true]);
        $this->sunset->forceFill(['is_active' => false])->save();
        $this->assertRefused('experience', []);
    }

    /** @param  array<string, mixed>  $overrides */
    private function assertRefused(string $field, array $overrides, ?string $message = null): void
    {
        try {
            $this->quote($overrides);
            $this->fail("Expected a QuoteException on {$field}.");
        } catch (QuoteException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            if ($message !== null) {
                $this->assertSame($message, $exception->errors()[$field][0]);
            }
        }
    }

    public function test_local_start_is_chicago_time_across_daylight_saving(): void
    {
        $this->assertSame('2027-07-05T02:00:00Z', QuoteRequest::localStart('2027-07-04', '21:00')->toIso8601ZuluString(), 'CDT, UTC−5');
        $this->assertSame('2027-01-15T06:00:00Z', QuoteRequest::localStart('2027-01-15')->toIso8601ZuluString(), 'CST, UTC−6, start of the day');
        $this->expectException(\InvalidArgumentException::class);
        QuoteRequest::localStart('06/04/2027');
    }
}
