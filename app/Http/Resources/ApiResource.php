<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Base for every JSON response body (PLAN §8.2 rule 8, CLAUDE.md). The shape is `{"data": …}`.
 *
 * - Each resource lists its fields one by one: the base toArray() throws, so `parent::toArray()`
 *   (every column of the model) can't be returned by accident.
 * - Records are identified by their ULID (`publicId()`), never the numeric id.
 * - Money is integer cents (`*_cents`), rates basis points (`*_bp`); dates are ISO 8601 in UTC (`iso()`),
 *   and the browser shows them in Chicago time (resources/js/lib/dates.js).
 */
abstract class ApiResource extends JsonResource
{
    /**
     * Each resource lists its own fields. JsonResource's default (every attribute of the model) is refused,
     * so a resource that forgets to list them fails loudly instead of leaking columns.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        throw new LogicException(static::class.' must list its fields in toArray().');
    }

    /** The ULID (the route key), never the numeric id. */
    protected function publicId(): string
    {
        /** @var Model $model */
        $model = $this->resource;

        return (string) $model->getRouteKey();
    }

    /** "2026-11-22T15:00:00Z", or null. */
    protected static function iso(?CarbonInterface $at): ?string
    {
        return $at?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
