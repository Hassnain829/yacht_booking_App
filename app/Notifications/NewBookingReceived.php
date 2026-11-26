<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The business's copy of a new paid booking (PLAN §11), sent to Settings → business email. Only what the crew needs
 * to plan the trip; the guest's phone and notes are on the admin booking screen (4.8).
 */
class NewBookingReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['boat', 'experience']);
        $start = $booking->starts_at->setTimezone(config('app.display_timezone'));

        return (new MailMessage)
            ->subject("New booking {$booking->reference}: ".($booking->boat?->title ?? 'a yacht').' · '.$start->format('D, M j g:i A'))
            ->line(($booking->experience?->name ?? 'Private charter').' on '.($booking->boat?->title ?? 'a yacht').', '.$start->format('l, F j, Y · g:i A')." for {$booking->hours} ".($booking->hours === 1 ? 'hour' : 'hours').", {$booking->guests} ".($booking->guests === 1 ? 'guest' : 'guests').'.')
            ->line('Paid in full: '.Money::format($booking->amount_paid_cents).'.')
            ->line('Lead guest: '.trim($booking->contact_first_name.' '.$booking->contact_last_name).'.');
    }
}
