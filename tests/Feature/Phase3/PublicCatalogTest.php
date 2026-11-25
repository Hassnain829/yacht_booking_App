<?php

namespace Tests\Feature\Phase3;

use App\Enums\BoatStatus;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\User;
use App\Services\PublicCatalog;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 3.5 — the public pages read the database (PublicCatalog): only what the admin made public shows, the
 * Unavailable tag comes from `booking_enabled`, admin edits appear on the site, prices use Settings, images load
 * from /storage (same host), and the pages survive an empty or half-empty catalog.
 */
class PublicCatalogTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPublicCatalog();
    }

    /** The public catalog as the next request sees it (the booking wizard that embedded it is gone since 4.3). */
    private function catalog(): PublicCatalog
    {
        app()->forgetInstance(PublicCatalog::class);

        return app(PublicCatalog::class);
    }

    public function test_only_published_boats_that_are_not_in_the_trash_are_shown(): void
    {
        Boat::query()->where('slug', 'golden')->update(['status' => BoatStatus::Draft]);
        Boat::query()->where('slug', 'onyx')->first()->delete();

        $this->get('/yachts')->assertOk()->assertSee('The Azure')->assertDontSee('Golden Hour')->assertDontSee('Midnight Onyx')
            ->assertSee('Four hand-picked yachts'); // the hero counts the live fleet
        $this->get('/yachts/golden')->assertNotFound();
        $this->get('/yachts/onyx')->assertNotFound();
        $this->assertSame(['azure', 'skyline', 'windward', 'lakeside'], $this->catalog()->yachts()->pluck('slug')->all());
        $this->get('/book?yacht=golden&experience=sunset')->assertRedirect('/yachts?experience=sunset'); // a hidden boat is never opened
    }

    public function test_the_admins_edits_appear_on_the_site(): void
    {
        $admin = User::factory()->admin()->create();
        $azure = Boat::query()->where('slug', 'azure')->sole();
        $this->actingAs($admin)->patchJson("/admin/boats/{$azure->ulid}/bookable", ['booking_enabled' => false])->assertOk();
        $azure->forceFill(['title' => 'The Azure II', 'hourly_rate_cents' => 51000])->save();

        $this->get('/yachts/azure')->assertOk()->assertSee('The Azure II')->assertSee('Unavailable');
        $boat = $this->catalog()->yacht('azure');
        $this->assertSame([false, 51000], [$boat->booking_enabled, $boat->hourly_rate_cents]);
        $this->get('/yachts/azure')->assertDontSee('id="booking-card"', false); // no booking card on an Unavailable boat
    }

    public function test_hidden_or_trashed_experiences_add_ons_and_harbors_disappear(): void
    {
        Experience::query()->where('slug', 'fireworks')->update(['is_active' => false]);
        Experience::query()->where('slug', 'proposal')->first()->delete();
        Addon::query()->where('slug', 'champagne')->update(['is_active' => false]);
        Harbor::query()->where('slug', 'belmont')->update(['is_active' => false]);

        $this->get('/experiences')->assertOk()->assertDontSee('id="fireworks"', false)->assertDontSee('id="proposal"', false)->assertSee('id="sunset"', false);
        $catalog = $this->catalog();
        $this->assertNotContains('fireworks', $catalog->experiences()->pluck('slug'));
        $this->assertNotContains('champagne', $catalog->addons()->pluck('slug'));
        $this->assertNotContains('fireworks', $catalog->yacht('azure')->experiences, 'a boat no longer offers a hidden experience');
        $this->get('/yachts/azure')->assertOk()->assertDontSee('value="fireworks"', false); // nor in its booking card

        $this->get('/about')->assertOk()->assertDontSee('>Belmont Harbor</h3>', false)->assertSee('>Navy Pier</h3>', false);
        $this->get('/yachts/windward')->assertOk()->assertSee('Belmont Harbor'); // its boat still shows where it departs from
    }

    public function test_images_come_from_storage_on_the_same_host_and_a_boat_without_photos_gets_a_placeholder(): void
    {
        $azure = Boat::query()->where('slug', 'azure')->sole();
        $cover = $azure->photos()->first();

        $host = rtrim(url('/'), '/'); // asset() uses the host of the request, never another origin
        $this->get('/yachts')->assertSee('src="'.$host.'/storage/'.$cover->variants['card'].'"', false)->assertDontSee('images/yacht-azure.jpg', false);
        $this->get('/yachts/azure')->assertSee($host.'/storage/'.$cover->path, false);

        $azure->photos()->delete(); // a mass delete: no model events
        PublicCatalog::flush();
        $this->get('/yachts')->assertSee('images/placeholder-boat.svg', false);
    }

    public function test_prices_use_the_settings_the_admin_saved(): void
    {
        app(SettingsService::class)->update(['tax_rate_bp' => 1025, 'weekend_multiplier_bp' => 12000], User::factory()->admin()->create(), 'New city tax rate.');

        $this->assertSame([1025, 12000], [$this->catalog()->pricing()['tax_rate_bp'], $this->catalog()->pricing()['weekend_multiplier_bp']]);
        $this->assertArrayNotHasKey('deposit_bp', $this->catalog()->pricing(), 'D22: no deposit');
        $this->get('/pricing')->assertOk()->assertSee('Taxes (10.25%)')->assertSee('Fri – Sun (+20%)');
    }

    public function test_the_pages_survive_an_experience_without_boats_and_a_site_without_boats(): void
    {
        Experience::factory()->create(['slug' => 'whale-watching', 'name' => 'Whale Watching', 'is_active' => true]);
        $this->get('/experiences')->assertOk()->assertSee('Whale Watching')->assertSee('On request');

        Boat::query()->update(['status' => BoatStatus::Paused]); // a mass update: no model events
        PublicCatalog::flush();
        foreach (['/', '/yachts', '/yachts?date='.now()->addWeek()->toDateString(), '/experiences', '/pricing', '/about', '/contact'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/pricing')->assertDontSee('id="estimator"', false);
        $this->get('/book')->assertRedirect('/yachts');
        $this->get('/checkout')->assertRedirect('/yachts');
        $this->get('/booking/confirmation')->assertRedirect('/yachts');
    }

    public function test_the_home_page_drops_tiles_of_hidden_or_trashed_experiences(): void
    {
        // Found in the 3.5 QA: the home page looked these up by slug and crashed when one was missing.
        Experience::query()->where('slug', 'celebration')->first()->delete();
        Experience::query()->where('slug', 'playpen')->update(['is_active' => false]);

        $this->get('/')->assertOk()
            ->assertDontSee('?experience=celebration', false)
            ->assertDontSee('?experience=playpen', false)
            ->assertSee('?experience=river', false);
    }

    public function test_the_checkout_sample_uses_whatever_the_admin_offers(): void
    {
        Experience::query()->where('slug', 'sunset')->first()->delete();
        Addon::query()->where('slug', 'champagne')->first()->delete();

        $this->get('/checkout')->assertOk()->assertDontSee('Sunset Skyline Cruise');
        $this->get('/booking/confirmation')->assertOk();
    }
}
