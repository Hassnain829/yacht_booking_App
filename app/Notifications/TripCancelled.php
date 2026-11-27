<?php

namespace App\Notifications;

use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your trip is cancelled" (PLAN 5.4), to the booking's contact email — after the guest cancels it online or the
 * team cancels it. Says what happens to the money: a refund waiting for the team's review (the guest's own
 * cancellation, D23), a refund already on its way (the team's), or none under the policy.
 */
class TripCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking, public ?Refund $refund, public bool $byGuest) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['boat', 'experience']);
        $start = $booking->starts_at->setTimezone(config('app.display_timezone'));
        $refund = $this->refund?->fresh();
        $trip = ($booking->experience?->name ?? 'your private charter').' on '.($booking->boat?->title ?? 'your yacht').', '.$start->format('l, F j, Y · g:i A');

        $mail = (new MailMessage)
            ->subject("Trip cancelled: {$booking->reference} · ".$start->format('D, M j'))
            ->greeting("Hi {$booking->contact_first_name},")
            ->line(($this->byGuest ? 'Your cancellation is confirmed: ' : 'We had to cancel your trip: ').$trip.'.');

        if ($refund === null) {
            $mail->line('No refund is due under the cancellation policy of this booking.');
        } elseif ($refund->status === RefundStatus::Requested) {
            $mail->line('**Refund:** '.Money::format($refund->amount_cents, 'always').' is being reviewed by our team, usually within 1 business day. We will email you as soon as it is sent.');
        } else {
            $mail->line('**Refund:** '.Money::format($refund->amount_cents, 'always').' is on its way back to your card. It can take 5–10 business days to show on your statement.');
        }
        if ($refund !== null && $refund->fee_cents > 0) {
            $mail->line('The card processing fee ('.Money::format($refund->fee_cents, 'always').') is not refundable.');
        }

        return $mail
            ->line("Booking reference: {$booking->reference}")
            ->action('View your trip', route('account.trips.show', $booking));
    }
}
