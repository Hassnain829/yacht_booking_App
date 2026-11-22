<?php

namespace App\Rules;

use App\Exceptions\InvalidUploadException;
use App\Services\MediaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The upload rule for FormRequests (PLAN §8.2, §9): `['required', SafeUpload::image()]`, or for a listing
 * photo `SafeUpload::image(maxKb: 25 * 1024)`. It runs the same checks MediaService runs before saving
 * (real type from the bytes, extension, pixel size), so a bad file fails on its own field with a clear message.
 */
final class SafeUpload implements ValidationRule
{
    private function __construct(
        private readonly string $kind,
        private readonly int $maxKb,
        private readonly int $minWidth,
        private readonly int $minHeight,
    ) {}

    /** JPG, PNG, GIF or WEBP; defaults from config/media.php (10 MB, at least 600 × 400 either way round). */
    public static function image(?int $maxKb = null, ?int $minWidth = null, ?int $minHeight = null): self
    {
        return new self(
            MediaService::IMAGE,
            $maxKb ?? (int) config('media.max_kb'),
            $minWidth ?? (int) config('media.image.min_width'),
            $minHeight ?? (int) config('media.image.min_height'),
        );
    }

    /** A PDF or an image (insurance, licenses) — saved on the private disk only. */
    public static function document(?int $maxKb = null): self
    {
        return new self(MediaService::DOCUMENT, $maxKb ?? (int) config('media.max_kb'), 0, 0);
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('This file did not upload. Please try again.');

            return;
        }

        if ($value->getSize() > $this->maxKb * 1024) {
            $fail('This file is larger than '.$this->readableLimit().'.');

            return;
        }

        try {
            app(MediaService::class)->inspect($value, $this->kind, $this->minWidth, $this->minHeight);
        } catch (InvalidUploadException $e) {
            $fail($e->reason());
        }
    }

    private function readableLimit(): string
    {
        return $this->maxKb % 1024 === 0 ? intdiv($this->maxKb, 1024).' MB' : $this->maxKb.' KB';
    }
}
