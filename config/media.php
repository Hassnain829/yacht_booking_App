<?php

/*
| Uploads (PLAN §9, Phase 3.3). MediaService and the SafeUpload rule read this.
| Every image is re-encoded to WebP (EXIF / GPS and anything hidden in the file are dropped) and saved
| under a random name; the visitor's file name is only kept as a label in the database.
*/
return [

    // The real type (read from the file's bytes, never the browser's claim) => its name extensions.
    // A file needs an allowed real type AND an allowed extension (a PNG named .jpg is fine; a JPEG named .php is not).
    'image_types' => [
        'image/jpeg' => ['jpg', 'jpeg', 'jfif'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'], // the list-your-boat wizard accepts GIF; only the first frame is kept
    ],

    // Documents (insurance, licenses) also accept the image types above. Documents live on the private disk only.
    'document_types' => [
        'application/pdf' => ['pdf'],
    ],

    // Default limit of the SafeUpload rule (a form can pass its own, e.g. 25 MB for listing photos).
    'max_kb' => 10 * 1024,

    // MediaService refuses anything bigger, even when no form rule ran (e.g. a seeder or a command).
    'ceiling_kb' => 25 * 1024,

    'image' => [
        // Default smallest image the SafeUpload rule accepts (forms can ask for more or less).
        'min_width' => 600,
        'min_height' => 400,

        // Bigger images are refused before they are opened: decoding one could use up a shared host's memory.
        'max_pixels' => 40_000_000,

        'quality' => 80,

        // The main file is at most this wide (never enlarged); smaller copies are made for cards and thumbnails
        // (only when the image is wider than the copy). Widths in pixels.
        'width' => 1600,
        'variants' => ['card' => 800, 'thumb' => 400],
    ],

    // Raised (when the host allows it) while an image is processed.
    'memory_limit' => '512M',

];
