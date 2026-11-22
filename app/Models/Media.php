<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One stored file (PLAN §9). Only the path relative to `disk` is kept ("public" for photos, "local" for
 * private documents). Created only by MediaService (Phase 3.3); the owning record and uploader are set by it.
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['variants' => 'array'];
    }
}
