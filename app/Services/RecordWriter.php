<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Saves an admin-managed record (PLAN 3.4): fills the validated fields, saves in a transaction and writes one
 * audit entry "<morph>.created" / "<morph>.updated" with only what changed (nothing changed → no entry).
 *
 * - An image column (e.g. `image_path`) goes through MediaService (WebP, random name). The old file is deleted
 *   after the save; if the save fails, the new file is removed again.
 * - `$relations` runs inside the same transaction after the save (pivot syncs, opening hours…). It returns
 *   [before, after] for the audit entry.
 */
class RecordWriter
{
    public function __construct(private MediaService $media, private AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data  validated, fillable fields
     * @param  array{file?: ?UploadedFile, remove?: bool, column?: string, directory?: string}  $image
     * @param  list<string>  $mask  fields logged only as "changed" (secrets)
     * @param  (callable(Model): array{0: array<string, mixed>, 1: array<string, mixed>})|null  $relations
     * @param  string|null  $reason  for sensitive edits (e.g. a cancellation policy): saved with the audit entry
     */
    public function save(Model $record, array $data, User $actor, array $image = [], array $mask = [], ?callable $relations = null, ?string $reason = null): Model
    {
        $column = $image['column'] ?? 'image_path';
        $file = $image['file'] ?? null;
        $oldPath = $record->getAttribute($column);
        $newPath = $file instanceof UploadedFile
            ? $this->media->storeImage($file, $image['directory'] ?? $record->getTable(), variants: false)->path
            : null;

        $record->fill($data);
        if ($newPath !== null) {
            $record->setAttribute($column, $newPath);
        } elseif (($image['remove'] ?? false) === true) {
            $record->setAttribute($column, null);
        }

        try {
            DB::transaction(function () use ($record, $actor, $mask, $relations, $reason): void {
                $isNew = ! $record->exists;
                [$before, $after] = $this->audit->changes($record, $mask);
                if ($isNew || $record->isDirty()) {
                    $record->save();
                }
                if ($relations !== null) {
                    [$relationsBefore, $relationsAfter] = $relations($record);
                    $before += $relationsBefore;
                    $after += $relationsAfter;
                }
                if ($isNew || $after !== [] || $before !== []) {
                    $this->audit->record($record->getMorphClass().($isNew ? '.created' : '.updated'), $record, $before, $after, $reason, $actor);
                }
            });
        } catch (Throwable $e) {
            if ($newPath !== null) {
                $this->media->deleteFiles('public', [$newPath]);
            }
            throw $e;
        }

        if (filled($oldPath) && $oldPath !== $record->getAttribute($column)) {
            $this->media->deleteFiles('public', [$oldPath]);
        }

        return $record;
    }
}
