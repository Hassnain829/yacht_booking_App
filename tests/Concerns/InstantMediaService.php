<?php

namespace Tests\Concerns;

use App\Services\MediaService;
use App\Support\StoredFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Test-only MediaService for page tests that only need catalog rows (SeedsPublicCatalog): it skips decoding
 * and WebP encoding and writes nothing, so seeding the demo catalog takes milliseconds instead of seconds.
 * The real pipeline keeps its own tests (Phase3/MediaTest, SeedersTest).
 */
class InstantMediaService extends MediaService
{
    public function storeImage(File $file, string $directory, string $disk = 'public', ?int $width = null, bool $variants = true): StoredFile
    {
        $base = trim($directory, '/').'/'.Str::uuid();
        $copies = $variants ? collect(config('media.image.variants'))->keys()->mapWithKeys(fn (string $name) => [$name => "{$base}-{$name}.webp"])->all() : [];

        return new StoredFile($disk, $base.'.webp', $copies, 'image/webp', 1, 1600, 1067);
    }
}
