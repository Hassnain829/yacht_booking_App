<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * One stored file (PLAN §9). Only the path relative to `disk` is kept ("public" for photos, "local" for
 * private documents), plus the paths of its smaller copies in `variants`. Created and deleted only by
 * MediaService; the owning record and the uploader are set by it.
 */
#[Fillable(['collection', 'disk', 'path', 'original_name', 'mime', 'size_bytes', 'width', 'height', 'variants', 'alt', 'sort'])]
#[Hidden(['id', 'model_type', 'model_id', 'disk', 'uploaded_by'])]
class Media extends Model
{
    use HasUuids;

    protected $table = 'media';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return MorphTo<Model, $this> */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPublic(): bool
    {
        return $this->disk === 'public';
    }

    /**
     * The public URL of the file or one of its copies ("card", "thumb"); a missing copy (the image was already
     * small) falls back to the main file. Private files have no URL: they go through FileController.
     */
    public function url(?string $variant = null): ?string
    {
        if (! $this->isPublic()) {
            return null;
        }

        $path = $variant === null ? $this->path : ($this->variants[$variant] ?? $this->path);

        return Storage::disk('public')->url($path);
    }

    /** For `<img srcset>`: every stored size with its width, e.g. "…-thumb.webp 400w, …-card.webp 800w, ….webp 1600w". */
    public function srcset(): ?string
    {
        if (! $this->isPublic() || $this->width === null) {
            return null;
        }

        $widths = config('media.image.variants');
        $sources = collect($this->variants ?? [])
            ->only(array_keys($widths))
            ->map(fn (string $path, string $name) => Storage::disk('public')->url($path).' '.$widths[$name].'w')
            ->push($this->url().' '.$this->width.'w');

        return $sources->implode(', ');
    }

    /** @return list<string> the main file and its copies, relative to the disk */
    public function paths(): array
    {
        return [$this->path, ...array_values($this->variants ?? [])];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['variants' => 'array'];
    }
}
