<?php

namespace Tests\Feature\Phase4;

use App\Enums\BoatStatus;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\Experience;
use App\Models\User;
use App\Services\PublicCatalog;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 4.3 — the boat page is the booking page (D21: Boatsetter's flow, our design). The card's JSON endpoints
 * (§8.2 set: guest, invalid input 422, valid input, unknown / hidden boat 404, rate limit 429; read-only GETs,
 * so there is no wrong user and no duplicate to guard), the card's starting choices, the /book redirects, and
 * full payment only (D22). Since the user's QA (2026-11-25): an experience is free, has no length of its own and
 * departs in its own window; add-ons (its suggested extras too) are optional, picked at checkout.
 */
class BookingCardTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    private Boat $azure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago')); // a Monday
        $this->seedPublicCatalog();
        $this->azure = Boat::query()->where('slug', 'azure')->sole();
        foreach (range(0, 6) as $day) { // 8 AM – 6 PM every day (Boatsetter's default)
            $this->azure->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        PublicCatalog::flush();
    }

    /** @return array<string, mixed> */
    private function card(string $query = ''): array
    {
        $html = $this->get('/yachts/azure'.$query)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-booking-card="([^"]+)"/', $html);
        preg_match('/data-booking-card="([^"]+)"/', $html, $match);

        return json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_boat_page_is_the_booking_page(): void
    {
        $this->get('/yachts/azure')->assertOk()
            ->assertSee('id="booking-card"', false)
            ->assertSee('Just the boat')
            ->assertSee('Instant confirmation')
            ->assertSee('Moderate cancellation')
            ->assertSee('Things to know')
            ->assertSee('Free cancellation up to 5 days before')
            ->assertSee('Weekday price. Pick a date for yours.')
            ->assertSee('data-sheet-open', false)
            ->assertDontSee('deposit');

        $card = $this->card();
        $this->assertSame(['availability' => url('/yachts/azure/availability'), 'quote' => url('/yachts/azure/quote')], $card['urls']);
        $this->assertSame(['experience' => null, 'date' => null, 'hours' => 3, 'guests' => 6, 'addons' => []], $card['initial']);
        $this->assertSame([3, 4, 5, 6, 8], $card['lengths'], 'the boat\'s lengths from its 3-hour minimum, the same with any experience');
        $this->assertSame(
            ['name' => 'Sunset Skyline Cruise', 'window' => '5:00 PM – 7:30 PM', 'times' => ['17:00', '17:30', '18:00', '18:30', '19:00', '19:30'], 'days' => 'Daily', 'weekdays' => null, 'season' => null, 'extras' => 'Free'],
            $card['experiences']['sunset'],
        );
        $this->assertSame(['Wed & Sat', [3, 6], '8:00 PM – 9:00 PM'], [$card['experiences']['fireworks']['days'], $card['experiences']['fireworks']['weekdays'], $card['experiences']['fireworks']['window']]);
        $this->get('/yachts/azure')->assertSee('Sunset Skyline Cruise · 5:00 PM – 7:30 PM')->assertSee('Daily · departs 5:00 PM – 7:30 PM');
        $this->assertSame(120, $card['freeHours']);
        $this->assertArrayNotHasKey('hourly_rate_cents', $card, 'the card gets choices and URLs, never prices to add up');
    }

    public function test_the_card_starts_from_the_links_choices_and_drops_what_does_not_fit(): void
    {
        $this->assertSame(['experience' => 'sunset', 'date' => '2027-05-12', 'hours' => 6, 'guests' => 13, 'addons' => ['champagne', 'decor']], $this->card('?experience=sunset&date=2027-05-12&hours=6&guests=99&addons=champagne,nope,decor,champagne')['initial']);
        $this->assertSame(['experience' => null, 'date' => null, 'hours' => 3, 'guests' => 1, 'addons' => []], $this->card('?experience=corporate&date=2020-01-01&hours=99&guests=-4&addons[]=%3Cb%3E')['initial'], 'Azure does not offer corporate outings');
        $this->get('/yachts/azure?addons=champagne')->assertSee('name="addons" value="champagne"', false)->assertSee('Champagne toast', false); // a pricing package's add-on, priced on the card
        $this->get('/yachts/azure?experience=%3Cscript%3E&date=2027-02-30')->assertOk()->assertDontSee('<script>', false);
    }

    public function test_the_old_book_address_redirects_to_the_boat_page_or_the_fleet(): void
    {
        $this->get('/book?yacht=azure&experience=sunset&date=2027-05-12&hours=3&guests=6')
            ->assertRedirect('/yachts/azure?experience=sunset&date=2027-05-12&guests=6&hours=3#book');
        $this->get('/book?experience=sunset&guests=8&date=2027-05-12')->assertRedirect('/yachts?experience=sunset&date=2027-05-12&guests=8');
        $this->get('/book?yacht=azure&experience=corporate')->assertRedirect('/yachts/azure#book');
        $this->get('/book?yacht=%3Cscript%3E&guests=abc&date=tomorrow')->assertRedirect('/yachts');
    }

    public function test_a_month_of_day_statuses(): void
    {
        $days = $this->getJson('/yachts/azure/availability?month=2027-05&hours=3')->assertOk()->json('data.days');

        $this->assertCount(31, $days);
        $this->assertSame(['past', 'available', 'available'], [$days['2027-05-02'], $days['2027-05-03'], $days['2027-05-12']]);

        $sunset = $this->getJson('/yachts/azure/availability?month=2027-05&hours=3&experience=sunset')->assertOk()->json('data.days');
        $this->assertSame('available', $sunset['2027-05-12'], 'the sunset window 5:00 – 6:00 PM sits inside the boat\'s start times');
        $fireworks = $this->getJson('/yachts/azure/availability?month=2027-05&hours=3&experience=fireworks')->assertOk()->json('data.days');
        $this->assertSame(['unavailable', 'unavailable'], [$fireworks['2027-05-12'], $fireworks['2027-05-13']], 'fireworks depart at 8 PM, after the boat\'s last start time');
    }

    public function test_a_days_start_times_with_booked_ones_marked(): void
    {
        $this->azure->forceFill(['allow_multiple_bookings' => true])->save(); // else the whole day is taken
        Booking::factory()->for($this->azure)->at(CarbonImmutable::parse('2027-05-12 12:00', 'America/Chicago')->utc(), 3)->create();

        $response = $this->getJson('/yachts/azure/availability?date=2027-05-12&hours=3&guests=6')->assertOk();
        $slots = collect($response->json('data.slots'))->keyBy('time');

        $this->assertSame(['time' => '08:00', 'label' => '8:00 AM', 'available' => true, 'reason' => null], $slots['08:00']);
        $this->assertSame([false, 'Booked'], [$slots['12:00']['available'], $slots['12:00']['reason']]);
        $this->assertSame(['date', 'slots'], array_keys($response->json('data')));
        $this->assertStringNotContainsString('CYB-', $response->getContent(), 'never another trip\'s details');
    }

    public function test_the_live_price_with_every_line_paid_in_full(): void
    {
        $estimate = $this->getJson('/yachts/azure/quote?hours=3&guests=6')->assertOk()->json('data');
        $this->assertTrue($estimate['estimate']);
        $this->assertSame(['charter', 'captain', 'cleaning'], array_column($estimate['lines'], 'key'));
        $this->assertSame(135000 + 25500 + 15000 + $estimate['tax_cents'], $estimate['total_cents']);
        $this->assertNull($estimate['free_cancellation_until']);
        $this->assertArrayNotHasKey('deposit_cents', $estimate);

        $decor = Addon::query()->where('slug', 'decor')->sole();
        Experience::query()->where('slug', 'sunset')->sole()->extras()->attach($decor, ['sort' => 0]);
        PublicCatalog::flush();

        $trip = $this->getJson('/yachts/azure/quote?hours=3&guests=6&experience=sunset&date=2027-05-15&time=17:30&addons[]=champagne')->assertOk()->json('data');
        $this->assertSame([false, true], [$trip['estimate'], $trip['weekend']]);
        $this->assertSame(['charter', 'captain', 'addon-champagne', 'cleaning'], array_column($trip['lines'], 'key'), 'the experience\'s suggested décor is charged only when picked');
        $this->assertSame('Add-on', $trip['lines'][2]['description']);
        $this->assertSame('2027-05-10T22:30:00Z', $trip['free_cancellation_until'], '5 days before 5:30 PM Chicago time');
        $this->assertSame('May 10, 5:30 PM', $trip['free_cancellation_label']);
        $this->assertSame(['name' => 'Moderate', 'url' => route('legal.cancellation')], $trip['policy']);
    }

    public function test_checkout_without_a_fitting_start_time_goes_back_to_the_boats_card(): void
    {
        $this->get('/checkout?yacht=azure&experience=sunset&date=2027-05-12&time=10:00&hours=3&guests=6&addons=champagne')
            ->assertRedirect(route('yachts.show', ['azure', 'experience' => 'sunset', 'date' => '2027-05-12', 'hours' => 3, 'guests' => 6, 'addons' => 'champagne']).'#book')
            ->assertSessionHas('booking_card_error', "Sunset Skyline Cruise isn't available at 10:00 AM: it departs 5:00 PM – 7:30 PM. Pick a start time inside that window, or just book the boat.");
        $this->get('/yachts/azure?experience=sunset&date=2027-05-12')->assertSee('Sunset Skyline Cruise isn&#039;t available at 10:00 AM', false);
        $this->get('/yachts/azure')->assertDontSee('isn&#039;t available at 10:00 AM', false);
    }

    public function test_checkout_offers_every_add_on_unticked_with_the_experiences_suggestions_first(): void
    {
        $decor = Addon::query()->where('slug', 'decor')->sole();
        Experience::query()->where('slug', 'sunset')->sole()->extras()->attach($decor, ['sort' => 0]);
        PublicCatalog::flush();
        $guest = User::factory()->create();
        $trip = ['yacht' => 'azure', 'experience' => 'sunset', 'date' => '2027-05-12', 'time' => '17:30', 'hours' => 3, 'guests' => 6];

        $this->actingAs($guest)->post('/checkout', $trip + ['idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $booking = Booking::query()->latest('id')->firstOrFail();
        $html = $this->get("/checkout/{$booking->ulid}")->assertOk()->assertSee('Make it yours')->assertSee('Suggested for Sunset Skyline Cruise')->getContent();
        $this->assertLessThan(strpos($html, 'value="catering"'), strpos($html, 'value="decor"'), 'the suggested extra comes first');
        $this->assertSame(7, substr_count($html, 'name="addons[]"'), 'every add-on is offered');
        $this->assertStringNotContainsString('checked', substr($html, strpos($html, 'value="decor"'), 60), 'nothing is ticked for the guest');
        $this->assertSame(['charter', 'captain', 'cleaning', 'tax', 'gratuity'], $booking->items->pluck('type')->map->value->all(), 'nothing is charged until ticked');

        // A pricing package's add-on comes ticked (the guest can untick it).
        $this->post('/checkout', ['time' => '18:00', 'addons' => 'decor', 'idempotency_key' => (string) Str::uuid()] + $trip)->assertRedirect();
        $picked = Booking::query()->latest('id')->firstOrFail();
        $this->assertContains('addon', $picked->items->pluck('type')->map->value->all());
        $page = $this->get("/checkout/{$picked->ulid}")->getContent();
        $this->assertStringContainsString('checked', substr($page, strpos($page, 'value="decor"'), 60));
    }

    public function test_choices_that_dont_fit_are_refused_on_their_field(): void
    {
        $this->getJson('/yachts/azure/availability?month=2027-05&hours=7')->assertUnprocessable()->assertJsonValidationErrors(['hours']);
        $this->getJson('/yachts/azure/availability?month=2027-05&hours=3&guests=40')->assertUnprocessable()->assertJsonValidationErrors(['guests']);
        $this->getJson('/yachts/azure/availability?month=2027-05&hours=3&experience=corporate')->assertUnprocessable()->assertJsonValidationErrors(['experience']);
        $this->getJson('/yachts/azure/availability?month=2027-05&hours=3&experience=no-such')->assertUnprocessable()->assertJsonValidationErrors(['experience']);
        $this->getJson('/yachts/azure/availability?month=2020-01&hours=3')->assertUnprocessable()->assertJsonValidationErrors(['month']);
        $this->getJson('/yachts/azure/availability?month=2027-05&date=2027-05-12&hours=3')->assertUnprocessable()->assertJsonValidationErrors(['month']);
        $this->getJson('/yachts/azure/availability?hours=3')->assertUnprocessable()->assertJsonValidationErrors(['month', 'date']);
        $this->getJson('/yachts/azure/availability?date=2027-05-01&hours=3')->assertUnprocessable()->assertJsonValidationErrors(['date']);

        $this->getJson('/yachts/azure/quote?hours=3')->assertUnprocessable()->assertJsonValidationErrors(['guests']);
        $this->getJson('/yachts/azure/quote?hours=7&guests=6')->assertUnprocessable()->assertJsonValidationErrors(['hours']);
        $this->getJson('/yachts/azure/quote?hours=3&guests=6&date=2027-05-12&time=09:15')->assertUnprocessable()->assertJsonValidationErrors(['time']);
        $this->getJson('/yachts/azure/quote?hours=3&guests=6&addons[]=nope&addons[]=nope')->assertUnprocessable()->assertJsonValidationErrors(['addons.0']);
        $this->getJson('/yachts/azure/quote?hours=3&guests=6&total_cents=1')->assertOk()->assertJsonPath('data.subtotal_cents', 175500); // a posted price is ignored
    }

    public function test_a_boat_that_is_not_published_answers_404(): void
    {
        $this->getJson('/yachts/no-such-boat/quote?hours=3&guests=2')->assertNotFound();
        Boat::query()->where('slug', 'golden')->update(['status' => BoatStatus::Draft]);
        $this->getJson('/yachts/golden/availability?month=2027-05&hours=3')->assertNotFound();
        Boat::query()->where('slug', 'onyx')->first()->delete();
        $this->getJson('/yachts/onyx/quote?hours=3&guests=2')->assertNotFound();
    }

    public function test_an_unavailable_boat_has_no_free_day(): void
    {
        $this->azure->forceFill(['booking_enabled' => false])->save();

        $days = $this->getJson('/yachts/azure/availability?month=2027-05&hours=3')->assertOk()->json('data.days');
        $this->assertNotContains('available', $days);
    }

    public function test_the_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/yachts/azure/quote?hours=3&guests=2')->assertOk();
        }
        $this->getJson('/yachts/azure/quote?hours=3&guests=2')->assertTooManyRequests();

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/yachts/azure/availability?date=2027-05-12&hours=3');
        }
        $this->getJson('/yachts/azure/availability?date=2027-05-12&hours=3')->assertTooManyRequests();
    }
}
