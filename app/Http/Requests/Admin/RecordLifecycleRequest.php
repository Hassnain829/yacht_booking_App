<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * Trash / restore / delete permanently on one record (PLAN 3.4, `TrashService`). The record is the route's
 * bound model; the controller method name picks the Policy ability: trash → delete, restore → restore,
 * destroy → forceDelete.
 */
class RecordLifecycleRequest extends SensitiveActionRequest
{
    private const ABILITIES = ['trash' => 'delete', 'restore' => 'restore', 'destroy' => 'forceDelete'];

    public function authorize(): bool
    {
        $record = collect($this->route()?->parameters() ?? [])->first(fn ($parameter) => $parameter instanceof Model);
        $ability = self::ABILITIES[$this->route()?->getActionMethod()] ?? null;

        return $record !== null && $ability !== null && $this->user()?->can($ability, $record) === true;
    }
}
