<?php

namespace App\Services\Payments;

use App\Enums\PaymentIntentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\Payments\Intent;
use App\Support\Payments\ProviderEvent;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe (PLAN §8.4): a PaymentIntent for the full amount, confirmed in the browser with the Payment Element
 * (card data never reaches our server, PCI SAQ-A; 3-D Secure handled by Stripe). Every call that creates or
 * changes money carries an idempotency key. The booking is confirmed only from Stripe's own answer — the webhook,
 * or our server retrieving the intent — never from the browser.
 */
class StripeGateway implements PaymentGateway
{
    public function __construct(
        private StripeClient $stripe,
        private ?string $publishableKey,
        private ?string $webhookSecret,
        private string $currency = 'usd',
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function publishableKey(): ?string
    {
        return $this->publishableKey;
    }

    public function createIntent(Payment $payment, Booking $booking): Intent
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $payment->amount_cents,
            'currency' => $this->currency,
            'automatic_payment_methods' => ['enabled' => true],
            'description' => "Booking {$booking->reference}",
            'receipt_email' => $booking->contact_email,
            'metadata' => ['booking' => $booking->ulid, 'reference' => $booking->reference, 'payment' => $payment->ulid],
        ], ['idempotency_key' => $payment->idempotency_key]);

        return $this->toIntent($intent);
    }

    public function updateAmount(Payment $payment): Intent
    {
        $intent = $this->stripe->paymentIntents->update($payment->provider_intent_id, [
            'amount' => $payment->amount_cents,
        ], ['idempotency_key' => $payment->idempotency_key.'-'.$payment->amount_cents]);

        return $this->toIntent($intent);
    }

    public function retrieve(Payment $payment): Intent
    {
        return $this->toIntent($this->stripe->paymentIntents->retrieve($payment->provider_intent_id, ['expand' => ['latest_charge']]));
    }

    public function cancel(Payment $payment): void
    {
        try {
            $this->stripe->paymentIntents->cancel($payment->provider_intent_id);
        } catch (ApiErrorException) {
            // Already succeeded, processing or cancelled: nothing to cancel.
        }
    }

    public function parseWebhook(string $payload, ?string $signature): ?ProviderEvent
    {
        if ($signature === null || $this->webhookSecret === null || $this->webhookSecret === '') {
            return null;
        }
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return null;
        }
        $object = $event->data->object ?? null;

        return new ProviderEvent($event->id, $event->type, $object instanceof PaymentIntent ? $object->id : ($object->payment_intent ?? null), $event->toArray());
    }

    private function toIntent(PaymentIntent $intent): Intent
    {
        $charge = is_object($intent->latest_charge) ? $intent->latest_charge : null;
        $card = $charge?->payment_method_details?->card ?? null;

        return new Intent(
            id: $intent->id,
            status: PaymentIntentStatus::fromStripe($intent->status),
            amountCents: (int) $intent->amount,
            clientSecret: $intent->client_secret,
            chargeId: $charge?->id ?? (is_string($intent->latest_charge) ? $intent->latest_charge : null),
            cardBrand: $card?->brand,
            cardLast4: $card?->last4,
            failureCode: $intent->last_payment_error?->code ?? null,
            failureMessage: $intent->last_payment_error?->message ?? null,
        );
    }
}
