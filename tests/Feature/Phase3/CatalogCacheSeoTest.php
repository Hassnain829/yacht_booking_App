<?php

namespace Tests\Feature\Phase3;

use App\Enums\BoatStatus;
use App\Models\Amenity;
use App\Models\Boat;
use App\Models\User;
use App\Services\BoatService;
use App\Services\PublicCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 3.6 — the public catalog is cached across requests and cleared by every kind of admin change; the
 * sitemap follows the live catalog; robots.txt keeps private areas (and non-production copies) out of search
 * engines; private pages are noindex and canonical URLs drop the query string.
 */
class CatalogCacheSeoTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPublicCatalog();
        $this->admin = User::factory()->admin()->create();
    }

    /** @return list<string> the SQL run while `$callback` ran */
    private function queries(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $log = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $log;
    }

    /* ------------------------------------------------------------------ cache */

    public function test_the_catalog_is_built_once_and_then_served_from_the_cache(): void
    {
        $this->assertFalse(Cache::has(PublicCatalog::CACHE_KEY));
        $first = $this->queries(fn () => $this->get('/yachts')->assertOk());
        $this->assertTrue(Cache::has(PublicCatalog::CACHE_KEY));
        $this->assertNotEmpty(array_filter($first, fn (string $sql) => str_contains($sql, 'from "boats"')));

        $second = $this->queries(fn () => $this->get('/yachts/azure')->assertOk()->assertSee('The Azure'));
        $this->assertSame([], array_values(array_filter($second, fn (string $sql) => preg_match('/from "(boats|experiences|addons|harbors|media|amenities)"/', $sql) === 1)), 'no catalog queries once cached');
    }

    public function test_every_kind_of_admin_change_clears_the_cache(): void
    {
        $this->get('/yachts')->assertOk(); // build the cache
        $this->actingAs($this->admin);
        $azure = Boat::query()->where('slug', 'azure')->sole();

        // the Bookable switch (a model save)
        $this->patchJson("/admin/boats/{$azure->ulid}/bookable", ['booking_enabled' => false])->assertOk();
        $this->assertFalse(Cache::has(PublicCatalog::CACHE_KEY));
        $this->get('/yachts/azure')->assertSee('Unavailable');

        // a new cover photo (a query update, no model events)
        $second = $azure->photos()->get()[1];
        $this->patch("/admin/boats/{$azure->ulid}/photos/{$second->uuid}", ['move' => 'first'])->assertRedirect();
        $this->get('/yachts')->assertSee('/storage/'.$second->variants['card'], false);

        // the Trash
        $this->delete('/admin/experiences/fireworks', ['reason' => 'Season is over now.', 'password' => 'password', 'dialog' => 'trash-record'])->assertRedirect();
        $this->get('/experiences')->assertDontSee('id="fireworks"', false);

        // an amenity deleted from every boat (ticked on The Azure through the boat form's service)
        $table = Amenity::query()->create(['group' => 'deck', 'name' => 'Teak cockpit table', 'sort' => 1]);
        app(BoatService::class)->save($azure->fresh(), [], ['amenities' => [$table->id], 'experiences' => $azure->experiences()->pluck('experiences.id')->mapWithKeys(fn ($id) => [$id => null])->all(), 'hours' => []], $this->admin);
        $this->get('/yachts/azure')->assertSee('Teak cockpit table');
        $this->delete("/admin/amenities/{$table->ulid}", ['reason' => 'Not on the boat any more.', 'dialog' => 'destroy-record'])->assertRedirect();
        $this->get('/yachts/azure')->assertDontSee('Teak cockpit table');

        // publishing / hiding a boat
        $golden = Boat::query()->where('slug', 'golden')->sole();
        $this->post("/admin/boats/{$golden->ulid}/status", ['action' => 'hide'])->assertRedirect();
        $this->get('/yachts/golden')->assertNotFound();
        $this->post("/admin/boats/{$golden->ulid}/status", ['action' => 'publish'])->assertRedirect();
        $this->get('/yachts/golden')->assertOk();
    }

    public function test_the_boat_form_clears_the_cache_when_only_its_relations_change(): void
    {
        $azure = Boat::query()->where('slug', 'azure')->sole();
        $generator = Amenity::query()->create(['group' => 'feature', 'name' => 'Generator', 'sort' => 1]);
        $azure->amenities()->attach($generator);
        PublicCatalog::flush(); // attach() fires no model events
        $this->get('/yachts/azure')->assertSee('Generator'); // build the cache

        $this->actingAs($this->admin)->get("/admin/boats/{$azure->ulid}/edit")->assertOk();
        app(BoatService::class)->save($azure->fresh(), [], ['amenities' => [], 'experiences' => [], 'hours' => []], $this->admin);

        $this->get('/yachts/azure')->assertDontSee('>Generator<', false)->assertSee('Flybridge sun lounge'); // a highlight line stays
        $this->assertSame([], app(PublicCatalog::class)->yacht('azure')->experiences);
    }

    /* ------------------------------------------------------------------ sitemap + robots */

    public function test_the_sitemap_lists_the_public_pages_and_live_boats_only(): void
    {
        Boat::query()->where('slug', 'golden')->sole()->forceFill(['status' => BoatStatus::Draft])->save();
        Boat::query()->where('slug', 'onyx')->sole()->delete();

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'valid XML');
        $entries = [];
        foreach ($xml->url as $url) { // (collect() would keep only the last <url>)
            $entries[(string) $url->loc] = (string) $url->lastmod;
        }
        $urls = array_keys($entries);

        $this->assertContains(route('home'), $urls);
        $this->assertContains(route('yachts.show', 'azure'), $urls);
        $this->assertContains(route('legal.privacy'), $urls);
        $this->assertNotContains(route('yachts.show', 'golden'), $urls);
        $this->assertNotContains(route('yachts.show', 'onyx'), $urls);
        $this->assertSame([], array_values(array_filter($urls, fn (string $url) => preg_match('#/(admin|owner|account|checkout|login)#', $url) === 1)));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $entries[route('yachts.show', 'azure')]);
    }

    public function test_robots_blocks_everything_off_production_and_private_areas_on_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSeeText("User-agent: *\nDisallow: /\n", false);

        $this->app['env'] = 'production';
        $robots = $this->get(str_replace('http://', 'https://', route('robots')))->assertOk()->getContent(); // production forces https
        $this->assertStringContainsString("Disallow: /admin\n", $robots);
        $this->assertStringContainsString("Disallow: /checkout\n", $robots);
        $this->assertStringNotContainsString("Disallow: /\n", $robots);
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $robots);
    }

    public function test_private_pages_are_noindex_and_canonical_urls_drop_the_query(): void
    {
        $this->get('/checkout')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
        $this->get('/booking/confirmation')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);

        $this->get('/yachts?type=Sailboat&sort=price-asc')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('yachts.index').'">', false)
            ->assertDontSee('<meta name="robots"', false)
            ->assertSee('<meta property="og:locale" content="en_US">', false)
            ->assertSee('<link rel="sitemap" type="application/xml" href="'.route('sitemap').'">', false);
    }
}
