<?php

namespace App\Services;

use App\Models\Harbor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Adds and edits harbors from Admin → Harbors (PLAN 3.4.1). Every save writes the audit log with the fields
 * that changed. A new image goes through MediaService (WebP, random name, `harbors/…`); the old file is
 * deleted only after the database change is saved, and a failed save removes the new file again.
 */
class HarborService
{
    public function __construct(private MediaService $media, private AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data  validated fields (HarborRequest)
     */
    public function create(array $data, ?UploadedFile $image, User $actor): Harbor
    {
        $harbor = new Harbor;

        return $this->save($harbor, $data, $image, false, $actor);
    }

    /**
     * @param  array<string, mixed>  $data  validated fields (HarborRequest)
     */
    public function update(Harbor $harbor, array $data, ?UploadedFile $image, bool $removeImage, User $actor): Harbor
    {
        return $this->save($harbor, $data, $image, $removeImage, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Harbor $harbor, array $data, ?UploadedFile $image, bool $removeImage, User $actor): Harbor
    {
        $oldImage = $harbor->image_path;
        $newImage = $image === null ? null : $this->media->storeImage($image, 'harbors', variants: false)->path;

        $harbor->fill($data);
        if ($newImage !== null) {
            $harbor->image_path = $newImage;
        } elseif ($removeImage) {
            $harbor->image_path = null;
        }

        try {
            DB::transaction(function () use ($harbor, $actor): void {
                $isNew = ! $harbor->exists;
                [$before, $after] = $this->audit->changes($harbor);
                if ($after === []) {
                    return; // nothing changed: no save, no audit entry
                }
                $harbor->save();
                $this->audit->record($isNew ? 'harbor.created' : 'harbor.updated', $harbor, $before, $after, actor: $actor);
            });
        } catch (Throwable $e) {
            if ($newImage !== null) {
                $this->media->deleteFiles('public', [$newImage]);
            }
            throw $e;
        }

        if ($oldImage !== null && $oldImage !== $harbor->image_path) {
            $this->media->deleteFiles('public', [$oldImage]);
        }

        return $harbor;
    }
}
