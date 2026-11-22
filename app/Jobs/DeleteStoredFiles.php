<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes stored files after their record is gone (PLAN §9). Queued so a boat with many photos is removed
 * quickly, and only after the transaction commits, so a rolled-back delete never loses its files.
 */
class DeleteStoredFiles implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $paths  relative to the disk
     */
    public function __construct(public string $disk, public array $paths)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        Storage::disk($this->disk)->delete($this->paths);
    }
}
