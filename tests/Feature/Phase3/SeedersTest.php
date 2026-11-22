<?php

namespace Tests\Feature\Phase3;

use App\Enums\AmenityGroup;
use App\Enums\BoatOwnership;
use App\Enums\BoatStatus;
use App\Enums\BoatType;
use App\Models\Addon;
use App\Models\Amenity;
use App\Models\Boat;
use App\Models\CancellationPolicy;
use App\Models\Captain;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\Media;
use App\Models\Setting;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoFleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesDesignImages;
use Tests\TestCase;

/**
 * Phase 3.2 — the catalog and demo fleet seeders (PLAN 3.2, user decision B): the mockup's data as real,
 * editable records; images stored through MediaService (3.3); re-running never overwrites the admin's edits.
 */
class SeedersTest extends TestCase
{
    use FakesDesignImages, RefreshDatabase;

    private const STORED_IMAGE = '[0-9a-f-]{36}\.webp';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->fakeDesignImages();
    }

    /** Runs the seeder classes directly (`db:seed` would ask for a confirmation in the "production" test). */
    private function runSeeders(string ...$seeders): void
    {
        foreach ($seeders as $seeder) {
            $this->app->call([$this->app->make($seeder), 'run']);
        }
    }

    public function test_the_catalog_matches_the_mockup_data(): void
    {
        $this->runSeeders(CatalogSeeder::class);

        $this->assertSame(['flexible', 'moderate', 'strict'], CancellationPolicy::query()->orderBy('sort')->pluck('slug')->all());
        $this->assertSame(50, CancellationPolicy::query()->where('slug', 'moderate')->first()->refundPercent(100));
        $this->assertSame(count(config('listing.features')), Amenity::query()->where('group', AmenityGroup::Feature)->count());
        $this->assertTrue(Amenity::query()->where('group', AmenityGroup::Highlight)->where('name', 'Flybridge sun lounge')->exists());

        $this->assertSame(config('demo.pricing.tax_rate_bp'), Setting::query()->where('key', 'tax_rate_bp')->value('value'));
        $this->assertSame(config('demo.pricing.gratuity_options'), Setting::query()->where('key', 'gratuity_options')->first()->value);
        $this->assertSame(72, Setting::query()->where('key', 'payout_delay_hours')->value('value'));
        $this->assertSame(config('site.business.email'), Setting::query()->where('key', 'business_email')->value('value'));

        $this->assertSame(collect(config('demo.harbors'))->pluck('slug')->all(), Harbor::query()->orderBy('sort')->pluck('slug')->all());
        $this->assertSame(8, Experience::count());
        $this->assertSame(7, Addon::count());

        $sunset = Experience::query()->where('slug', 'sunset')->first();
        $harbor = Harbor::query()->where('slug', 'dusable')->first();
        $this->assertSame(config('demo.experiences.0.time_slots'), $sunset->time_slots);
        $this->assertMatchesRegularExpression('#^experiences/'.self::STORED_IMAGE.'$#', $sunset->image_path);
        $this->assertMatchesRegularExpression('#^harbors/'.self::STORED_IMAGE.'$#', $harbor->image_path);
        Storage::disk('public')->assertExists([$sunset->image_path, $harbor->image_path]);
        $this->assertSame(count(config('demo.experiences')), count(Storage::disk('public')->files('experiences')), 'one file each: no card / thumb copies for single images');
        $this->assertFileExists(public_path(config('demo.experiences.0.image')), 'the design image is copied, never moved');
    }

    public function test_the_demo_fleet_is_six_published_house_boats_with_photos(): void
    {
        $this->runSeeders(CatalogSeeder::class, DemoFleetSeeder::class);

        $this->assertSame(6, Boat::query()->listed()->count());
        $azure = Boat::query()->where('slug', 'azure')->firstOrFail();
        $yacht = collect(config('demo.yachts'))->firstWhere('slug', 'azure');

        $this->assertSame(BoatStatus::Published, $azure->status);
        $this->assertSame(BoatOwnership::House, $azure->ownership);
        $this->assertSame($yacht['hourly_rate_cents'], $azure->hourly_rate_cents);
        $this->assertSame($yacht['max_guests'], $azure->max_guests);
        $this->assertSame('dusable', $azure->harbor->slug);
        $this->assertSame('moderate', $azure->cancellationPolicy->slug);
        $this->assertEqualsCanonicalizing($yacht['experiences'], $azure->experiences->pluck('slug')->all());
        $this->assertEqualsCanonicalizing($yacht['amenities'], $azure->amenities->pluck('name')->all());
        $this->assertSame(BoatType::Sail, Boat::query()->where('slug', 'windward')->value('type'));

        $photos = $azure->photos;
        $cover = $photos->first();
        $this->assertSame(count(array_unique([$yacht['cover'], ...$yacht['gallery']])), $photos->count());
        $this->assertSame(basename($yacht['cover']), $cover->original_name, 'the cover comes first');
        $this->assertMatchesRegularExpression("#^boats/{$azure->ulid}/photos/".self::STORED_IMAGE.'$#', $cover->path);
        $this->assertSame(['image/webp', 1600], [$cover->mime, $cover->width]);
        $this->assertSame(['card', 'thumb'], array_keys($cover->variants));
        $this->assertSame($yacht['title'], $cover->alt);
        Storage::disk('public')->assertExists($photos->flatMap->paths()->all());

        // Local: bookable (for testing) and the demo captains are there, licenses mapped and encrypted.
        $this->assertSame(6, Boat::query()->bookable()->count());
        $this->assertSame(4, Captain::count());
        $this->assertSame(6, Captain::query()->where('license_type', 'oupv')->value('max_passengers_licensed'));
        $this->assertStringNotContainsString('MML-4471902', (string) DB::table('captains')->value('license_number'));
    }

    public function test_on_the_live_server_the_demo_boats_carry_the_unavailable_tag_and_no_demo_captains(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->runSeeders(CatalogSeeder::class, DemoFleetSeeder::class);

        $this->assertSame(6, Boat::query()->listed()->count(), 'still shown, so the design stays full');
        $this->assertSame(0, Boat::query()->bookable()->count());
        $this->assertSame(DemoFleetSeeder::UNAVAILABLE_REASON, Boat::query()->value('unavailable_reason'));
        $this->assertSame(0, Captain::count(), 'example.com captains must never get assignments');
    }

    public function test_running_the_seeders_again_never_overwrites_or_brings_back_what_the_admin_changed(): void
    {
        $this->runSeeders(CatalogSeeder::class, DemoFleetSeeder::class);

        Setting::query()->where('key', 'tax_rate_bp')->update(['value' => json_encode(900)]);
        Experience::query()->where('slug', 'sunset')->update(['name' => 'Golden Hour Cruise']);
        Experience::query()->where('slug', 'fireworks')->first()->delete();
        Boat::query()->where('slug', 'onyx')->first()->delete();
        Boat::query()->where('slug', 'azure')->first()->forceFill(['booking_enabled' => false])->save();
        $counts = [Amenity::count(), Media::count(), Addon::count()];

        $this->runSeeders(CatalogSeeder::class, DemoFleetSeeder::class);

        $this->assertSame(900, Setting::query()->where('key', 'tax_rate_bp')->value('value'));
        $this->assertSame('Golden Hour Cruise', Experience::query()->where('slug', 'sunset')->value('name'));
        $this->assertSoftDeleted(Experience::withTrashed()->where('slug', 'fireworks')->first());
        $this->assertSame(1, Experience::withTrashed()->where('slug', 'fireworks')->count());
        $this->assertSame(5, Boat::query()->count(), 'a deleted boat stays deleted');
        $this->assertFalse(Boat::query()->where('slug', 'azure')->value('booking_enabled'));
        $this->assertSame($counts, [Amenity::count(), Media::count(), Addon::count()], 'nothing duplicated');
    }
}
