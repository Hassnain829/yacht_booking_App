<?php

namespace App\Support;

/**
 * What MediaService saved for one upload: paths are relative to the disk (PLAN §9).
 * A record with a single image column (harbors, experiences, captains) keeps `path`;
 * a `media` row also keeps the variants and the facts below.
 */
final readonly class StoredFile
{
    /**
     * @param  array<string, string>  $variants  e.g. ["card" => "boats/…/9f1c…-card.webp"]
     */
    public function __construct(
        public string $disk,
        public string $path,
        public array $variants,
        public string $mime,
        public int $sizeBytes,
        public ?int $width,
        public ?int $height,
    ) {}

    /** @return list<string> the main file and its variants */
    public function paths(): array
    {
        return [$this->path, ...array_values($this->variants)];
    }
}
