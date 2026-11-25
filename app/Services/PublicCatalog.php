<?php

namespace App\Services;

use App\Enums\AmenityGroup;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\Media;
use App\Support\StartTimes;
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
 * (stats, testimonials, FAQs…) stay demo config until Phases 4.8 and 10. Prices themselves are never added up
 * here: the booking card, the examples and checkout ask QuoteService (via PriceExamples or the quote endpoint).
 * `lengths` are QuoteService::durations(), so the fleet filter and the booking card offer exactly the lengths the
 * price allows (the same with any experience: an experience has no length of its own, 2026-11-25). Each experience
 * carries its departure window (`time_slots`, `departs_label`) and days (`days_label`) for every page.
 */
class PublicCatalog
{
    public const PLACEHOLDER = 'images/placeholder-boat.svg';

    /**
     * Versioned: bump it whenever the payload's shape changes, so a deploy never reads a payload built by the old
     * code (found in the user's QA, 2026-11-26: the booking card showed "departs null" until the cache expired).
     */
    public const CACHE_KEY = 'catalog.public.v2';

    /** @var array{harbors: list<array<string, mixed>>, experiences: list<array<string, mixed>>, addons: list<array<string, mixed>>, yachts: list<array<string, mixed>>}|null */
    private ?array $payload = null;

    /** @var array<string, Collection<int, Fluent>> */
    private array $built = [];

    public function __construct(private SettingsService $settings, private QuoteService $quotes) {}

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

    /**
     * The fleet's "On board" filter (4.4): the 12 amenities ticked on the most published boats, by name.
     *
     * @return Collection<int, Fluent> ulid, name
     */
    public function amenityFilters(): Collection
    {
        return $this->built['amenity_filters'] ??= collect($this->payload()['amenity_filters'] ?? [])->map(fn (array $amenity) => new Fluent($amenity));
    }

    /** @return Collection<int, Fluent> */
    public function addons(): Collection
    {
        return $this->built['addons'] ??= collect($this->payload()['addons'])->map(fn (array $addon) => new Fluent($addon));
    }

    /**
     * Booking and price rules from Admin → Settings, plus the demo promo codes (until 4.8). No deposit (D22).
     *
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        $settings = $this->settings->all();

        return [
            'weekend_multiplier_bp' => (int) $settings['weekend_multiplier_bp'],
            'tax_rate_bp' => (int) $settings['tax_rate_bp'],
            'gratuity_options' => array_map('intval', $settings['gratuity_options']),
            'default_gratuity' => (int) $settings['default_gratuity'],
            'promos' => config('demo.pricing.promos'),
        ];
    }

    /**
     * "Daily", "Wed & Sat", "Mon, Wed & Fri".
     *
     * @param  list<int>|null  $weekdays  0 = Sunday; null = every day
     */
    public static function daysLabel(?array $weekdays): string
    {
        if ($weekdays === null || $weekdays === [] || count($weekdays) === 7) {
            return 'Daily';
        }
        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        return collect($weekdays)->map(fn ($day) => $names[(int) $day] ?? null)->filter()->join(', ', ' & ');
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
        $experiences = Experience::query()->active()->with('extras')->orderBy('name')->get()->map(fn (Experience $experience) => [
            'slug' => $experience->slug,
            'name' => $experience->name,
            'category' => $experience->category,
            'icon' => $experience->icon ?: 'sparkles',
            'image' => $this->path($experience->image_path),
            'tagline' => $experience->tagline,
            'description' => $experience->description,
            'includes' => $experience->includes ?? [],
            'time_slots' => StartTimes::normalize($experience->time_slots ?? []), // its departure window
            'departs_label' => StartTimes::summary($experience->time_slots ?? []), // "5:00 PM – 7:30 PM"
            'days_label' => self::daysLabel($experience->allowed_weekdays),
            // A free experience; its extras are add-ons suggested with it at checkout, optional (2026-11-25).
            'extras' => $experience->extras->where('is_active', true)->map(fn (Addon $addon) => [
                'slug' => $addon->slug,
                'name' => $addon->name,
                'price_cents' => (int) $addon->price_cents,
                'unit' => $addon->unit->value,
            ])->values()->all(),
            'allowed_weekdays' => $experience->allowed_weekdays,
            'is_featured' => $experience->is_featured,
            'season_starts_on' => $experience->season_starts_on?->toDateString(),
            'season_ends_on' => $experience->season_ends_on?->toDateString(),
        ])->values()->all();
        $liveExperiences = array_column($experiences, 'slug');

        $boats = Boat::query()
            ->listed()
            ->with([
                'photos',
                'harbor',
                'amenities',
                'cancellationPolicy',
                'experiences' => fn ($query) => $query->where('experiences.is_active', true)->wherePivot('is_active', true),
            ])
            ->orderBy('sort')
            ->orderBy('title')
            ->get();
        $yachts = $boats->map(fn (Boat $boat) => $this->yachtFrom($boat, $liveExperiences))->values()->all();
        $amenityFilters = $boats->flatMap(fn (Boat $boat) => $boat->amenities)
            ->groupBy('ulid')
            ->sortByDesc(fn ($same) => $same->count())
            ->take(12)
            ->map(fn ($same) => ['ulid' => $same->first()->ulid, 'name' => $same->first()->name])
            ->sortBy('name')
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
            'amenity_filters' => $amenityFilters,
        ];
    }

    /**
     * @param  list<string>  $liveExperiences
     * @return array<string, mixed>
     */
    private function yachtFrom(Boat $boat, array $liveExperiences): array
    {
        $cover = $boat->cover();
        $offered = $boat->experiences->filter(fn (Experience $experience) => in_array($experience->slug, $liveExperiences, true));
        $policy = $boat->cancellationPolicy;

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
            'engine_model' => $boat->engine_model,
            'harbor' => $boat->harbor ? $this->harbor($boat->harbor) : null,
            'hourly_rate_cents' => (int) $boat->hourly_rate_cents,
            'weekend_multiplier_bp' => $boat->weekend_multiplier_bp,
            'min_hours' => (int) $boat->min_hours,
            'durations' => $boat->durations,
            'lengths' => $this->quotes->durations($boat),
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
            // The boat page's "Amenities" list: the boat's own highlights first, then what is ticked (3.7).
            'amenities' => $this->amenityList($boat),
            'experiences' => $offered->pluck('slug')->values()->all(),
            'amenity_ids' => $boat->amenities->pluck('ulid')->values()->all(), // the fleet's "More filters" (4.4)
            'rules' => $boat->rules['not_allowed'] ?? [],
            'policy' => $policy === null ? null : [
                'name' => $policy->name,
                'badge' => $policy->badge,
                'points' => $policy->points ?? [],
                'free_hours' => $policy->freeCancellationHours(),
            ],
            'updated_at' => $boat->updated_at?->toIso8601String(), // the sitemap's lastmod
        ];
    }

    /** @return list<string> highlights, then the ticked amenities in list order, without repeats */
    private function amenityList(Boat $boat): array
    {
        $order = array_flip(array_map(fn (AmenityGroup $group) => $group->value, AmenityGroup::forms()));
        $ticked = $boat->amenities
            ->sortBy(fn ($amenity) => [$order[$amenity->group->value] ?? 99, $amenity->sort, $amenity->name])
            ->pluck('name')
            ->all();
        $seen = [];

        return array_values(array_filter([...($boat->highlights ?? []), ...$ticked], function (string $name) use (&$seen) {
            $key = mb_strtolower(trim($name));
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
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
