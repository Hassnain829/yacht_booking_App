<?php

namespace Tests\Feature\Phase3;

use App\Enums\AmenityGroup;
use App\Enums\BoatStatus;
use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\CancellationPolicy;
use App\Models\Harbor;
use App\Models\User;
use App\Services\PublicCatalog;
use Database\Seeders\ClientBoatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 3.7 — flexible boat listings: the boat's own highlights, the engine, the cruiser / yacht feature lists
 * with "add a missing item" on the boat form, the listing checklist + the Coast Guard 6-passenger warning, and
 * the client's Cruisers 280 CXi added by its seeder.
 */
class BoatListingTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPublicCatalog();
        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, mixed> the boat form for The Azure, as it is, with overrides */
    private function azureForm(array $overrides = []): array
    {
        $hours = [];
        foreach (range(0, 6) as $day) {
            $hours[$day] = ['open' => '1', 'opens' => '09:00', 'closes' => '22:00'];
        }

        return array_replace_recursive([
            'title' => 'The Azure', 'slug' => 'azure', 'short' => 'A sleek flybridge yacht.', 'description' => 'Flybridge lounge.', 'badge' => '',
            'highlights_text' => '', 'type' => 'power', 'category' => 'Motor Yacht', 'length_ft' => '62', 'max_guests' => '13',
            'uscg_inspected' => '1', 'engine_model' => '', 'harbor' => 'dusable', 'hourly_rate' => '450', 'weekend_markup' => '', 'min_hours' => '3',
            'cleaning_fee' => '150', 'captain_rate' => '85', 'crew_count' => '1', 'cancellation_policy' => 'moderate', 'advance_notice_minutes' => '0',
            'buffer_minutes' => '0', 'allow_multiple_bookings' => '0', 'captain_source' => 'platform', 'renter_operated_allowed' => '0',
            'fishing_charter' => '0', 'is_featured' => '1', 'sort' => '0', 'reviews_count' => '312', 'booking_enabled' => '1', 'extensions_enabled' => '1',
            'hours' => $hours,
        ], $overrides);
    }

    public function test_the_boat_form_saves_highlights_the_engine_and_new_items_in_any_list(): void
    {
        $azure = Boat::query()->where('slug', 'azure')->sole();
        Amenity::query()->create(['group' => 'feature', 'name' => 'Generator', 'sort' => 1]);

        $this->actingAs($this->admin)->put("/admin/boats/{$azure->ulid}", $this->azureForm([
            'highlights_text' => "Onboard generator\n\n  Cold A/C cabin  \nPrivate head with shower",
            'engine_model' => 'Twin Volvo Penta 4.3L',
            'new_amenities' => ['feature' => 'generator, Shore power', 'galley' => 'Espresso machine', 'bathroom' => ''],
        ]))->assertRedirect("/admin/boats/{$azure->ulid}/edit");

        $azure->refresh();
        $this->assertSame(['Onboard generator', 'Cold A/C cabin', 'Private head with shower'], $azure->highlights);
        $this->assertSame('Twin Volvo Penta 4.3L', $azure->engine_model);
        $this->assertSame(1, Amenity::query()->where('group', 'feature')->where('name', 'like', 'generator')->count(), '"generator" reuses "Generator"');
        $this->assertEqualsCanonicalizing(['Generator', 'Shore power', 'Espresso machine'], $azure->amenities()->pluck('name')->all());
        $this->assertSame(AmenityGroup::Galley, Amenity::query()->where('name', 'Espresso machine')->sole()->group);
        $this->assertEqualsCanonicalizing(['Espresso machine', 'Generator', 'Shore power'], AuditLog::query()->where('action', 'boat.updated')->latest('id')->first()->after['amenities']);

        // The boat page lists the highlights first, then what is ticked — no repeats.
        $html = $this->get('/yachts/azure')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, '>Shore power<'), strpos($html, 'Onboard generator'));
        $this->assertSame(1, substr_count($html, 'Espresso machine'));
        $this->assertStringNotContainsString('Flybridge sun lounge', $html); // the old highlights were replaced
    }

    public function test_bad_highlights_and_new_items_are_refused(): void
    {
        $azure = Boat::query()->where('slug', 'azure')->sole();
        $this->actingAs($this->admin);

        $this->putJson("/admin/boats/{$azure->ulid}", $this->azureForm([
            'highlights_text' => implode("\n", array_map(fn ($i) => "Line {$i}", range(1, 13))),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['highlights']);
        $this->putJson("/admin/boats/{$azure->ulid}", $this->azureForm([
            'highlights_text' => "<b>Bold</b>\n".str_repeat('x', 81),
            'new_amenities' => ['spaceship' => 'Warp drive', 'galley' => str_repeat('y', 81).', '.implode(', ', range(1, 10))],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['highlights.0', 'highlights.1', 'new_amenities', 'new_amenity_names.galley', 'new_amenity_names.galley.0']);
        $this->postJson('/admin/amenities', ['name' => 'Old style', 'group' => 'highlight', 'icon' => '', 'sort' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors(['group']);

        $this->assertSame(0, Amenity::query()->where('name', 'Warp drive')->count());
    }

    public function test_the_checklist_shows_what_is_missing_and_the_coast_guard_warning(): void
    {
        $this->seed(ClientBoatSeeder::class);
        $boat = Boat::query()->where('slug', ClientBoatSeeder::SLUG)->sole();
        $this->actingAs($this->admin);

        $this->get("/admin/boats/{$boat->ulid}/edit")->assertOk()
            ->assertSee('Listing checklist')
            ->assertSee('The rate guests pay per hour.')
            ->assertSee('Where guests board.')
            ->assertDontSee('no Coast Guard inspection (COI) is ticked');

        $boat->forceFill(['max_guests' => 10])->save();
        $this->get("/admin/boats/{$boat->ulid}/edit")->assertSee('Up to 10 guests, but no Coast Guard inspection (COI) is ticked.');
        $this->get('/admin/boats/create')->assertOk()->assertSee('How to add a boat')->assertSee('Features &amp; amenities', false);
    }

    public function test_the_clients_boat_is_added_as_a_featured_draft_and_shows_first_once_published(): void
    {
        $this->seed(ClientBoatSeeder::class);
        $this->seed(ClientBoatSeeder::class); // safe to run again

        $boat = Boat::query()->where('slug', ClientBoatSeeder::SLUG)->sole();
        $this->assertSame([BoatStatus::Draft, true, 0, 6, 'Volvo Penta 4.3L', 2006], [$boat->status, $boat->is_featured, $boat->sort, $boat->max_guests, $boat->engine_model, $boat->year]);
        $this->assertContains('Onboard generator', $boat->highlights);
        $this->assertEqualsCanonicalizing(['Generator', 'Microwave', 'Separate shower', 'Large swim platform'], $boat->amenities()->whereIn('name', ['Generator', 'Microwave', 'Separate shower', 'Large swim platform'])->pluck('name')->all());
        $this->assertSame(7, $boat->availabilityRules()->count());
        $this->get('/yachts/'.ClientBoatSeeder::SLUG)->assertNotFound(); // a draft is not on the site

        // The client fills in the rest in the admin, then publishes.
        $boat->forceFill([
            'hourly_rate_cents' => 30000, 'captain_rate_cents' => 7500, 'cleaning_fee_cents' => 10000,
            'harbor_id' => Harbor::query()->where('slug', 'dusable')->value('id'),
            'cancellation_policy_id' => CancellationPolicy::query()->where('slug', 'moderate')->value('id'),
        ])->save();
        $this->actingAs($this->admin)->post("/admin/boats/{$boat->ulid}/status", ['action' => 'publish'])->assertRedirect();

        $this->get('/yachts/'.ClientBoatSeeder::SLUG)->assertOk()->assertSee('Cruisers 280 CXi')->assertSee('Private head with separate shower')
            ->assertSee('New on Chicago Yacht Booking')->assertDontSee('0.0'); // no reviews yet: never "0.0"
        $home = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($home, 'The Azure'), strpos($home, 'Cruisers 280 CXi'), 'featured and first on the home page');
        $this->assertSame(ClientBoatSeeder::SLUG, app(PublicCatalog::class)->yachts()->first()->slug);
    }
}
