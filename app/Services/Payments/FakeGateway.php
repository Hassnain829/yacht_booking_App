<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Support\Payments\Intent;
use App\Support\Payments\ProviderEvent;
use App\Support\Payments\ProviderRefund;
use App\Support\Payments\SavedCard;
use App\Support\Payments\SetupStart;
use Illuminate\Support\Str;

/**
 * Local development and tests without Stripe keys (payments.driver = fake; refused in production). The checkout
 * and the account show a test card form; "paying" or "adding" a test card number is done by our own test-card
 * routes (they exist only with this gateway). Refunds succeed at once. No real money moves.
 */
class FakeGateway implements PaymentGateway
{
    /** Test cards: 4242… pays, 4000 0000 0000 0002 is declined. */
    public const DECLINED_CARD = '4000000000000002';

    public function name(): string
    {
        return 'fake';
    }

    public function publishableKey(): ?string
    {
        return null;
    }

    public function createCustomer(User $user): string
    {
        return 'fake_cus_'.$user->ulid;
    }

    public function createIntent(Payment $payment, Booking $booking, ?string $customerId): Intent
    {
        return new Intent('fake_pi_'.self::id(), $payment->status, $payment->amount_cents);
    }

    public function updateIntent(Payment $payment, ?string $customerId): Intent
    {
        return $this->retrieve($payment);
    }

    public function retrieve(Payment $payment): Intent
    {
        return new Intent((string) $payment->provider_intent_id, $payment->status, $payment->amount_cents, cardBrand: $payment->card_brand, cardLast4: $payment->card_last4, paymentMethodId: $payment->provider_payment_method_id, feeCents: $payment->fee_cents);
    }

    public function cancel(Payment $payment): void {}

    public function refund(Payment $payment, int $amountCents, string $idempotencyKey, array $metadata = []): ProviderRefund
    {
        return new ProviderRefund('fake_re_'.substr(hash('sha256', $idempotencyKey), 0, 24), RefundStatus::Succeeded);
    }

    public function createSetup(string $customerId): SetupStart
    {
        return new SetupStart('fake_seti_'.self::id(), null);
    }

    public function retrieveSetup(string $setupId, string $customerId): ?SavedCard
    {
        return null; // the fake gateway saves cards through its test card route
    }

    public function detach(string $paymentMethodId): void {}

    public function parseWebhook(string $payload, ?string $signature): ?ProviderEvent
    {
        return null;
    }

    public static function id(): string
    {
        return Str::lower((string) Str::ulid());
    }
}
