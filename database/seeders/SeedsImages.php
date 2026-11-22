<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Seeders copy a design image (public/images/…) to the public disk, so the admin can later replace or delete
 * the copy without ever touching the design file the static pages use (PLAN 3.2, user decision).
 */
trait SeedsImages
{
    /**
     * @param  string  $designPath  e.g. "images/yacht-azure.jpg" (relative to public/)
     * @param  string  $target  path on the public disk without the extension, e.g. "boats/01J…/1"
     * @return string the stored path on the public disk
     */
    protected function copyDesignImage(string $designPath, string $target): string
    {
        $source = public_path($designPath);
        if (! is_file($source)) {
            throw new RuntimeException("Design image not found: {$designPath}");
        }

        $path = $target.'.'.strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, (string) file_get_contents($source));
        }

        return $path;
    }

    /**
     * What a media row needs to know about a stored image.
     *
     * @return array{mime: string, size_bytes: int, width: int|null, height: int|null}
     */
    protected function imageFacts(string $path): array
    {
        $absolute = Storage::disk('public')->path($path);
        $size = @getimagesize($absolute);

        return [
            'mime' => $size['mime'] ?? 'image/jpeg',
            'size_bytes' => (int) Storage::disk('public')->size($path),
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
        ];
    }
}
