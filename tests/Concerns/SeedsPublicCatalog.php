<?php

namespace Tests\Concerns;

use App\Services\MediaService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoFleetSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * The public pages read the database since 3.5 (PublicCatalog). Tests of those pages seed the catalog the site
 * starts with — harbors, experiences, add-ons, settings and the 6 demo boats with their photo rows — on a fake
 * public disk. Image encoding is skipped (InstantMediaService): these tests check pages, not files.
 * Use with RefreshDatabase.
 */
trait SeedsPublicCatalog
{
    protected function seedPublicCatalog(): void
    {
        Storage::fake('public');
        $this->app->bind(MediaService::class, InstantMediaService::class);
        $this->seed([CatalogSeeder::class, DemoFleetSeeder::class]);
        $this->app->offsetUnset(MediaService::class); // the rest of the test uses the real one
    }
}
