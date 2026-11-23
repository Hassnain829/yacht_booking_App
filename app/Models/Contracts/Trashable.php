<?php

namespace App\Models\Contracts;

/**
 * A record the admin can move to the Trash, restore and delete permanently (PLAN 3.4, `TrashService`).
 * The model must use SoftDeletes. Each check returns sentences the admin reads; an empty list means "allowed".
 */
interface Trashable
{
    /** What the admin calls one of these, e.g. "harbor". */
    public function trashLabel(): string;

    /** The name shown in messages and the audit log, e.g. "Navy Pier". */
    public function trashTitle(): string;

    /** @return list<string> why it can't go to the Trash right now */
    public function trashBlockers(): array;

    /** @return list<string> why it can't be deleted permanently (e.g. bookings or payments point at it) */
    public function permanentDeleteBlockers(): array;

    /**
     * Files the record keeps in its own columns (not `media` rows), deleted with it.
     *
     * @return array<string, list<string>> disk => paths
     */
    public function ownFiles(): array;
}
