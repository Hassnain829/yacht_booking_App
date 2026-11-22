<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Your password was changed" (PLAN §8.1, §11). Sent after a password reset (and a password change, Phase 2.5). */
class PasswordChanged extends Notification implements ShouldQueue
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
            ->subject('Your password was changed')
            ->line('The password for your '.config('app.name').' account was just changed, and you were logged out on every device.')
            ->line($this->context->summary())
            ->line('If this was you, there is nothing else to do.')
            ->action('Reset your password', route('password.request'))
            ->line("If it wasn't you, reset your password right away and reply to this email so we can help.");
    }
}
