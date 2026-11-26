<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Support\Payments\Intent;
use App\Support\Payments\ProviderEvent;
use Illuminate\Support\Str;

/**
 * Local development and tests without Stripe keys (payments.driver = fake; refused in production). The checkout
 * shows a test card form; "paying" with a test card number marks the payment as succeeded on the server
 * (CheckoutController@fakePay, a route that exists only with this gateway). No real money moves.
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

    public function createIntent(Payment $payment, Booking $booking): Intent
    {
        return new Intent('fake_pi_'.Str::lower((string) Str::ulid()), $payment->status, $payment->amount_cents);
    }

    public function updateAmount(Payment $payment): Intent
    {
        return $this->retrieve($payment);
    }

    public function retrieve(Payment $payment): Intent
    {
        return new Intent((string) $payment->provider_intent_id, $payment->status, $payment->amount_cents, cardBrand: $payment->card_brand, cardLast4: $payment->card_last4);
    }

    public function cancel(Payment $payment): void {}

    public function parseWebhook(string $payload, ?string $signature): ?ProviderEvent
    {
        return null;
    }
}
