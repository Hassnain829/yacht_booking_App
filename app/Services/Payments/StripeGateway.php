<?php

namespace App\Services\Payments;

use App\Enums\PaymentIntentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Support\Payments\Intent;
use App\Support\Payments\ProviderEvent;
use App\Support\Payments\ProviderRefund;
use App\Support\Payments\SavedCard;
use App\Support\Payments\SetupStart;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe (PLAN §8.4): a PaymentIntent for the full amount, confirmed in the browser with the Payment Element
 * (card data never reaches our server, PCI SAQ-A; 3-D Secure handled by Stripe). Every call that creates or
 * changes money carries an idempotency key. The booking is confirmed only from Stripe's own answer — the webhook,
 * or our server retrieving the intent — never from the browser.
 *
 * Saved cards (5.3): each guest gets a Stripe Customer; a card is saved by paying with "Save this card"
 * (setup_future_usage) or from the account (a SetupIntent). Refunds (5.4) go against the PaymentIntent.
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

    public function createCustomer(User $user): string
    {
        return $this->stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user' => $user->ulid],
        ], ['idempotency_key' => 'customer-'.$user->ulid])->id;
    }

    public function createIntent(Payment $payment, Booking $booking, ?string $customerId): Intent
    {
        $intent = $this->stripe->paymentIntents->create(array_filter([
            'amount' => $payment->amount_cents,
            'currency' => $this->currency,
            'automatic_payment_methods' => ['enabled' => true],
            'customer' => $customerId,
            'payment_method' => $payment->provider_payment_method_id,
            'setup_future_usage' => $payment->save_card && $customerId !== null ? 'off_session' : null,
            'description' => "Booking {$booking->reference}",
            'receipt_email' => $booking->contact_email,
            'metadata' => ['booking' => $booking->ulid, 'reference' => $booking->reference, 'payment' => $payment->ulid],
        ], fn ($value) => $value !== null), ['idempotency_key' => $payment->idempotency_key]);

        return $this->toIntent($intent);
    }

    public function updateIntent(Payment $payment, ?string $customerId): Intent
    {
        $params = array_filter([
            'amount' => $payment->amount_cents,
            'customer' => $customerId,
            'payment_method' => $payment->provider_payment_method_id,
        ], fn ($value) => $value !== null);
        $params['setup_future_usage'] = $payment->save_card && $customerId !== null && $payment->provider_payment_method_id === null ? 'off_session' : ''; // '' unsets it
        $intent = $this->stripe->paymentIntents->update($payment->provider_intent_id, $params, [
            'idempotency_key' => $payment->idempotency_key.'-'.substr(hash('sha256', (string) json_encode($params)), 0, 16),
        ]);

        return $this->toIntent($intent);
    }

    public function retrieve(Payment $payment): Intent
    {
        return $this->toIntent($this->stripe->paymentIntents->retrieve($payment->provider_intent_id, ['expand' => ['latest_charge.balance_transaction']]));
    }

    public function cancel(Payment $payment): void
    {
        try {
            $this->stripe->paymentIntents->cancel($payment->provider_intent_id);
        } catch (ApiErrorException) {
            // Already succeeded, processing or cancelled: nothing to cancel.
        }
    }

    public function refund(Payment $payment, int $amountCents, string $idempotencyKey, array $metadata = []): ProviderRefund
    {
        $refund = $this->stripe->refunds->create([
            'payment_intent' => $payment->provider_intent_id,
            'amount' => $amountCents,
            'reason' => 'requested_by_customer',
            'metadata' => $metadata,
        ], ['idempotency_key' => $idempotencyKey]);

        return new ProviderRefund($refund->id, RefundStatus::fromStripe($refund->status), $refund->failure_reason ?? null);
    }

    public function createSetup(string $customerId): SetupStart
    {
        $setup = $this->stripe->setupIntents->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
        ]);

        return new SetupStart($setup->id, $setup->client_secret);
    }

    public function retrieveSetup(string $setupId, string $customerId): ?SavedCard
    {
        try {
            $setup = $this->stripe->setupIntents->retrieve($setupId, ['expand' => ['payment_method']]);
        } catch (ApiErrorException) {
            return null;
        }
        $customer = is_object($setup->customer) ? $setup->customer->id : $setup->customer;
        $method = is_object($setup->payment_method) ? $setup->payment_method : null;
        if ($setup->status !== 'succeeded' || $customer !== $customerId || $method?->card === null) {
            return null;
        }

        return new SavedCard($method->id, (string) $method->card->brand, (string) $method->card->last4, (int) $method->card->exp_month, (int) $method->card->exp_year);
    }

    public function detach(string $paymentMethodId): void
    {
        try {
            $this->stripe->paymentMethods->detach($paymentMethodId);
        } catch (ApiErrorException) {
            // Already detached (or removed in Stripe's dashboard): nothing to do.
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
        $intentId = $object instanceof PaymentIntent ? $object->id : ($object->payment_intent ?? null);

        return new ProviderEvent($event->id, $event->type, is_string($intentId) ? $intentId : null, $event->toArray(), $object instanceof StripeObject ? $object->toArray() : []);
    }

    private function toIntent(PaymentIntent $intent): Intent
    {
        $charge = is_object($intent->latest_charge) ? $intent->latest_charge : null;
        $card = $charge?->payment_method_details?->card ?? null;
        $balance = is_object($charge?->balance_transaction ?? null) ? $charge->balance_transaction : null;
        $method = is_object($intent->payment_method) ? $intent->payment_method->id : $intent->payment_method;

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
            paymentMethodId: is_string($method) ? $method : null,
            cardExpMonth: isset($card->exp_month) ? (int) $card->exp_month : null,
            cardExpYear: isset($card->exp_year) ? (int) $card->exp_year : null,
            feeCents: isset($balance->fee) ? (int) $balance->fee : null,
        );
    }
}
