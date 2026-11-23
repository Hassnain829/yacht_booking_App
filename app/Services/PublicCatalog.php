<?php

namespace App\Services;

use App\Enums\AmenityGroup;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * What the public site shows (PLAN 3.5–3.6): the database catalog, in the same shape the Phase 1 pages were
 * built on (App\Support\Demo\DemoCatalog), so the views and the booking JS keep working unchanged.
 *
 * - Only what the admin made public: published boats (with the Unavailable tag from `booking_enabled`), shown
 *   experiences / add-ons / harbors. Anything in the Trash is left out by its SoftDeletes scope.
 * - Images are paths for `asset()` ("storage/boats/…/x.webp"), so they load from the page's own host (CSP).
 *   A record without a photo gets a neutral placeholder.
 * - A boat offers an experience only while that experience is shown, not in the Trash, and on for that boat.
 *
 * **Cache (3.6):** the whole catalog is one cache entry of plain arrays (CACHE_KEY), built on the first visit
 * and kept for a day at most. Every change the admin makes clears it after its transaction commits:
 * model events on the catalog models (PublicCatalogObserver), plus flushAfterCommit() where data changes
 * without events (pivot syncs, photo order). Within one request the Fluent objects are built once (scoped
 * singleton, forgotten after every request).
 *
 * Prices and rules come from Settings (cached on their own); promo codes and the editable page content
 * (stats, testimonials, FAQs…) stay demo config until Phases 4.7 and 10.
 */
class PublicCatalog
{
    public const PLACEHOLDER = 'images/placeholder-boat.svg';

    public const CACHE_KEY = 'catalog.public';

    /** @var array{harbors: list<array<string, mixed>>, experiences: list<array<string, mixed>>, addons: list<array<string, mixed>>, yachts: list<array<string, mixed>>}|null */
    private ?array $payload = null;

    /** @var array<string, Collection<int, Fluent>> */
    private array $built = [];

    public function __construct(private SettingsService $settings) {}

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Clears the cache once the surrounding transaction commits (right away when there is none). */
    public static function flushAfterCommit(): void
    {
        DB::afterCommit(fn () => self::flush());
    }

    /** @return Collection<int, Fluent> */
    public function harbors(): Collection
    {
        return $this->built['harbors'] ??= collect($this->payload()['harbors'])->map(fn (array $harbor) => new Fluent($harbor));
    }

    public function harborBySlug(?string $slug): ?Fluent
    {
        return $this->harbors()->firstWhere('slug', $slug);
    }

    /** @return Collection<int, Fluent> */
    public function yachts(): Collection
    {
        return $this->built['yachts'] ??= collect($this->payload()['yachts'])
            ->map(fn (array $yacht) => new Fluent(['harbor' => $yacht['harbor'] === null ? null : new Fluent($yacht['harbor'])] + $yacht));
    }

    public function yacht(string $slug): ?Fluent
    {
        return $this->yachts()->firstWhere('slug', $slug);
    }

    /** @return Collection<int, Fluent> */
    public function featuredYachts(): Collection
    {
        return $this->yachts()->where('is_featured', true)->values();
    }

    /** @return Collection<int, Fluent> */
    public function experiences(): Collection
    {
        return $this->built['experiences'] ??= collect($this->payload()['experiences'])->map(fn (array $experience) => new Fluent($experience));
    }

    public function experience(string $slug): ?Fluent
    {
        return $this->experiences()->firstWhere('slug', $slug);
    }

    /** @return Collection<int, Fluent> */
    public function addons(): Collection
    {
        return $this->built['addons'] ??= collect($this->payload()['addons'])->map(fn (array $addon) => new Fluent($addon));
    }

    /**
     * Booking and price rules from Admin → Settings, plus the demo promo codes (until 4.7).
     *
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        $settings = $this->settings->all();

        return [
            'weekend_multiplier_bp' => (int) $settings['weekend_multiplier_bp'],
            'tax_rate_bp' => (int) $settings['tax_rate_bp'],
            'deposit_bp' => (int) $settings['deposit_bp'],
            'balance_due_days' => (int) $settings['balance_due_days'],
            'gratuity_options' => array_map('intval', $settings['gratuity_options']),
            'default_gratuity' => (int) $settings['default_gratuity'],
            'promos' => config('demo.pricing.promos'),
        ];
    }

    /** Editable page content (stats, testimonials, gallery, FAQs…) — config until the CMS (Phase 10). */
    public function content(string $key): mixed
    {
        $value = config("demo.content.$key");

        if (is_array($value) && array_is_list($value)) {
            return collect($value)->map(fn ($item) => is_array($item) ? new Fluent($item) : $item);
        }

        return is_array($value) ? new Fluent($value) : $value;
    }

    /**
     * The cached catalog: plain arrays only (safe for any cache store).
     *
     * @return array{harbors: list<array<string, mixed>>, experiences: list<array<string, mixed>>, addons: list<array<string, mixed>>, yachts: list<array<string, mixed>>}
     */
    private function payload(): array
    {
        return $this->payload ??= Cache::remember(self::CACHE_KEY, now()->addDay(), fn () => $this->build());
    }

    /**
     * @return array{harbors: list<array<string, mixed>>, experiences: list<array<string, mixed>>, addons: list<array<string, mixed>>, yachts: list<array<string, mixed>>}
     */
    private function build(): array
    {
        $experiences = Experience::query()->active()->orderBy('name')->get()->map(fn (Experience $experience) => [
            'slug' => $experience->slug,
            'name' => $experience->name,
            'category' => $experience->category,
            'icon' => $experience->icon ?: 'sparkles',
            'image' => $this->path($experience->image_path),
            'tagline' => $experience->tagline,
            'description' => $experience->description,
            'includes' => $experience->includes ?? [],
            'durations' => array_map('intval', $experience->durations ?? []),
            'time_slots' => $experience->time_slots ?? [],
            'package_fee_cents' => (int) $experience->package_fee_cents,
            'allowed_weekdays' => $experience->allowed_weekdays,
            'is_featured' => $experience->is_featured,
            'season_starts_on' => $experience->season_starts_on?->toDateString(),
            'season_ends_on' => $experience->season_ends_on?->toDateString(),
        ])->values()->all();
        $liveExperiences = array_column($experiences, 'slug');

        $yachts = Boat::query()
            ->listed()
            ->with([
                'photos',
                'harbor',
                'amenities' => fn ($query) => $query->where('group', AmenityGroup::Highlight),
                'experiences' => fn ($query) => $query->where('experiences.is_active', true)->wherePivot('is_active', true),
            ])
            ->orderBy('sort')
            ->orderBy('title')
            ->get()
            ->map(fn (Boat $boat) => $this->yachtFrom($boat, $liveExperiences))
            ->values()
            ->all();

        return [
            'harbors' => Harbor::query()->active()->orderBy('name')->get()->map(fn (Harbor $harbor) => $this->harbor($harbor))->values()->all(),
            'experiences' => $experiences,
            'addons' => Addon::query()->active()->get()->map(fn (Addon $addon) => [
                'slug' => $addon->slug,
                'name' => $addon->name,
                'description' => $addon->description,
                'price_cents' => (int) $addon->price_cents,
                'unit' => $addon->unit->value,
                'icon' => $addon->icon ?: 'gift',
                'max_quantity' => $addon->max_quantity,
            ])->values()->all(),
            'yachts' => $yachts,
        ];
    }

    /**
     * @param  list<string>  $liveExperiences
     * @return array<string, mixed>
     */
    private function yachtFrom(Boat $boat, array $liveExperiences): array
    {
        $cover = $boat->cover();

        return [
            'slug' => $boat->slug,
            'title' => $boat->title,
            'category' => $boat->category,
            'type' => $boat->type?->value,
            'length_ft' => $boat->length_ft,
            'max_guests' => $boat->max_guests,
            'staterooms' => $boat->staterooms,
            'heads' => $boat->heads,
            'year' => $boat->year,
            'harbor' => $boat->harbor ? $this->harbor($boat->harbor) : null,
            'hourly_rate_cents' => (int) $boat->hourly_rate_cents,
            'weekend_multiplier_bp' => $boat->weekend_multiplier_bp,
            'min_hours' => (int) $boat->min_hours,
            'durations' => $boat->durations,
            'captain_rate_cents' => (int) $boat->captain_rate_cents,
            'crew_count' => (int) $boat->crew_count,
            'cleaning_fee_cents' => (int) $boat->cleaning_fee_cents,
            'rating_avg' => $boat->rating_avg === null ? null : (float) $boat->rating_avg,
            'reviews_count' => (int) $boat->reviews_count,
            'badge' => $boat->badge,
            'is_featured' => $boat->is_featured,
            'booking_enabled' => $boat->booking_enabled,
            'unavailable_reason' => $boat->unavailable_reason,
            'extensions_enabled' => $boat->extensions_enabled,
            'cover' => $this->path($cover?->path),
            'cover_card' => $this->path($cover?->variants['card'] ?? $cover?->path),
            'gallery' => $boat->photos->isEmpty() ? [self::PLACEHOLDER] : $boat->photos->map(fn (Media $photo) => $this->path($photo->path))->values()->all(),
            'short' => $boat->short,
            'description' => $boat->description,
            'amenities' => $boat->amenities->sortBy('sort')->pluck('name')->values()->all(),
            'experiences' => array_values(array_intersect($boat->experiences->pluck('slug')->all(), $liveExperiences)),
            'rules' => $boat->rules['not_allowed'] ?? [],
            'updated_at' => $boat->updated_at?->toIso8601String(), // the sitemap's lastmod
        ];
    }

    /** @return array<string, mixed> */
    private function harbor(Harbor $harbor): array
    {
        return [
            'slug' => $harbor->slug,
            'name' => $harbor->name,
            'area' => $harbor->area,
            'notes' => $harbor->notes,
            'address' => $harbor->address,
            'map_query' => $harbor->map_query ?: $harbor->name.' Chicago IL',
            'image' => $this->path($harbor->image_path),
        ];
    }

    /** A public-disk path → "storage/…" for asset(); none → the placeholder. */
    private function path(?string $path): string
    {
        return blank($path) ? self::PLACEHOLDER : 'storage/'.ltrim($path, '/');
    }
}
