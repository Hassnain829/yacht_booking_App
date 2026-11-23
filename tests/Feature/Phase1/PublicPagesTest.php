<?php

namespace Tests\Feature\Phase1;

use App\Models\Boat;
use App\Services\PublicCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 1.2 — public pages: content renders from the demo catalog, server-side filters work
 * without JS, untrusted query values are ignored (never echoed), and unavailable boats can't be booked.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPublicCatalog(); // the pages read the database since 3.5
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function pages(): array
    {
        return [
            'home' => ['/', ['Your Private Yacht', 'id="fleet-panel-all"', 'id="fleet-panel-large"', 'data-quick-book', 'Loved by']],
            'yachts' => ['/yachts', ['Our <em>Fleet</em>', 'id="yacht-grid"', 'Compare at a glance']],
            'yacht' => ['/yachts/azure', ['The Azure', 'id="y-book-form"', 'Experiences on this yacht', 'Departure location']],
            'experiences' => ['/experiences', ['Experiences <em>&amp; Events</em>', 'id="fireworks"', 'All-in from']],
            'pricing' => ['/pricing', ['Real examples', 'id="estimator"', 'Yacht rates', 'id="policies"']],
            'about' => ['/about', ['at your service', 'Captains who love this lake', 'Four Chicago harbors']],
            'contact' => ['/contact', ['Send us a message', 'name="_token"', 'name="website"', 'Quick answers']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('pages')]
    public function test_page_renders_its_content(string $url, array $expected): void
    {
        $response = $this->get($url)->assertOk();
        foreach ($expected as $text) {
            $response->assertSee($text, false);
        }

        $html = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html, 'No inline <script> (CSP)');
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/i', $html, 'No inline style attributes (CSP)');
    }

    public function test_fleet_renders_every_boat_with_filter_data(): void
    {
        $html = $this->get('/yachts')->getContent();

        preg_match_all('/<article[^>]*data-type="[^"]*"[^>]*>/', $html, $cards);
        $this->assertCount(6, $cards[0]);
        $this->assertSame(0, $this->hiddenCount($html), 'No filter → every card visible');
    }

    public function test_fleet_filters_on_the_server_without_js(): void
    {
        $html = $this->get('/yachts?type=Sailboat')->assertOk()->getContent();

        $this->assertStringContainsString('id="result-count" class="text-navy-950">1<', $html);
        $this->assertSame(5, $this->hiddenCount($html), 'Five non-sailboats are hidden');

        $sorted = $this->get('/yachts?sort=price-asc')->getContent();
        preg_match_all('/data-price="(\d+)"/', $sorted, $prices);
        $sortedPrices = $prices[1];
        sort($sortedPrices, SORT_NUMERIC);
        $this->assertSame($sortedPrices, $prices[1], 'Cards come out cheapest first');
    }

    public function test_untrusted_query_values_are_ignored_and_never_echoed(): void
    {
        $payload = '<script>alert(1)</script>';

        foreach (['/yachts?type=', '/yachts?harbor=', '/yachts?sort=', '/experiences?category='] as $url) {
            $this->get($url.urlencode($payload))->assertOk()->assertDontSee($payload, false)->assertDontSee('alert(1)', false);
        }

        // Arrays instead of strings must not crash the page either.
        $this->get('/yachts?type[]=x&guests[]=1&sort[]=y')->assertOk();
        $this->get('/experiences?category[]=party')->assertOk();
    }

    public function test_unknown_yacht_is_404(): void
    {
        $this->get('/yachts/does-not-exist')->assertNotFound();
    }

    public function test_unavailable_yacht_shows_the_tag_and_cannot_be_booked(): void
    {
        Boat::query()->where('slug', 'azure')->update(['booking_enabled' => false]); // the admin's Unavailable switch
        PublicCatalog::flush(); // a mass update fires no model events, so clear the cached catalog by hand
        $this->app->forgetInstance(PublicCatalog::class);

        $this->get('/yachts/azure')
            ->assertOk()
            ->assertSee('Unavailable')
            ->assertSee('Not taking bookings right now')
            ->assertDontSee('id="y-book-form"', false)
            ->assertDontSee('book?yacht=azure', false);
    }

    public function test_embedded_page_data_is_valid_escaped_json(): void
    {
        foreach (['/yachts/azure' => 'data-quote', '/pricing' => 'data-estimator'] as $url => $attribute) {
            $html = $this->get($url)->getContent();
            $this->assertMatchesRegularExpression('/'.$attribute.'="([^"]+)"/', $html);
            preg_match('/'.$attribute.'="([^"]+)"/', $html, $match);

            $data = json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
            $this->assertIsArray($data);
            $this->assertArrayHasKey('pricing', $data);
            $this->assertArrayNotHasKey('admin_notes', $data, 'Only display data is embedded');
        }
    }

    public function test_experiences_category_filter_works_server_side(): void
    {
        $html = $this->get('/experiences?category=party')->assertOk()->getContent();

        preg_match_all('/<article id="([a-z-]+)" data-cat="[a-z]+"[^>]*>/', $html, $rows, PREG_SET_ORDER);
        $visible = collect($rows)->reject(fn ($row) => preg_match('/\shidden(=|\s|>)/', $row[0]))->pluck(1)->all();

        $this->assertSame(['playpen', 'celebration'], array_values($visible));
    }

    public function test_pricing_examples_use_the_server_calculator(): void
    {
        $this->get('/pricing')
            ->assertSee('Most booked')
            ->assertSee('Sunset for Two')
            ->assertSee('Corporate Cruise')
            ->assertSee('book?yacht=azure&amp;experience=playpen&amp;hours=4&amp;guests=12&amp;addons=lilypad', false);
    }

    /** Number of fleet cards carrying the `hidden` attribute (not the overflow-hidden class). */
    private function hiddenCount(string $html): int
    {
        preg_match_all('/<article[^>]*data-type="[^"]*"[^>]*>/', $html, $cards);

        return collect($cards[0])->filter(fn (string $tag) => preg_match('/\shidden(=|\s|>)/', $tag))->count();
    }
}
