<?php

namespace Database\Seeders;

use App\Enums\AddonUnit;
use App\Enums\AmenityGroup;
use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Models\Addon;
use App\Models\Amenity;
use App\Models\CancellationPolicy;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Support\StartTimes;
use Illuminate\Database\Seeder;

/**
 * The catalog the site starts with (PLAN 3.2): cancellation policies, amenity lists, settings, harbors,
 * experiences and add-ons — taken from the mockup data (config/demo.php, config/listing.php, …).
 * Safe on the live server: `php artisan db:seed --class=CatalogSeeder --force`.
 *
 * It only ADDS what is missing (matched by slug / key); running it again never overwrites the admin's
 * changes and never brings back something the admin deleted (soft-deleted rows count as existing).
 * Images are stored through MediaService (SeedsImages) as WebP copies: the design files in public/images
 * are never touched. Needs the GD extension (enable `extension=gd` in php.ini).
 */
class CatalogSeeder extends Seeder
{
    use SeedsImages;

    public function run(): void
    {
        $this->policies();
        $this->amenities();
        $this->settings();
        $this->harbors();
        $this->experiences();
        $this->addons();
        $this->promoCodes();
    }

    private function policies(): void
    {
        $rules = config('listing.cancellation_rules');

        foreach (config('listing.cancellation') as $sort => $policy) {
            CancellationPolicy::query()->firstOrCreate(['slug' => $policy['id']], [
                'name' => $policy['name'],
                'badge' => $policy['badge'],
                'points' => $policy['points'],
                'rules' => array_map(fn (array $r) => ['min_hours_before' => $r[0], 'refund_pct' => $r[1]], $rules[$policy['id']]),
                'is_active' => true,
                'sort' => $sort,
            ]);
        }
    }

    /** The wizard's lists, plus the cruiser / yacht groups added in 3.7. */
    private function amenities(): void
    {
        $lists = [
            AmenityGroup::Feature->value => config('listing.features'),
            AmenityGroup::Extra->value => config('listing.extras'),
            AmenityGroup::Navigation->value => config('listing.navigation'),
            // Added for cruisers and yachts (3.7). A boat's own selling points are its `highlights` lines.
            AmenityGroup::Cabin->value => ['Cabin', 'Forward V-berth', 'Mid-cabin berth', 'Convertible dinette', 'Bedding & linens', 'Cabin storage'],
            AmenityGroup::Galley->value => ['Galley', 'Microwave', 'Stove / cooktop', 'Coffee maker', 'Sink with running water', 'Ice maker'],
            AmenityGroup::Bathroom->value => ['Private enclosed head', 'Electric flush toilet', 'Separate shower', 'Sink & vanity', 'Freshwater system'],
            AmenityGroup::Deck->value => ['Large swim platform', 'Bimini top / shade', 'Sun pad', 'Cockpit table', 'Cockpit mats', 'Raw-water washdown'],
        ];

        foreach ($lists as $group => $names) {
            foreach (array_values($names) as $sort => $name) {
                Amenity::query()->firstOrCreate(['group' => $group, 'name' => $name], ['sort' => $sort]);
            }
        }
    }

    /** Defaults the admin can change in Settings (PLAN §6.7). Existing keys are left alone. */
    private function settings(): void
    {
        $pricing = config('demo.pricing');
        $money = config('demo_owner.money');
        $business = config('site.business');

        $defaults = [
            'booking' => [
                'tax_rate_bp' => $pricing['tax_rate_bp'],
                'weekend_multiplier_bp' => $pricing['weekend_multiplier_bp'],
                'gratuity_options' => $pricing['gratuity_options'],
                'default_gratuity' => $pricing['default_gratuity'],
                'hold_minutes' => 15,
            ],
            'owners' => [
                'commission_bp' => $money['commission_bp'],
                'payout_delay_hours' => $money['payout_delay_hours'],
                'complaint_window_hours' => $money['complaint_window_hours'],
                'min_withdrawal_cents' => $money['min_withdrawal_cents'],
            ],
            'business' => [
                'business_name' => $business['name'],
                'business_email' => $business['email'],
                'business_phone' => $business['phone'],
                'business_office' => $business['office'],
                'business_hours' => $business['hours'],
                'business_tagline' => $business['tagline'],
                'social_links' => config('site.social'),
            ],
            // Phase 8 rules (PLAN R12, R15): placeholders the admin can change in Settings.
            'captains' => SettingsService::DEFAULTS['captains'],
            'extensions' => SettingsService::DEFAULTS['extensions'],
        ];

        foreach ($defaults as $group => $values) {
            foreach ($values as $key => $value) {
                Setting::query()->firstOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
            }
        }
    }

    private function harbors(): void
    {
        foreach (config('demo.harbors') as $sort => $harbor) {
            if (Harbor::query()->where('slug', $harbor['slug'])->exists()) {
                continue;
            }
            Harbor::query()->create([
                'slug' => $harbor['slug'],
                'name' => $harbor['name'],
                'area' => $harbor['area'],
                'notes' => $harbor['notes'],
                'map_query' => $harbor['map_query'],
                'image_path' => $this->media()->storeImage($this->designImage($harbor['image']), 'harbors', variants: false)->path,
                'is_active' => true,
                'sort' => $sort,
            ]);
        }
    }

    private function experiences(): void
    {
        foreach (config('demo.experiences') as $sort => $experience) {
            if (Experience::withTrashed()->where('slug', $experience['slug'])->exists()) {
                continue;
            }
            Experience::query()->create([
                'slug' => $experience['slug'],
                'name' => $experience['name'],
                'category' => $experience['category'],
                'icon' => $experience['icon'],
                'image_path' => $this->media()->storeImage($this->designImage($experience['image']), 'experiences', variants: false)->path,
                'tagline' => $experience['tagline'],
                'description' => $experience['description'],
                'includes' => $experience['includes'],
                'time_slots' => StartTimes::between(...$experience['departs']), // its departure window
                'allowed_weekdays' => $experience['allowed_weekdays'],
                'is_active' => true,
                'sort' => $sort,
            ]);
        }
    }

    /** The mockup's two codes (4.5): WELCOME10 (10% off the charter) and SUNSET50 ($50 off the booking). */
    private function promoCodes(): void
    {
        foreach (config('demo.pricing.promos') as $promo) {
            if (PromoCode::withTrashed()->where('code', $promo['code'])->exists()) {
                continue;
            }
            PromoCode::query()->create([
                'code' => $promo['code'],
                'type' => PromoType::from($promo['type']),
                'value' => $promo['type'] === 'percent' ? $promo['value'] * 100 : $promo['value'], // percent → basis points
                'applies_to' => PromoAppliesTo::from($promo['applies_to']),
                'is_active' => true,
            ]);
        }
    }

    private function addons(): void
    {
        foreach (config('demo.addons') as $sort => $addon) {
            Addon::query()->firstOrCreate(['slug' => $addon['slug']], [
                'name' => $addon['name'],
                'description' => $addon['description'],
                'icon' => $addon['icon'],
                'price_cents' => $addon['price_cents'],
                'unit' => AddonUnit::from($addon['unit']),
                'is_active' => true,
                'sort' => $sort,
            ]);
        }
    }
}
