<?php

use Intervention\Image\Drivers\Gd\Driver;

/*
| Intervention Image (used only by MediaService, PLAN §9). GD is on every Namecheap plan; set
| IMAGE_DRIVER to Intervention\Image\Drivers\Imagick\Driver to use Imagick instead.
| Local XAMPP: enable `extension=gd` in C:\xampp\php\php.ini.
*/
return [

    'driver' => env('IMAGE_DRIVER', Driver::class),

    'options' => [
        // Turn photos the right way up from their EXIF orientation before the EXIF is dropped.
        'autoOrientation' => true,
        // Animated GIFs: keep only the first frame.
        'decodeAnimation' => false,
        'backgroundColor' => 'ffffff',
        // Never write EXIF / GPS / comments into the files we save.
        'strip' => true,
    ],

];
