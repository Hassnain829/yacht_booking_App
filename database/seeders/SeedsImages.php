<?php

namespace Database\Seeders;

use App\Services\MediaService;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Seeders store the design images (public/images/…) through MediaService, like any upload: a WebP copy
 * (with card / thumb sizes for boat photos) on the public disk. The admin can later replace or delete the
 * copy without ever touching the design file the static pages use (PLAN 3.2, user decision).
 */
trait SeedsImages
{
    /** @param  string  $designPath  e.g. "images/yacht-azure.jpg" (relative to public/); must exist */
    protected function designImage(string $designPath): File
    {
        return new File(public_path($designPath));
    }

    protected function media(): MediaService
    {
        return app(MediaService::class);
    }
}
