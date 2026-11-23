<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Plain-text fields refuse HTML (PLAN §8.2 rule 5): a tag such as `<script>` or `<a href>` is an error, not
 * something we try to clean. Blade escapes output anyway; this keeps markup out of the database entirely.
 * "Fish & chips" or "under 6 < 10" are fine — only something that looks like a tag is refused.
 */
final class PlainText implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('~<\s*/?\s*[a-z!?][^>]*>~i', $value) === 1) {
            $fail('Please use plain text here (no HTML).');
        }
    }
}
