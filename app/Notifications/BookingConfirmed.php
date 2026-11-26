<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\Calendar\TripCalendar;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You're booked" (PLAN 4.6, §11): sent to the booking's contact email once the payment came in, with the trip as
 * an .ics attachment. Queued; the plain-text part comes with Laravel's mail layout.
 */
class BookingConfirmed extends Notification implements ShouldQueue
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
        $booking = $this->booking->loadMissing(['boat.harbor', 'experience', 'items']);
        $tz = config('app.display_timezone');
        $start = $booking->starts_at->setTimezone($tz);
        $end = $booking->ends_at->setTimezone($tz);
        $harbor = $booking->boat?->harbor;

        $mail = (new MailMessage)
            ->subject("You're booked: {$booking->reference} · ".$start->format('D, M j'))
            ->greeting("You're booked, {$booking->contact_first_name}!")
            ->line(($booking->experience?->name ?? 'Your private charter').' on '.($booking->boat?->title ?? 'your yacht').'.')
            ->line('**When:** '.$start->format('l, F j, Y').' · '.$start->format('g:i A').' – '.$end->format('g:i A').' (Chicago time)')
            ->line('**Where:** '.($harbor ? $harbor->name.($harbor->address ? ', '.$harbor->address : '') : 'Chicago, IL'))
            ->line("**Guests:** {$booking->guests} · **Booking reference:** {$booking->reference}")
            ->line('**Paid in full:** '.Money::format($booking->amount_paid_cents))
            ->action('View your booking', route('bookings.confirmation', $booking))
            ->line('Please arrive 20 minutes before departure. Your captain will text you the day before your trip.')
            ->line('The trip is attached as a calendar file.');

        return $mail->attachData(TripCalendar::ics($booking), TripCalendar::filename($booking), ['mime' => 'text/calendar']);
    }
}
