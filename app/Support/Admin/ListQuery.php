<?php

namespace App\Support\Admin;

use Illuminate\Http\Request;

/**
 * Reads an admin list's query string safely (PLAN §8.2 rule 7): every value is checked against a whitelist,
 * anything else (arrays, unknown columns, "<script>") falls back to the default. Never put a value from here
 * into SQL yourself — map it to a column you chose, as the `sort()` result does.
 */
final readonly class ListQuery
{
    public const PER_PAGE = 20;

    public function __construct(private Request $request) {}

    /** The search box: trimmed, at most `$max` characters, '' when missing or not a string. */
    public function search(string $key = 'q', int $max = 60): string
    {
        $value = $this->request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    /**
     * @param  list<string>  $allowed
     */
    public function pick(string $key, array $allowed, ?string $default = null): ?string
    {
        $value = $this->request->query($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * The sort the visitor asked for, if it is one of ours.
     *
     * @param  array<string, string>  $columns  URL name => database column, e.g. ['name' => 'name', 'boats' => 'boats_count']
     * @return array{0: string, 1: string, 2: 'asc'|'desc'} [URL name, column, direction]
     */
    public function sort(array $columns, string $default, string $defaultDir = 'asc'): array
    {
        $sort = $this->pick('sort', array_keys($columns), $default);
        $dir = $this->pick('dir', ['asc', 'desc'], $defaultDir);

        return [$sort, $columns[$sort], $dir === 'desc' ? 'desc' : 'asc'];
    }

    /** `?view=trash` shows the records in the Trash instead of the active ones. */
    public function inTrash(): bool
    {
        return $this->pick('view', ['trash']) === 'trash';
    }
}
