<?php

namespace App\Services;

use App\Enums\BookingItemType;
use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Support\Pricing\Quote;
use App\Support\Pricing\QuoteLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one way a booking's status changes (PLAN §7.3, CLAUDE.md): every change writes its history line. Also puts
 * a QuoteService answer on a booking (money, lines, snapshot), so the booking always matches what was priced, and
 * holds the admin's booking actions (4.8): the status changes allowed now and the notes, both audit-logged.
 */
class BookingService
{
    public function __construct(private AuditService $audit, private PaymentGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $attributes  set together with the status (e.g. confirmed_at)
     */
    public function transition(Booking $booking, BookingStatus $to, ?User $actor = null, ?string $note = null, array $attributes = []): Booking
    {
        $from = $booking->exists ? $booking->getOriginal('status') : null;
        $booking->forceFill(['status' => $to] + $attributes)->save();

        (new BookingStatusHistory)->forceFill([
            'booking_id' => $booking->getKey(),
            'from_status' => $from instanceof BookingStatus ? $from->value : $from,
            'to_status' => $to->value,
            'actor_id' => $actor?->getKey(),
            'note' => $note === null ? null : mb_substr($note, 0, 500),
            'created_at' => now(),
        ])->save();

        return $booking;
    }

    /**
     * What the admin may do with a booking's status now (4.8): let a hold go, or record how a trip went once it
     * started / ended. Cancelling (with its refund) comes with Phase 5.
     *
     * @return array<string, string> status value => what the choice says
     */
    public function adminTargets(Booking $booking, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return match (true) {
            $booking->status === BookingStatus::Hold && $booking->isPayable($now) => [BookingStatus::Expired->value => 'Let the hold go (the time becomes free)'],
            $booking->status === BookingStatus::Confirmed => array_filter([
                BookingStatus::Completed->value => $booking->ends_at->lessThanOrEqualTo($now) ? 'Completed — the trip took place' : null,
                BookingStatus::NoShow->value => $booking->starts_at->lessThanOrEqualTo($now) ? 'No-show — the guest didn\'t come' : null,
            ]),
            default => [],
        };
    }

    /**
     * An admin status change (a reason + the password, SensitiveActionRequest): only one allowed now, with its
     * history line and an audit-log entry. Letting a hold go also cancels its open payment.
     *
     * @throws ValidationException on `status` (the "sensitive" bag) when the change isn't allowed now
     */
    public function changeStatusByAdmin(Booking $booking, BookingStatus $to, User $admin, string $reason): Booking
    {
        $booking = DB::transaction(function () use ($booking, $to, $admin, $reason) {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if (! array_key_exists($to->value, $this->adminTargets($booking))) {
                throw ValidationException::withMessages(['status' => "This booking can't be marked {$to->label()} now."])->errorBag('sensitive');
            }
            $from = $booking->status;
            $this->transition($booking, $to, $admin, $reason, match ($to) {
                BookingStatus::Completed => ['completed_at' => now()],
                BookingStatus::Expired => ['hold_expires_at' => now()],
                default => [],
            });
            $this->audit->record('booking.status_changed', $booking, ['status' => $from->value], ['status' => $to->value], $reason, $admin);

            return $booking;
        });

        if ($to === BookingStatus::Expired) {
            $this->cancelOpenPayments($booking);
        }

        return $booking;
    }

    /**
     * The admin's notes (4.8): the internal note and the note for the captain. Only what changed is logged.
     *
     * @param  array{admin_notes: ?string, captain_notes: ?string}  $notes
     */
    public function updateNotes(Booking $booking, array $notes, User $admin): void
    {
        $booking->forceFill($notes);
        [$before, $after] = $this->audit->changes($booking);
        if ($after === []) {
            return;
        }
        $booking->save();
        $this->audit->record('booking.notes_updated', $booking, $before, $after, actor: $admin);
    }

    /** A booking that can't be paid any more: its open payment forms are cancelled with the provider. */
    public function cancelOpenPayments(Booking $booking): void
    {
        foreach ($booking->payments()->get() as $payment) {
            if ($payment->status->isOpen() && $payment->status !== PaymentIntentStatus::Processing) {
                $this->gateway->cancel($payment);
                $payment->forceFill(['status' => PaymentIntentStatus::Canceled])->save();
            }
        }
    }

    /** The quote's money, its lines (replacing the old ones) and its pricing snapshot. The booking must be saved. */
    public function applyQuote(Booking $booking, Quote $quote): void
    {
        $booking->forceFill([
            'hours' => $quote->hours,
            'guests' => $quote->guests,
            'subtotal_cents' => $quote->subtotalCents,
            'discount_cents' => $quote->discountCents,
            'tax_cents' => $quote->taxCents,
            'gratuity_bp' => $quote->gratuityPct * 100,
            'gratuity_cents' => $quote->gratuityCents,
            'total_cents' => $quote->totalCents,
            'pricing_snapshot' => $quote->pricingSnapshot,
        ])->save();

        $booking->items()->whereNull('booking_extension_id')->delete();
        foreach ($quote->items() as $sort => $line) {
            /** @var QuoteLine $line */
            $booking->items()->forceCreate([
                'type' => $line->type,
                'addon_id' => $line->type === BookingItemType::Addon ? $line->addonId : null,
                'label' => mb_substr($line->label, 0, 120),
                'description' => mb_substr($line->description, 0, 255),
                'quantity' => $line->quantity,
                'unit_amount_cents' => $line->unitAmountCents,
                'amount_cents' => $line->amountCents,
                'payee' => $line->payee,
                'sort' => $sort,
            ]);
        }
        $booking->unsetRelation('items');
    }
}
