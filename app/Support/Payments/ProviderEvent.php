<?php

namespace App\Support\Payments;

/**
 * A webhook event whose signature checked out: its id (stored once), type, the intent it is about, the raw event and
 * the object it carries (an intent, a refund or a dispute).
 */
final readonly class ProviderEvent
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $object
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $intentId,
        public array $payload,
        public array $object = [],
    ) {}
}
