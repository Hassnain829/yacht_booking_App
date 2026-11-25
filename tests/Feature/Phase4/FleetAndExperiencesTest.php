<?php

namespace Tests\Feature\Phase4;

use App\Models\Addon;
use App\Models\Amenity;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\Experience;
use App\Services\PublicCatalog;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 4.4 — the fleet's Boatsetter-style filter bar (whitelisted query string, the same rules as the JS) with
 * "Free on this date" from the availability calendar, and the Experiences page as Chicago categories whose boats
 * open the booking card with the experience picked (D21). Since the user's QA (2026-11-25): every link to a boat
 * page carries the search; an experience is free, has no length of its own and departs in its own window; its
 * extras are optional, offered at checkout.
 */
class FleetAndExperiencesTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago'));
        $this->seedPublicCatalog();
    }

    /** @return list<string> the slugs of the cards shown (not `hidden`), in page order */
    private function visible(string $query = ''): array
    {
        $html = $this->get('/yachts'.$query)->assertOk()->getContent();
        preg_match_all('/<article([^>]*data-type="[^"]*"[^>]*)>.*?href="[^"]*\/yachts\/([a-z0-9-]+)(?:\?[^"]*)?"/s', $html, $cards, PREG_SET_ORDER);

        return collect($cards)->reject(fn (array $card) => preg_match('/\shidden(=|\s|$)/', $card[1]))->map(fn (array $card) => $card[2])->values()->all();
    }

    /** Every day 8 AM – 6 PM for one demo boat (demo boats start with no start times). */
    private function openStartTimes(string $slug): Boat
    {
        $boat = Boat::query()->where('slug', $slug)->sole();
        foreach (range(0, 6) as $day) {
            $boat->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        PublicCatalog::flush();

        return $boat;
    }

    public function test_the_filter_bar_filters_on_the_server_like_the_js(): void
    {
        $all = $this->visible();
        $this->assertCount(6, $all);

        $this->assertEqualsCanonicalizing(['skyline', 'lakeside', 'onyx'], $this->visible('?guests=20'));
        $this->assertEqualsCanonicalizing(['azure', 'golden', 'lakeside'], $this->visible('?experience=playpen'));
        $this->assertEqualsCanonicalizing(['azure', 'golden', 'lakeside'], $this->visible('?experience=playpen&hours=8'), 'an experience has no length of its own: the boat\'s lengths');
        $this->assertCount(6, $this->visible('?hours=5'), 'no lengths ticked: every length from the minimum up to 8 hours');
        $this->assertSame(['windward'], $this->visible('?hours=2'), 'only Windward goes as short as 2 hours');
        $this->assertEqualsCanonicalizing(['windward', 'golden', 'azure'], $this->visible('?price=under-500'));
        $this->assertEqualsCanonicalizing(['skyline', 'lakeside', 'onyx'], $this->visible('?length=over-60&guests=14'));
        $this->assertSame(['windward'], $this->visible('?length=40-60&harbor=belmont'));
        $this->assertSame(['onyx', 'lakeside', 'skyline', 'azure', 'golden', 'windward'], $this->visible('?sort=price-desc'));
    }

    public function test_on_board_filters_need_every_ticked_amenity(): void
    {
        $wifi = Amenity::factory()->create(['name' => 'Starlink WiFi']);
        $grill = Amenity::factory()->create(['name' => 'Pellet grill']);
        Boat::query()->where('slug', 'azure')->sole()->amenities()->attach([$wifi->id, $grill->id]);
        Boat::query()->where('slug', 'skyline')->sole()->amenities()->attach([$wifi->id]);
        PublicCatalog::flush(); // a pivot attach fires no model events

        $this->get('/yachts')->assertSee('value="'.$wifi->ulid.'"', false)->assertSee('More filters');
        $this->assertEqualsCanonicalizing(['azure', 'skyline'], $this->visible('?amenities[]='.$wifi->ulid));
        $this->assertSame(['azure'], $this->visible('?amenities[]='.$wifi->ulid.'&amenities[]='.$grill->ulid));
    }

    public function test_unknown_filter_values_are_ignored_and_never_echoed(): void
    {
        $html = $this->get('/yachts?guests=abc&price=free&length=%3Cx%3E&hours=7&experience=%3Cscript%3E&type=%3Cb%3E&sort=evil&amenities[]=nope&date=2027-02-30')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('value="2027-02-30"', $html);
        $this->assertCount(6, $this->visible('?guests=abc&price=free&hours=7&sort=evil&date=2027-02-30'));
    }

    public function test_a_date_shows_only_the_boats_free_that_day(): void
    {
        $azure = $this->openStartTimes('azure');
        $this->openStartTimes('windward');
        Booking::factory()->for(Boat::query()->where('slug', 'windward')->sole())->at(CarbonImmutable::parse('2027-05-12 09:00', 'America/Chicago')->utc(), 2)->create(); // one trip a day

        $html = $this->get('/yachts?date=2027-05-12&guests=6')->assertOk()->getContent();
        $this->assertSame(['azure'], $this->visible('?date=2027-05-12&guests=6'), 'boats without start times, and a boat booked that day, are left out');
        $this->assertStringContainsString('yachts free on Wed, May 12', $html);
        $this->assertStringContainsString('Free on May 12 · 21 start times', $html);
        $this->assertStringContainsString('/yachts/azure?date=2027-05-12&amp;guests=6#book', $html, 'Book carries the search to the booking card');
        $this->assertStringContainsString('href="'.url('/yachts/azure').'?date=2027-05-12&amp;guests=6" data-yacht-link="details"', $html, 'so do the photo, the name and the details arrow');

        $azure->forceFill(['booking_enabled' => false])->save(); // the Unavailable tag
        $this->assertSame([], $this->visible('?date=2027-05-12'));
    }

    public function test_every_card_link_carries_the_experience_to_the_boats_booking_card(): void
    {
        $html = $this->get('/yachts?experience=playpen&guests=8')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'href="'.url('/yachts/azure').'?guests=8&amp;experience=playpen" data-yacht-link="details"'), 'photo, name and arrow');
        $this->assertStringContainsString('href="'.url('/yachts/azure').'?guests=8&amp;experience=playpen#book" data-yacht-link="book"', $html);
        $this->get('/yachts/azure?guests=8&experience=playpen')->assertSee('<option value="playpen" selected>', false);
    }

    public function test_the_experiences_page_lists_the_boats_and_books_through_their_card(): void
    {
        $html = $this->get('/experiences')->assertOk()->getContent();

        $this->assertStringContainsString('/yachts?experience=playpen', $html, 'several boats: Book opens the fleet filtered');
        $this->assertStringContainsString('/yachts/azure?experience=playpen#book', $html, 'each boat opens its booking card with the experience picked');
        $this->assertStringContainsString('3 yachts offer it', $html);
        $this->assertStringContainsString('The experience is free: you pay only for the yacht and its captain, by the hour.', $html);
        $this->assertStringContainsString('5:00 PM – 7:30 PM', $html, 'the sunset cruise departs in the evening');
        $this->assertStringContainsString('8:00 PM – 9:00 PM', $html, 'the fireworks at night');
        $this->assertStringContainsString('Your choice', $html, 'no trip length of its own');
        $this->assertStringNotContainsString('>Duration<', $html);

        $decor = Addon::query()->where('slug', 'decor')->sole();
        Experience::query()->where('slug', 'celebration')->sole()->extras()->attach($decor, ['sort' => 0]);
        PublicCatalog::flush();
        $this->get('/experiences')->assertSee('Optional extras at checkout:')->assertSee('Celebration décor $175');

        Boat::query()->whereNot('slug', 'azure')->update(['booking_enabled' => false]);
        PublicCatalog::flush();
        $this->get('/experiences')->assertSee('/yachts/azure?experience=playpen#book', false); // one bookable boat: straight to it
    }

    public function test_trips_from_prices_come_from_the_quote_service(): void
    {
        // Windward, sunset, weekday, 2 h, 1 guest: $225 × 2 + captain $65 × 2 + fuel & cleaning $75, before tax.
        $this->get('/experiences')->assertOk()->assertSee('Trips from')->assertSee(money(45000 + 13000 + 7500, 'never'));
    }
}
