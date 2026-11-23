<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for records only the admin manages (PLAN 3.4): one named Gate decides everything, and a record in the
 * Trash can only be restored or deleted permanently, not edited. Subclasses name their Gate.
 */
abstract class ManagedRecordPolicy
{
    /** The Gate ability, e.g. "manage-fleet". */
    abstract protected function ability(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->ability());
    }

    public function create(User $user): bool
    {
        return $user->can($this->ability());
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can($this->ability()) && ! (method_exists($record, 'trashed') && $record->trashed());
    }

    /** Move to the Trash. */
    public function delete(User $user, Model $record): bool
    {
        return $user->can($this->ability());
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can($this->ability());
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->can($this->ability());
    }
}
