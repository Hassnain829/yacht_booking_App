<?php

namespace App\Services;

use App\Models\Boat;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A boat's gallery as the admin manages it (PLAN 3.4.4). The first photo is the cover. Every photo goes through
 * MediaService (WebP, card / thumb sizes, random names under boats/{ulid}/photos); order is the `sort` column,
 * kept as 0, 1, 2… Each change is written to the audit log.
 */
class BoatPhotoService
{
    public const MAX_PHOTOS = 30;

    public function __construct(private MediaService $media, private AuditService $audit) {}

    /**
     * @param  list<UploadedFile>  $files  already checked by SafeUpload
     */
    public function add(Boat $boat, array $files, User $actor): int
    {
        if ($boat->photos()->count() + count($files) > self::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photos' => 'A boat can have at most '.self::MAX_PHOTOS.' photos. Delete some first.']);
        }

        $added = [];
        foreach ($files as $file) {
            $added[] = $this->media->addImage($boat, 'photos', $file, $actor, $boat->title)->uuid;
        }
        $this->audit->record('boat.photos_added', $boat, [], ['photos' => $added], actor: $actor);

        return count($added);
    }

    /** Alt text and / or a move: "first" (make it the cover), "up" or "down". */
    public function update(Boat $boat, Media $photo, ?string $alt, ?string $move, User $actor): void
    {
        DB::transaction(function () use ($boat, $photo, $alt, $move, $actor): void {
            $before = ['photo' => $photo->uuid, 'alt' => $photo->alt, 'position' => $this->position($boat, $photo)];

            if ($alt !== null && $alt !== $photo->alt) {
                $photo->forceFill(['alt' => $alt])->save();
            }
            if ($move !== null) {
                $this->move($boat, $photo, $move);
            }

            $after = ['photo' => $photo->uuid, 'alt' => $photo->alt, 'position' => $this->position($boat, $photo)];
            if ($after !== $before) {
                $this->audit->record('boat.photo_changed', $boat, $before, $after, actor: $actor);
            }
        });
    }

    public function delete(Boat $boat, Media $photo, User $actor): void
    {
        DB::transaction(function () use ($boat, $photo, $actor): void {
            $this->media->delete($photo); // the files go after the commit
            $this->renumber($boat);
            $this->audit->record('boat.photo_deleted', $boat, ['photo' => $photo->uuid, 'path' => $photo->path], [], actor: $actor);
        });
    }

    private function move(Boat $boat, Media $photo, string $move): void
    {
        $ids = $boat->photos()->pluck('id')->all();
        $index = array_search($photo->getKey(), $ids, true);
        $target = match ($move) {
            'first' => 0,
            'up' => max(0, $index - 1),
            'down' => min(count($ids) - 1, $index + 1),
            default => $index,
        };
        array_splice($ids, $index, 1);
        array_splice($ids, $target, 0, [$photo->getKey()]);
        $this->renumber($boat, $ids);
    }

    /** @param list<int>|null $ids in the new order; null = keep the current order */
    private function renumber(Boat $boat, ?array $ids = null): void
    {
        foreach ($ids ?? $boat->photos()->pluck('id')->all() as $sort => $id) {
            Media::query()->whereKey($id)->update(['sort' => $sort]);
        }
    }

    private function position(Boat $boat, Media $photo): int
    {
        return (int) array_search($photo->getKey(), $boat->photos()->pluck('id')->all(), true);
    }
}
