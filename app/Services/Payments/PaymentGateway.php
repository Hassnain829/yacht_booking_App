<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Support\Payments\Intent;
use App\Support\Payments\ProviderEvent;

/**
 * The card payment provider (PLAN §6.6, §8.4; D22: every booking is paid in full at checkout). Stripe in real use
 * (StripeGateway); FakeGateway for local development and tests, refused in production. Amounts always come from
 * the booking, worked out by QuoteService on the server.
 */
interface PaymentGateway
{
    /** "stripe" or "fake" (payments.provider). */
    public function name(): string;

    /** The key the browser's payment form needs (Stripe's publishable key); null for the fake gateway. */
    public function publishableKey(): ?string;

    /** A new intent for the payment's amount, carrying the payment's idempotency key. */
    public function createIntent(Payment $payment, Booking $booking): Intent;

    /** The open intent, set to the payment's (new) amount. */
    public function updateAmount(Payment $payment): Intent;

    /** The intent as the provider sees it now (never what the browser says). */
    public function retrieve(Payment $payment): Intent;

    /** Cancel an open intent (an expired hold); nothing when it can't be cancelled any more. */
    public function cancel(Payment $payment): void;

    /** A webhook whose signature checks out, or null (a wrong or missing signature, or no webhooks). */
    public function parseWebhook(string $payload, ?string $signature): ?ProviderEvent;
}
