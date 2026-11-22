<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * "Your email address was changed" — sent to the OLD address (PLAN §8.1), so the real owner hears about it
 * even if someone else changed it. The new address is partly hidden.
 */
class EmailChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $newEmail, public SecurityContext $context) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your email address was changed')
            ->line('The email address of your '.config('app.name').' account was changed to '.self::mask($this->newEmail).'. From now on we will write to the new address.')
            ->line($this->context->summary())
            ->line("If this wasn't you, reply to this email right away: someone may have access to your account.");
    }

    /** "n*******@example.com" */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::mask($local, '*', 1).'@'.$domain;
    }
}
