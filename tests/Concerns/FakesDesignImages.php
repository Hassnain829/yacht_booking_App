<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The seeders store every design image (public/images) through MediaService. Encoding the real 1600 px
 * photos as WebP takes GD about a minute, so tests point public_path() at a temporary folder with tiny
 * stand-ins of the same names: 1700 × 24 px, wide enough that every size (1600 / 800 / 400) is still made.
 */
trait FakesDesignImages
{
    protected function fakeDesignImages(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cyb-design-'.Str::random(10);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root)); // first, so a failure below still cleans up
        File::ensureDirectoryExists($root.'/images');

        foreach (File::files(public_path('images')) as $design) {
            $image = imagecreatetruecolor(1700, 24);
            imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 40, 80));
            $target = $root.'/images/'.$design->getFilename();

            match (strtolower($design->getExtension())) {
                'png' => imagepng($image, $target),
                'webp' => imagewebp($image, $target),
                default => imagejpeg($image, $target),
            };
        }

        $this->app->usePublicPath($root);
    }
}
