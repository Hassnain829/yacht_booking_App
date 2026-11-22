<?php

namespace App\Services;

use App\Exceptions\InvalidUploadException;
use App\Jobs\DeleteStoredFiles;
use App\Models\Media;
use App\Models\User;
use App\Support\StoredFile;
use finfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\Exceptions\MissingDependencyException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Every uploaded file goes through here (PLAN §9). The pipeline:
 *  1. inspect — the real type from the file's bytes (finfo) and an allowed name extension; images also
 *     their pixel size (refused before decoding when it could exhaust the host's memory).
 *  2. images are re-encoded to WebP, which drops EXIF / GPS and anything hidden after the image data;
 *  3. sizes: the main file (≤ 1600 px wide) plus "card" (800) and "thumb" (400) copies;
 *  4. saved under a random UUID name — the visitor's file name is only kept as a label in the database;
 *  5. only .webp / .pdf files are ever written, so nothing stored here can run as a script.
 * PDFs are accepted only as documents, which always go to the private "local" disk.
 */
class MediaService
{
    public const IMAGE = 'image';

    public const DOCUMENT = 'document';

    private const PRIVATE_DISK = 'local';

    /**
     * Checks a file before anything is saved. Throws InvalidUploadException with a message for the visitor.
     *
     * @param  string  $kind  self::IMAGE or self::DOCUMENT (PDF or image)
     * @param  int  $minWidth  smallest size, either way round (a portrait photo counts as well as a landscape one)
     * @return array{mime: string, width: int|null, height: int|null}
     */
    public function inspect(File $file, string $kind = self::IMAGE, int $minWidth = 0, int $minHeight = 0): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            throw InvalidUploadException::because('This file could not be read. Please try again.');
        }

        $ceilingKb = (int) config('media.ceiling_kb');
        if (filesize($path) > $ceilingKb * 1024) {
            throw InvalidUploadException::because('This file is larger than '.intdiv($ceilingKb, 1024).' MB.');
        }

        $types = $kind === self::DOCUMENT
            ? config('media.document_types') + config('media.image_types')
            : config('media.image_types');
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = strtolower($file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension());

        // The real type must be allowed, and the name must end in one of the allowed extensions (never .php, .svg …).
        // A mix-up inside the family is fine (a WebP saved from a website as "photo.jpg"): we never use the
        // visitor's extension — the stored name comes from the real type.
        if (! isset($types[$mime]) || ! in_array($extension, array_merge(...array_values($types)), true)) {
            throw InvalidUploadException::because($kind === self::DOCUMENT
                ? 'This file must be a PDF or a JPG, PNG, GIF or WEBP image.'
                : 'This file must be a JPG, PNG, GIF or WEBP image.');
        }

        if (! str_starts_with($mime, 'image/')) {
            return ['mime' => $mime, 'width' => null, 'height' => null];
        }

        $size = @getimagesize($path);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw $this->unreadableImage();
        }
        [$width, $height] = $size;

        $maxPixels = (int) config('media.image.max_pixels');
        if ($width * $height > $maxPixels) {
            throw InvalidUploadException::because('This image is too large: at most '.intdiv($maxPixels, 1_000_000).' megapixels.');
        }
        if (max($width, $height) < max($minWidth, $minHeight) || min($width, $height) < min($minWidth, $minHeight)) {
            throw InvalidUploadException::because("This image is too small: at least {$minWidth} × {$minHeight} pixels.");
        }

        return ['mime' => $mime, 'width' => $width, 'height' => $height];
    }

    /**
     * Re-encodes an image to WebP and saves it (plus its smaller copies) under a random name. For records with
     * one image column (harbors, experiences, captains); boat photos use addImage(), which also writes `media`.
     *
     * @param  string  $directory  on the disk, e.g. "experiences"
     * @param  int|null  $width  the main file's largest width (default config media.image.width)
     * @param  bool  $variants  also make the "card" / "thumb" copies
     */
    public function storeImage(File $file, string $directory, string $disk = 'public', ?int $width = null, bool $variants = true): StoredFile
    {
        $this->inspect($file);
        $image = $this->decode($file);
        $base = trim($directory, '/').'/'.Str::uuid();

        $image->scaleDown(width: $width ?? (int) config('media.image.width'));
        [$mainWidth, $mainHeight] = [$image->width(), $image->height()];
        $files = [$base.'.webp' => $main = $this->encode($image)];

        // Biggest copy first: each one is scaled down from the previous (already encoded) one, which is faster.
        $sizes = $variants ? config('media.image.variants') : [];
        arsort($sizes);
        $paths = [];
        foreach ($sizes as $name => $variantWidth) {
            if ($image->width() <= $variantWidth) {
                continue; // a small image is not enlarged; the main file is used instead
            }
            $image->scaleDown(width: $variantWidth);
            $paths[$name] = "{$base}-{$name}.webp";
            $files[$paths[$name]] = $this->encode($image);
        }

        $this->put($disk, $files);

        return new StoredFile($disk, $base.'.webp', $paths, 'image/webp', strlen($main), $mainWidth, $mainHeight);
    }

    /** A photo of a record (e.g. a boat's gallery): stored with its sizes, as a `media` row (last in order). */
    public function addImage(Model $owner, string $collection, File $file, ?User $uploader = null, ?string $alt = null): Media
    {
        $stored = $this->storeImage($file, $this->directoryFor($owner, $collection));

        return $this->attach($owner, $collection, $stored, $file, $uploader, $alt);
    }

    /**
     * A private document (insurance, license, complaint photo): a PDF is kept as it is, an image is re-encoded.
     * Always on the private disk — served only through FileController, which checks MediaPolicy.
     */
    public function addDocument(Model $owner, string $collection, File $file, ?User $uploader = null): Media
    {
        $directory = $this->directoryFor($owner, $collection);
        $facts = $this->inspect($file, self::DOCUMENT);

        $stored = str_starts_with($facts['mime'], 'image/')
            ? $this->storeImage($file, $directory, self::PRIVATE_DISK, variants: false)
            : $this->storeDocument($file, $directory, $facts['mime']);

        return $this->attach($owner, $collection, $stored, $file, $uploader, null);
    }

    /** Removes a media row; its files are deleted by a queued job once the transaction commits. */
    public function delete(Media $media): void
    {
        // Only when the row was really deleted: a stale model (already gone) must never take the files of a row that exists.
        if ($media->delete() === true) {
            $this->deleteFiles($media->disk, $media->paths());
        }
    }

    /** Removes every file of a record (used when the record is deleted for good). @return int rows removed */
    public function deleteAllFor(Model $owner): int
    {
        $items = Media::query()->whereMorphedTo('model', $owner)->get();
        $items->each(fn (Media $media) => $this->delete($media));

        return $items->count();
    }

    /**
     * Deletes files in a queued job, after the transaction commits (e.g. an image column that was replaced).
     *
     * @param  array<int, string|null>  $paths
     */
    public function deleteFiles(string $disk, array $paths): void
    {
        $paths = array_values(array_filter($paths, filled(...)));
        if ($paths !== []) {
            DeleteStoredFiles::dispatch($disk, $paths);
        }
    }

    /** The public URL of a file on the public disk, e.g. experiences.image_path. */
    public function url(?string $path): ?string
    {
        return blank($path) ? null : Storage::disk('public')->url($path);
    }

    /** Sends a private file: PDFs as a download, images inline. Never cached by the browser or a proxy. */
    public function download(Media $media): StreamedResponse
    {
        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        $name = (Str::slug(pathinfo((string) $media->original_name, PATHINFO_FILENAME)) ?: 'file').'.'.pathinfo($media->path, PATHINFO_EXTENSION);
        $headers = ['Content-Type' => $media->mime, 'Cache-Control' => 'private, no-store'];

        return $media->mime === 'application/pdf'
            ? $disk->download($media->path, $name, $headers)
            : $disk->response($media->path, $name, $headers);
    }

    /** Where a record's files go, e.g. "boats/01JD…/photos". Never the numeric id (URLs must not show it). */
    public function directoryFor(Model $owner, string $collection): string
    {
        if (! $owner->exists) {
            throw new LogicException('Save the record before adding files to it.');
        }
        if (preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $collection) !== 1) {
            throw new LogicException("Invalid media collection [{$collection}].");
        }

        return Str::plural($owner->getMorphClass()).'/'.($owner->getAttribute('ulid') ?? $owner->getRouteKey()).'/'.$collection;
    }

    private function decode(File $file): ImageInterface
    {
        $this->raiseMemoryLimit();
        $images = $this->images();

        try {
            return $images->decodePath((string) $file->getRealPath());
        } catch (ImageException) {
            throw $this->unreadableImage();
        }
    }

    private function images(): ImageManager
    {
        try {
            return app(ImageManager::class);
        } catch (MissingDependencyException $e) {
            throw new RuntimeException('Image processing needs the PHP GD extension. Locally: remove the ";" before "extension=gd" in C:\xampp\php\php.ini, then restart PHP.', previous: $e);
        }
    }

    private function encode(ImageInterface $image): string
    {
        return $image->encode(new WebpEncoder(quality: (int) config('media.image.quality'), strip: true))->toString();
    }

    /** A PDF on the private disk, copied byte for byte (streamed) under a random name. */
    private function storeDocument(File $file, string $directory, string $mime): StoredFile
    {
        $name = Str::uuid().'.'.config("media.document_types.{$mime}.0");
        $path = Storage::disk(self::PRIVATE_DISK)->putFileAs($directory, $file, $name);
        if ($path === false) {
            throw new RuntimeException('Could not save a document on the private disk.');
        }

        return new StoredFile(self::PRIVATE_DISK, $path, [], $mime, (int) $file->getSize(), null, null);
    }

    /** @param  array<string, string>  $files  path => contents; a failure removes what was already written */
    private function put(string $disk, array $files): void
    {
        $written = [];
        foreach ($files as $path => $contents) {
            if (! Storage::disk($disk)->put($path, $contents)) {
                Storage::disk($disk)->delete($written);
                throw new RuntimeException("Could not save an upload on the {$disk} disk.");
            }
            $written[] = $path;
        }
    }

    /** Writes the media row; if that fails, the files just saved are removed again. */
    private function attach(Model $owner, string $collection, StoredFile $stored, File $file, ?User $uploader, ?string $alt): Media
    {
        try {
            return DB::transaction(function () use ($owner, $collection, $stored, $file, $uploader, $alt): Media {
                $last = Media::query()->whereMorphedTo('model', $owner)->where('collection', $collection)->max('sort');

                $media = new Media([
                    'collection' => $collection,
                    'disk' => $stored->disk,
                    'path' => $stored->path,
                    'variants' => $stored->variants === [] ? null : $stored->variants,
                    'original_name' => $this->originalName($file),
                    'mime' => $stored->mime,
                    'size_bytes' => $stored->sizeBytes,
                    'width' => $stored->width,
                    'height' => $stored->height,
                    'alt' => blank($alt) ? null : mb_substr(trim($alt), 0, 255),
                    'sort' => $last === null ? 0 : $last + 1,
                ]);
                $media->model()->associate($owner);
                $media->uploader()->associate($uploader);
                $media->save();

                return $media;
            });
        } catch (Throwable $e) {
            Storage::disk($stored->disk)->delete($stored->paths());
            throw $e;
        }
    }

    /** The visitor's file name, as a label only (it is never used on disk): no folders or control characters. */
    private function originalName(File $file): ?string
    {
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', mb_scrub($name)))));

        return $name === '' ? null : mb_substr($name, 0, 200);
    }

    private function raiseMemoryLimit(): void
    {
        $current = ini_parse_quantity((string) ini_get('memory_limit'));
        $wanted = (string) config('media.memory_limit');

        if ($current !== -1 && $current < ini_parse_quantity($wanted)) {
            @ini_set('memory_limit', $wanted);
        }
    }

    private function unreadableImage(): InvalidUploadException
    {
        return InvalidUploadException::because('This image could not be read. Please choose a different file.');
    }
}
