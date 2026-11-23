<?php

namespace Database\Seeders;

use App\Enums\BoatOwnership;
use App\Enums\BoatStatus;
use App\Enums\BoatType;
use App\Enums\CaptainSource;
use App\Enums\LicenseType;
use App\Models\Boat;
use App\Models\CancellationPolicy;
use App\Models\Captain;
use App\Models\Experience;
use App\Models\Harbor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The mockup's 6 boats as real, editable records (PLAN 3.2, user decision B), with their photos stored
 * through MediaService (WebP + card / thumb sizes, about a minute for the 27 photos).
 * Run CatalogSeeder first (harbors, policies, experiences, amenities).
 *
 * - Live server (`php artisan db:seed --class=DemoFleetSeeder --force`): every demo boat carries the
 *   Unavailable tag; the admin can edit, hide or delete them. Demo captains are NOT created there
 *   (their example.com emails must never get trip assignments).
 * - Local: the boats stay bookable (for testing) and the demo captains are added.
 *
 * Only adds what is missing (by slug / email, soft-deleted rows included): never overwrites the admin's edits.
 */
class DemoFleetSeeder extends Seeder
{
    use SeedsImages;

    public const UNAVAILABLE_REASON = 'Showcase boat — not taking bookings yet.';

    public function run(): void
    {
        $this->boats();

        if (! app()->isProduction()) {
            $this->captains();
        }
    }

    private function boats(): void
    {
        $policy = CancellationPolicy::query()->where('slug', 'moderate')->first();
        $live = app()->isProduction();

        foreach (config('demo.yachts') as $sort => $yacht) {
            if (Boat::withTrashed()->where('slug', $yacht['slug'])->exists()) {
                continue;
            }

            // All or nothing: a boat whose photos failed (e.g. no GD) must not stay behind, or re-running would skip it.
            DB::transaction(fn () => $this->createBoat($yacht, $sort, $policy, $live));
        }
    }

    /** @param  array<string, mixed>  $yacht  one entry of config('demo.yachts') */
    private function createBoat(array $yacht, int $sort, ?CancellationPolicy $policy, bool $live): void
    {
        $boat = new Boat([
            'slug' => $yacht['slug'],
            'title' => $yacht['title'],
            'short' => $yacht['short'],
            'description' => $yacht['description'],
            'type' => str_contains(strtolower($yacht['category']), 'sail') ? BoatType::Sail : BoatType::Power,
            'category' => $yacht['category'],
            'year' => $yacht['year'],
            'length_ft' => $yacht['length_ft'],
            'max_guests' => $yacht['max_guests'],
            'staterooms' => $yacht['staterooms'],
            'heads' => $yacht['heads'],
            'harbor_id' => Harbor::query()->where('slug', $yacht['harbor'])->value('id'),
            'country' => 'US',
            'state' => 'IL',
            'city' => 'Chicago',
            'cancellation_policy_id' => $policy?->id,
            'captain_source' => CaptainSource::Platform,
            'hourly_rate_cents' => $yacht['hourly_rate_cents'],
            'min_hours' => $yacht['min_hours'],
            'cleaning_fee_cents' => $yacht['cleaning_fee_cents'],
            'captain_rate_cents' => $yacht['captain_rate_cents'],
            'crew_count' => $yacht['crew_count'],
            'badge' => $yacht['badge'],
            'is_featured' => $yacht['is_featured'],
            'sort' => $sort,
            'rating_avg' => $yacht['rating_avg'],
            'reviews_count' => $yacht['reviews_count'],
            'highlights' => $yacht['amenities'], // the boat's own selling points (3.7)
            'booking_enabled' => $live ? false : $yacht['booking_enabled'],
            'unavailable_reason' => $live || ! $yacht['booking_enabled'] ? self::UNAVAILABLE_REASON : null,
        ]);
        // System fields: a published house boat (never fillable).
        $boat->forceFill(['ownership' => BoatOwnership::House, 'status' => BoatStatus::Published, 'published_at' => now()])->save();

        $boat->experiences()->sync(Experience::query()->whereIn('slug', $yacht['experiences'])->pluck('id'));

        // Cover first: MediaService puts each new photo last.
        foreach (array_unique([$yacht['cover'], ...$yacht['gallery']]) as $image) {
            $this->media()->addImage($boat, 'photos', $this->designImage($image), alt: $yacht['title']);
        }
    }

    /** Local only: the admin demo's captains, with their licenses mapped to our three types. */
    private function captains(): void
    {
        $types = config('demo_admin.license_types');

        foreach (config('demo_admin.captains') as $sort => $captain) {
            if (Captain::withTrashed()->where('email', $captain['email'])->exists()) {
                continue;
            }
            Captain::query()->create([
                'name' => $captain['name'],
                'slug' => Str::slug($captain['name']),
                'email' => $captain['email'],
                'phone' => $captain['phone'],
                'license_type' => str_starts_with($captain['license_type'], 'master') ? LicenseType::Master : LicenseType::from($captain['license_type']),
                'license_number' => $captain['license_number'],
                'license_expires_on' => now()->addDays($captain['license_expires_days'])->toDateString(),
                'max_passengers_licensed' => $types[$captain['license_type']]['max_passengers'],
                'pay_rate_cents' => $captain['rate_cents'],
                'is_active' => $captain['active'],
                'is_public' => true,
                'sort' => $sort,
            ]);
        }
    }
}
