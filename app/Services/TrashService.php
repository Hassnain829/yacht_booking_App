<?php

namespace App\Services;

use App\Models\Contracts\Trashable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trash, restore and delete permanently (PLAN 3.4, user requirement 2026-11-22), the same way for every
 * admin-managed record: each step checks the record's blockers first, needs a reason and writes the audit
 * log as "<morph name>.trashed / .restored / .deleted_permanently".
 *
 * - **Trash** = soft delete: hidden everywhere, files kept, can be restored.
 * - **Delete permanently** = only from the Trash: the row, its pivot rows (database cascades), its `media`
 *   rows and every stored file go. Blocked while bookings or payments point at it (financial records stay).
 *
 * A blocked step throws a ValidationException (key "action", error bag "sensitive"), so the dialog shows why.
 */
class TrashService
{
    public function __construct(private AuditService $audit, private MediaService $media) {}

    public function trash(Model&Trashable $record, string $reason, User $actor): void
    {
        if ($record->trashed()) {
            throw $this->blocked(['This '.$record->trashLabel().' is already in the Trash.']);
        }

        DB::transaction(function () use ($record, $reason, $actor): void {
            $this->guard($record->trashBlockers());
            $record->delete();
            $this->audit->record($record->getMorphClass().'.trashed', $record, reason: $reason, actor: $actor);
        });
    }

    public function restore(Model&Trashable $record, string $reason, User $actor): void
    {
        if (! $record->trashed()) {
            throw $this->blocked(['This '.$record->trashLabel().' is not in the Trash.']);
        }

        DB::transaction(function () use ($record, $reason, $actor): void {
            $record->restore();
            $this->audit->record($record->getMorphClass().'.restored', $record, reason: $reason, actor: $actor);
        });
    }

    public function forceDelete(Model&Trashable $record, string $reason, User $actor): void
    {
        if (! $record->trashed()) {
            throw $this->blocked(['Move this '.$record->trashLabel().' to the Trash first. Only records in the Trash can be deleted permanently.']);
        }

        DB::transaction(function () use ($record, $reason, $actor): void {
            $this->guard($record->permanentDeleteBlockers());

            $files = $record->ownFiles();
            $this->media->deleteAllFor($record);
            $record->forceDelete();
            foreach ($files as $disk => $paths) {
                $this->media->deleteFiles($disk, $paths); // queued, runs only after this transaction commits
            }

            $this->audit->record(
                $record->getMorphClass().'.deleted_permanently',
                $record,
                before: array_filter($record->only(['name', 'title', 'slug']), fn ($value) => filled($value)),
                reason: $reason,
                actor: $actor,
            );
        });
    }

    /** @param list<string> $blockers */
    private function guard(array $blockers): void
    {
        if ($blockers !== []) {
            throw $this->blocked($blockers);
        }
    }

    /** @param list<string> $reasons */
    private function blocked(array $reasons): ValidationException
    {
        return ValidationException::withMessages(['action' => $reasons])->errorBag('sensitive');
    }
}
