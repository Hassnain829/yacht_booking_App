<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the business (Settings → business email) when a guest cancels online (PLAN 5.4): the time is free again, and
 * the refund the policy gives waits for the admin's approval on the booking page (D23).
 */
class GuestCancelledTrip extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking, public ?Refund $refund) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['boat', 'experience']);
        $start = $booking->starts_at->setTimezone(config('app.display_timezone'));
        $guest = trim($booking->contact_first_name.' '.$booking->contact_last_name);

        $mail = (new MailMessage)
            ->subject(($this->refund ? 'Refund to approve' : 'Trip cancelled').": {$booking->reference} · ".$start->format('D, M j'))
            ->line($guest.' cancelled '.($booking->experience?->name ?? 'a private charter').' on '.($booking->boat?->title ?? 'a yacht').', '.$start->format('l, F j, Y · g:i A').'. The time is free again.')
            ->line('Reason: '.($booking->cancellation_reason ?: '—'));

        if ($this->refund) {
            $mail->line('Refund by the '.($booking->policy_snapshot['name'] ?? 'booking\'s').' policy ('.$this->refund->policy_pct.'%): **'.Money::format($this->refund->amount_cents, 'always').'** (card fee of '.Money::format($this->refund->fee_cents, 'always').' kept back). Nothing is refunded until you approve it.');
        } else {
            $mail->line('No refund is due under the policy.');
        }

        return $mail->action('Open the booking', route('admin.bookings.show', $booking->reference));
    }
}
