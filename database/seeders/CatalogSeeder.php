<?php

namespace Database\Seeders;

use App\Enums\AddonUnit;
use App\Enums\AmenityGroup;
use App\Models\Addon;
use App\Models\Amenity;
use App\Models\CancellationPolicy;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\Setting;
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

    /** The wizard's lists, plus each demo boat's highlights. */
    private function amenities(): void
    {
        $lists = [
            AmenityGroup::Feature->value => config('listing.features'),
            AmenityGroup::Extra->value => config('listing.extras'),
            AmenityGroup::Navigation->value => config('listing.navigation'),
            AmenityGroup::Highlight->value => collect(config('demo.yachts'))->pluck('amenities')->flatten()->unique()->values()->all(),
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
                'deposit_bp' => $pricing['deposit_bp'],
                'balance_due_days' => $pricing['balance_due_days'],
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
                'durations' => $experience['durations'],
                'time_slots' => $experience['time_slots'],
                'allowed_weekdays' => $experience['allowed_weekdays'],
                'package_fee_cents' => $experience['package_fee_cents'],
                'is_active' => true,
                'sort' => $sort,
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
