<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the business: a guest's bank opened a dispute (chargeback) on a payment (PLAN 5.4). It is answered in Stripe. */
class DisputeOpened extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Dispute $dispute) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dispute = $this->dispute->loadMissing('booking');
        $due = $dispute->evidence_due_by?->setTimezone(config('app.display_timezone'))->format('M j, Y g:i A');

        $mail = (new MailMessage)
            ->subject('Payment disputed'.($dispute->booking ? ": {$dispute->booking->reference}" : ''))
            ->line('A card payment of '.Money::format($dispute->amount_cents, 'always').' was disputed by the guest\'s bank ('.str_replace('_', ' ', (string) $dispute->reason).').')
            ->line($due ? "Send your evidence in the Stripe dashboard before {$due} (Chicago time)." : 'Send your evidence in the Stripe dashboard.');

        return $dispute->booking ? $mail->action('Open the booking', route('admin.bookings.show', $dispute->booking->reference)) : $mail;
    }
}
