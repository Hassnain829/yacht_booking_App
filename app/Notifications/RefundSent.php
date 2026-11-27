<?php

namespace App\Notifications;

use App\Models\Refund;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Your refund is on its way" (PLAN 5.4): the team approved the guest's refund, or refunded them on its own. */
class RefundSent extends Notification implements ShouldQueue
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
        $refund = $this->refund->loadMissing(['booking', 'payment']);
        $booking = $refund->booking;
        $card = $refund->payment?->card_last4 ? ucfirst((string) $refund->payment->card_brand).' •••• '.$refund->payment->card_last4 : 'your card';

        return (new MailMessage)
            ->subject("Refund sent: {$booking->reference}")
            ->greeting("Hi {$booking->contact_first_name},")
            ->line('We have refunded **'.Money::format($refund->amount_cents, 'always')."** to {$card} for booking {$booking->reference}.")
            ->line('It can take 5–10 business days to show on your statement.')
            ->action('View your trip', route('account.trips.show', $booking));
    }
}
