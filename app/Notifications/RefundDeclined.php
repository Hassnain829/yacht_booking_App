<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The team declined the refund a guest's cancellation asked for (PLAN 5.4, D23), with the team's reason. */
class RefundDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Refund $refund) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->refund->loadMissing('booking')->booking;
        $business = config('site.business');

        return (new MailMessage)
            ->subject("About your refund: {$booking->reference}")
            ->greeting("Hi {$booking->contact_first_name},")
            ->line("We reviewed the refund for booking {$booking->reference} and could not approve it.")
            ->line('Our note: '.$this->refund->decision_note)
            ->line('Questions? Call '.($business['phone'] ?? 'us').' or reply to this email.')
            ->action('View your trip', route('account.trips.show', $booking));
    }
}
