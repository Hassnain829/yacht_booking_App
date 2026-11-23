<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the audit log (PLAN §8.5): every admin action, status change and money action.
 * Callers pass only the fields that changed — never passwords, tokens or card data.
 */
class AuditService
{
    /**
     * @param  string  $action  dotted name, e.g. "user.status_changed"
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  User|null  $actor  who did it; null = the system or the command line
     */
    public function record(string $action, ?Model $subject = null, array $before = [], array $after = [], ?string $reason = null, ?User $actor = null): AuditLog
    {
        // Artisan commands and queued jobs have no visitor: no IP or browser to record.
        $console = app()->runningInConsole() && ! app()->runningUnitTests();
        $request = request();

        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before === [] ? null : $before,
            'after' => $after === [] ? null : $after,
            'reason' => blank($reason) ? null : mb_substr(trim($reason), 0, 1000),
            'ip_address' => $console ? null : $request->ip(),
            'user_agent' => $console || ! $request->userAgent() ? null : mb_substr($request->userAgent(), 0, 500),
        ]);
    }

    /**
     * The unsaved changes of a model as [before, after], ready for record(). Call it after fill(), before save().
     * Keys in `$mask` (secrets such as an encrypted license number) are logged only as "changed".
     *
     * @param  list<string>  $mask
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function changes(Model $model, array $mask = []): array
    {
        $before = [];
        $after = [];

        foreach (array_keys($model->getDirty()) as $key) {
            $masked = in_array($key, $mask, true);
            $before[$key] = $masked ? '(hidden)' : ($model->exists ? $model->getOriginal($key) : null);
            $after[$key] = $masked ? '(changed)' : $model->getAttribute($key);
        }

        return [$model->exists ? $before : [], $after];
    }
}
