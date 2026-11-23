<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Support\Str;

/**
 * Admin forms with a web address (slug): an empty slug is made from the name / title, a typed one is
 * lower-cased. Validate it with SLUG_RULE + a unique rule that also counts records in the Trash.
 */
trait PreparesSlug
{
    public const SLUG_RULE = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    protected function prepareSlug(string $from): void
    {
        $slug = is_string($this->input('slug')) ? trim($this->input('slug')) : '';
        $source = is_string($this->input($from)) ? $this->input($from) : '';

        $this->merge(['slug' => $slug !== '' ? Str::lower($slug) : Str::slug($source)]);
    }

    /**
     * Turns a textarea with one item per line into a clean list (empty lines dropped).
     *
     * @return list<string>
     */
    protected function lines(string $field): array
    {
        $text = $this->input($field);

        return is_string($text)
            ? array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), fn (string $line) => $line !== ''))
            : [];
    }

    /** @return array<string, string> */
    protected function slugMessages(string $thing): array
    {
        return [
            'slug.unique' => "Another {$thing} already uses this web address (it may be in the Trash). Choose a different name or address.",
            'slug.regex' => 'Use lowercase letters, numbers and single dashes only, e.g. "sunset-cruise".',
            'slug.required' => 'Please enter a name, so the web address can be made from it.',
        ];
    }
}
