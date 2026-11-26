<?php

namespace App\Http\Controllers;

use App\Actions\Booking\ConfirmPaidBooking;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stripe's webhook (POST /stripe/webhook, PLAN §8.4): no session or CSRF token — the signature (Stripe-Signature,
 * the endpoint's secret) proves it is Stripe; anything else is a 400. Each event is stored once (unique id) before
 * it is handled, so a replay does nothing; one that failed is handled again when Stripe retries. Payment intent
 * events are answered with the intent as Stripe reports it now, through ConfirmPaidBooking.
 */
class StripeWebhookController extends Controller
{
    private const HANDLED = ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.processing', 'payment_intent.canceled', 'payment_intent.requires_action'];

    public function __invoke(Request $request, PaymentGateway $gateway, ConfirmPaidBooking $confirm): Response
    {
        $event = $gateway->parseWebhook($request->getContent(), $request->header('Stripe-Signature'));
        if ($event === null) {
            return response('Invalid signature', 400);
        }

        $stored = WebhookEvent::query()->where('event_id', $event->id)->first();
        if ($stored?->processed_at !== null) {
            return response('Already handled', 200);
        }
        $stored ??= tap((new WebhookEvent)->forceFill([
            'provider' => $gateway->name(),
            'event_id' => $event->id,
            'type' => mb_substr($event->type, 0, 80),
            'payload' => $event->payload,
            'received_at' => now(),
        ]))->save();

        try {
            if (in_array($event->type, self::HANDLED, true) && $event->intentId !== null) {
                $payment = Payment::query()->where('provider_intent_id', $event->intentId)->first();
                if ($payment !== null) {
                    $confirm->handle($payment, $gateway->retrieve($payment));
                }
            }
            $stored->forceFill(['processed_at' => now(), 'error' => null])->save();
        } catch (Throwable $exception) {
            $stored->forceFill(['error' => mb_substr($exception->getMessage(), 0, 255)])->save();
            Log::error('Stripe webhook failed', ['event' => $event->id, 'type' => $event->type, 'error' => $exception->getMessage()]);

            return response('Failed, retry', 500);
        }

        return response('OK', 200);
    }
}
