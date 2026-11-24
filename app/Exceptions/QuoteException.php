<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Choices that can't be priced together (a length the boat doesn't offer, too many guests, a hidden add-on…).
 * It is a validation error, so an endpoint that skipped a FormRequest check still answers 422 on the field
 * (hours, guests, experience, addons, gratuity) instead of a server error.
 */
class QuoteException extends ValidationException
{
    public static function on(string $field, string $message): self
    {
        return self::withMessages([$field => $message]);
    }
}
