<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "New login to your account" (PLAN §8.1): owners and the admin, when they log in from a browser we
 * haven't seen for their account before (never on the very first login).
 */
class NewDeviceLogin extends Notification implements ShouldQueue
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
            ->subject('New login to your '.config('app.name').' account')
            ->line("Your account was just used to log in from a device we haven't seen before:")
            ->line($this->context->summary())
            ->line('If this was you, you can ignore this email.')
            ->action('Review your devices', route('account.profile').'#sessions')
            ->line("If it wasn't you, change your password right away and log out your other devices.");
    }
}
