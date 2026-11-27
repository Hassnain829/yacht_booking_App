<?php

namespace App\Services\Payments;

use App\Models\Dispute;
use App\Models\Payment;
use App\Notifications\DisputeOpened;
use App\Services\RefundService;
use Carbon\CarbonImmutable;

/**
 * Chargebacks (PLAN 5.4): Stripe's `charge.dispute.*` webhooks are kept as Dispute records on the booking, and the
 * business is emailed when one opens. The answer (evidence) is sent in Stripe's dashboard.
 */
class Disputes
{
    public function __construct(private PaymentGateway $gateway, private RefundService $refunds) {}

    /** @param  array<string, mixed>  $object  Stripe's Dispute object */
    public function sync(array $object): ?Dispute
    {
        $id = is_string($object['id'] ?? null) ? $object['id'] : null;
        if ($id === null) {
            return null;
        }
        $intent = is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : null;
        $charge = is_string($object['charge'] ?? null) ? $object['charge'] : null;
        $payment = ($intent ? Payment::query()->where('provider_intent_id', $intent)->first() : null)
            ?? ($charge ? Payment::query()->where('provider_charge_id', $charge)->first() : null);
        $due = $object['evidence_details']['due_by'] ?? null;
        $status = mb_substr((string) ($object['status'] ?? 'needs_response'), 0, 40);

        $dispute = Dispute::query()->where('provider_dispute_id', $id)->first() ?? new Dispute;
        $isNew = ! $dispute->exists;
        $dispute->forceFill([
            'provider' => $this->gateway->name(),
            'provider_dispute_id' => $id,
            'payment_id' => $payment?->getKey(),
            'booking_id' => $payment?->booking_id,
            'amount_cents' => (int) ($object['amount'] ?? 0),
            'reason' => is_string($object['reason'] ?? null) ? mb_substr($object['reason'], 0, 60) : null,
            'status' => $status,
            'evidence_due_by' => is_numeric($due) ? CarbonImmutable::createFromTimestamp((int) $due) : null,
            'closed_at' => in_array($status, Dispute::CLOSED, true) ? ($dispute->closed_at ?? now()) : null,
        ])->save();

        if ($isNew) {
            $this->refunds->notifyBusiness(new DisputeOpened($dispute));
        }

        return $dispute;
    }
}
