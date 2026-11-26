<?php

namespace App\Http\Resources\Checkout;

use App\Http\Resources\ApiResource;
use App\Models\Payment;
use App\Support\Payments\Intent;
use Illuminate\Http\Request;

/**
 * What the browser needs to take the payment (POST /checkout/{booking}/pay): the provider, Stripe's client secret
 * for this intent (never stored on our side), the amount being charged (the booking's total, D22) and where to go
 * afterwards. The fake gateway gets its test card endpoint instead of a secret.
 */
class PaymentStartResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource['payment'];
        /** @var Intent $intent */
        $intent = $this->resource['intent'];
        $booking = $payment->booking;

        return [
            'payment' => $payment->ulid,
            'provider' => $payment->provider,
            'client_secret' => $payment->provider === 'stripe' ? $intent->clientSecret : null,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'return_url' => route('checkout.complete', $booking),
            'test_card_url' => $payment->provider === 'fake' ? route('checkout.test-card', $booking) : null,
        ];
    }
}
