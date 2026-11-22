<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Two-factor login was turned on / off" (PLAN §11), including a reset by our team. */
class TwoFactorChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public bool $enabled,
        public SecurityContext $context,
        public bool $byOurTeam = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Two-factor login was turned '.($this->enabled ? 'on' : 'off'));

        if ($this->enabled) {
            return $mail
                ->line('Two-factor login is now on for your '.config('app.name').' account. Each login will ask for a code from your authenticator app.')
                ->line($this->context->summary())
                ->line('Keep your recovery codes somewhere safe: they are the only way in if you lose your phone.')
                ->line("If this wasn't you, reply to this email right away.");
        }

        return $mail
            ->line($this->byOurTeam
                ? 'Our team turned off two-factor login for your '.config('app.name').' account, as you asked, and logged you out on every device.'
                : 'Two-factor login was turned off for your '.config('app.name').' account.')
            ->line($this->context->summary())
            ->action('Turn it back on', route('account.two-factor'))
            ->line("If this wasn't you, change your password right away and reply to this email.");
    }
}
