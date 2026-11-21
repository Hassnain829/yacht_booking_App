<?php

namespace App\Support\Demo;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * Phase 1 stand-in for the database (reads config/demo.php).
 *
 * Every record is returned as a Fluent object, so views use the same syntax
 * they will use with Eloquent models: $yacht->title, $yacht->harbor->name.
 * In Phase 3 the controllers switch to Eloquent queries and this class is deleted.
 */
class DemoCatalog
{
    /** @return Collection<int, Fluent> */
    public function harbors(): Collection
    {
        return collect(config('demo.harbors'))->map(fn (array $harbor) => new Fluent($harbor));
    }

    public function harbor(?string $slug): ?Fluent
    {
        return $this->harbors()->firstWhere('slug', $slug);
    }

    /** @return Collection<int, Fluent> */
    public function yachts(): Collection
    {
        $harbors = $this->harbors()->keyBy('slug');

        return collect(config('demo.yachts'))->map(function (array $yacht) use ($harbors) {
            $yacht['harbor'] = $harbors->get($yacht['harbor']);

            return new Fluent($yacht);
        });
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
        return collect(config('demo.experiences'))->map(fn (array $experience) => new Fluent($experience));
    }

    public function experience(string $slug): ?Fluent
    {
        return $this->experiences()->firstWhere('slug', $slug);
    }

    /** @return Collection<int, Fluent> */
    public function addons(): Collection
    {
        return collect(config('demo.addons'))->map(fn (array $addon) => new Fluent($addon));
    }

    /** @return array<string, mixed> */
    public function pricing(): array
    {
        return config('demo.pricing');
    }

    /**
     * Editable site content (stats, testimonials, gallery, faqs…).
     * Lists come back as Collections of Fluent; plain values as-is.
     */
    public function content(string $key): mixed
    {
        $value = config("demo.content.$key");

        if (is_array($value) && array_is_list($value)) {
            return collect($value)->map(fn ($item) => is_array($item) ? new Fluent($item) : $item);
        }

        return is_array($value) ? new Fluent($value) : $value;
    }
}
