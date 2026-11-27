<?php

namespace App\Support\Payments;

/** A provider's setup intent for saving a card without paying: its id and the secret the browser confirms it with. */
final readonly class SetupStart
{
    public function __construct(
        public string $id,
        public ?string $clientSecret,
    ) {}
}
