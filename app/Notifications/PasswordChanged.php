<?php

namespace App\Notifications;

use App\Notifications\Concerns\SkipsDeletedAccounts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Your password was changed" (PLAN §8.1, §11). Sent after a password reset and after a change on Profile & security. */
class PasswordChanged extends Notification implements ShouldQueue
{
    use Queueable, SkipsDeletedAccounts;

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
            ->line('The password for your '.config('app.name').' account was just changed, and your other devices were logged out.')
            ->line($this->context->summary())
            ->line('If this was you, there is nothing else to do.')
            ->action('Reset your password', route('password.request'))
            ->line("If it wasn't you, reset your password right away and reply to this email so we can help.");
    }
}
