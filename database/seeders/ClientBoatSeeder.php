<?php

namespace Database\Seeders;

use App\Enums\AmenityGroup;
use App\Enums\BoatOwnership;
use App\Enums\BoatStatus;
use App\Enums\BoatType;
use App\Enums\CaptainSource;
use App\Models\Amenity;
use App\Models\Boat;
use App\Services\PublicCatalog;
use App\Support\StartTimes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The client's own boat (PLAN 3.7; open item 1): a 2006 Cruisers Yachts 280 CXi express cruiser, from the
 * details the client sent on 2026-11-23. It is added as a **draft** house boat, featured on the home page,
 * with everything the client told us: specs, the cabin / galley / bathroom features and the highlights.
 *
 * Still needed from the client before it can be published (the admin's boat page shows them as a checklist):
 * boat name (if it has one), departure harbor, price per hour + captain fee + fuel & cleaning, cancellation
 * policy, the experiences it offers, its own photos, and the guest limit (6 without a Coast Guard COI).
 *
 * Safe to run again and on the live site: `php artisan db:seed --class=ClientBoatSeeder`. It adds the boat
 * only if no boat uses its web address yet, so the admin's later edits are never overwritten.
 */
class ClientBoatSeeder extends Seeder
{
    public const SLUG = 'cruisers-280-cxi';

    /** Ticked features: group => names (created in that list if missing). */
    private const FEATURES = [
        AmenityGroup::Feature->value => ['Air conditioning', 'Generator', 'Bluetooth audio'],
        AmenityGroup::Cabin->value => ['Cabin', 'Forward V-berth', 'Mid-cabin berth', 'Convertible dinette', 'Cabin storage'],
        AmenityGroup::Galley->value => ['Galley', 'Refrigerator', 'Microwave', 'Sink with running water'],
        AmenityGroup::Bathroom->value => ['Private enclosed head', 'Electric flush toilet', 'Separate shower', 'Sink & vanity', 'Freshwater system'],
        AmenityGroup::Deck->value => ['Large swim platform', 'Cockpit mats', 'Raw-water washdown'],
    ];

    public function run(): void
    {
        if (Boat::withTrashed()->where('slug', self::SLUG)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $boat = new Boat([
                'slug' => self::SLUG,
                'title' => 'Cruisers 280 CXi',
                'short' => 'A roomy 31-ft express cruiser with cold A/C, a generator, a full galley and a private head with shower.',
                'description' => 'Step aboard a 2006 Cruisers Yachts 280 CXi — an express cruiser of about 31 feet with a surprising amount of room below deck. '
                    .'Twin Volvo Penta 4.3L engines, overhauled and tuned in the last two years, and an onboard generator keep the air conditioning cold and the fridge running all day. '
                    .'Below you will find a forward sleeping area, a mid-cabin berth, a convertible dinette, a full galley with refrigerator, microwave and solid-surface counters, '
                    .'and a private enclosed head with a separate shower. Up top, the large swim platform makes a Lake Michigan swim easy. '
                    .'A comfortable choice for couples, families and small groups who want shade, a real bathroom and space to relax.',
                'highlights' => [
                    'Twin Volvo Penta 4.3L engines, recently overhauled',
                    'Onboard generator',
                    'Cold air-conditioned cabin',
                    'Forward and mid-cabin sleeping areas',
                    'Full galley with fridge and microwave',
                    'Private head with separate shower',
                    'Large swim platform',
                    'Convertible dinette and plenty of storage',
                ],
                'type' => BoatType::Power,
                'category' => 'Express cruiser',
                'make' => 'Cruisers Yachts',
                'model' => '280 CXi',
                'year' => 2006,
                'length_ft' => 31,
                'max_guests' => 6, // a captained charter without a Coast Guard COI (confirm with the client)
                'staterooms' => 2,
                'beds' => 3,
                'heads' => 1,
                'engine_count' => 2,
                'engine_type' => 'Inboard/Outboard (sterndrive)',
                'engine_model' => 'Volvo Penta 4.3L',
                'uscg_inspected' => false,
                'captain_source' => CaptainSource::Platform,
                'renter_operated_allowed' => false,
                'min_hours' => 2,
                'crew_count' => 1,
                'advance_notice_minutes' => 1440,
                'buffer_minutes' => 60,
                'is_featured' => true,
                'sort' => 0, // first on the home page and the fleet page
                'reviews_count' => 0,
                'booking_enabled' => true,
                'extensions_enabled' => true,
            ]);
            $boat->forceFill(['ownership' => BoatOwnership::House, 'status' => BoatStatus::Draft])->save();

            $ids = [];
            foreach (self::FEATURES as $group => $names) {
                foreach ($names as $name) {
                    $ids[] = Amenity::query()->firstOrCreate(['group' => $group, 'name' => $name], ['sort' => 100])->getKey();
                }
            }
            $boat->amenities()->sync($ids);

            foreach (range(0, 6) as $day) {
                // Departures every half hour 8 AM – 9 PM: a 2-hour trip is back by 11 PM (4.2 start times).
                $boat->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::between('08:00', '21:00')]);
            }
        });

        PublicCatalog::flush();
    }
}
