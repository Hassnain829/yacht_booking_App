<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your account has been deleted" — sent to the address the account had, which is gone from the
 * database by the time the queue sends this (it is passed in, see UserAccountService::deleteAccount).
 */
class AccountDeleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SecurityContext $context) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account has been deleted')
            ->line('Your '.config('app.name').' account has been deleted, as you asked. Your profile and login details are gone.')
            ->line('Records of past bookings and payments are kept for as long as the law requires.')
            ->line($this->context->summary())
            ->line("If you didn't do this, reply to this email right away.");
    }
}
