<?php

namespace App\Support\Payments;

use App\Enums\RefundStatus;

/** A refund as the provider reports it: its id, status and, when it failed, why. */
final readonly class ProviderRefund
{
    public function __construct(
        public string $id,
        public RefundStatus $status,
        public ?string $failureReason = null,
    ) {}
}
