<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * A trip that can't take place at the chosen time (AvailabilityService::ensureFree). It is a validation error on
 * the field that has to change — `time` for most reasons, else `guests`, `hours` or `experience` — so an endpoint
 * answers 422 with a message the customer can act on.
 */
class AvailabilityException extends ValidationException
{
    public static function on(string $field, string $message): self
    {
        return self::withMessages([$field => $message]);
    }
}
