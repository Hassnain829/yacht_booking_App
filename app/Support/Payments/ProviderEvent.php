<?php

namespace App\Support\Payments;

/** A webhook event whose signature checked out: its id (stored once), type, the intent it is about, the raw event. */
final readonly class ProviderEvent
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $intentId,
        public array $payload,
    ) {}
}
