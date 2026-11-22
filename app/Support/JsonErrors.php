<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * One error shape for every JSON response (PLAN §8.2 rule 8): `{"message": "…"}`, plus `"errors": {field: […]}`
 * for validation (Laravel's own 422). The messages are generic on purpose: Laravel's default texts can leak
 * internals, e.g. a 404 "No query results for model [App\Models\Boat] 01J…" or a 405 listing the methods.
 * resources/js/lib/http.js shows its own friendly text for each status; only 409 messages are ours to show.
 */
final class JsonErrors
{
    public const MESSAGES = [
        400 => 'Bad request.',
        401 => 'Unauthenticated.',
        403 => 'Forbidden.',
        404 => 'Not found.',
        405 => 'Method not allowed.',
        409 => 'Conflict.',
        413 => 'Too large.',
        419 => 'Session expired.',
        422 => 'The given data was invalid.',
        429 => 'Too many requests.',
        500 => 'Server error.',
        503 => 'Service unavailable.',
    ];

    public static function from(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        // A 409 carries a message we wrote for the user ("That time was just booked …").
        $message = $status === 409 && $e->getMessage() !== ''
            ? $e->getMessage()
            : (self::MESSAGES[$status] ?? ($status >= 500 ? self::MESSAGES[500] : self::MESSAGES[400]));

        return response()->json(['message' => $message], $status, $e->getHeaders());
    }
}
