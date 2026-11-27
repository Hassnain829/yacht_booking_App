<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Support\Payments\Intent;
use App\Support\Payments\ProviderEvent;
use App\Support\Payments\ProviderRefund;
use App\Support\Payments\SavedCard;
use App\Support\Payments\SetupStart;

/**
 * The card payment provider (PLAN §6.6, §8.4; D22: every booking is paid in full at checkout). Stripe in real use
 * (StripeGateway); FakeGateway for local development and tests, refused in production. Amounts always come from
 * the booking, worked out by QuoteService on the server; refunds from RefundService once the admin approves (D23).
 */
interface PaymentGateway
{
    /** "stripe" or "fake" (payments.provider). */
    public function name(): string;

    /** The key the browser's payment form needs (Stripe's publishable key); null for the fake gateway. */
    public function publishableKey(): ?string;

    /** A new customer at the provider for the guest (saved cards hang off it). */
    public function createCustomer(User $user): string;

    /**
     * A new intent for the payment's amount, carrying the payment's idempotency key. With the guest's customer, a
     * saved card (`provider_payment_method_id`) or the wish to save the new card (`save_card`).
     */
    public function createIntent(Payment $payment, Booking $booking, ?string $customerId): Intent;

    /** The open intent, set to the payment's (new) amount, card and save choice. */
    public function updateIntent(Payment $payment, ?string $customerId): Intent;

    /** The intent as the provider sees it now (never what the browser says). */
    public function retrieve(Payment $payment): Intent;

    /** Cancel an open intent (an expired hold); nothing when it can't be cancelled any more. */
    public function cancel(Payment $payment): void;

    /** Send money back to the card that paid, once (the idempotency key). */
    public function refund(Payment $payment, int $amountCents, string $idempotencyKey, array $metadata = []): ProviderRefund;

    /** Start saving a card without paying (the account's "Add a card"). */
    public function createSetup(string $customerId): SetupStart;

    /** The card a finished setup saved — only when it succeeded and belongs to this customer. */
    public function retrieveSetup(string $setupId, string $customerId): ?SavedCard;

    /** Forget a saved card at the provider. */
    public function detach(string $paymentMethodId): void;

    /** A webhook whose signature checks out, or null (a wrong or missing signature, or no webhooks). */
    public function parseWebhook(string $payload, ?string $signature): ?ProviderEvent;
}
