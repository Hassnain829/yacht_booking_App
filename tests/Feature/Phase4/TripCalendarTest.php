<?php

namespace Tests\Feature\Phase4;

use App\Models\Booking;
use App\Models\Experience;
use App\Support\Calendar\TripCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 4.6 — the trip as an .ics file (RFC 5545): UTC times, escaped text, lines folded at 75 octets, CRLF. */
class TripCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_booking_becomes_a_valid_calendar_event(): void
    {
        $experience = Experience::factory()->create(['name' => 'Sunset; Skyline, Cruise — a very long experience name that goes on and on past the line limit']);
        $booking = Booking::factory()->at(CarbonImmutable::parse('2027-06-04 18:30', 'America/Chicago')->utc(), 3)->create(['experience_id' => $experience->id, 'guests' => 1]);

        $ics = TripCalendar::ics($booking->fresh(), CarbonImmutable::parse('2027-05-01 12:00:00', 'UTC'));
        $lines = explode("\r\n", rtrim($ics, "\r\n"));

        $this->assertSame(['BEGIN:VCALENDAR', 'VERSION:2.0'], array_slice($lines, 0, 2));
        $this->assertContains('DTSTART:20270604T233000Z', $lines);
        $this->assertContains('DTEND:20270605T023000Z', $lines);
        $this->assertContains('DTSTAMP:20270501T120000Z', $lines);
        $this->assertStringContainsString('Sunset\; Skyline\, Cruise', str_replace("\r\n ", '', $ics), 'escaped, then unfolded');
        $this->assertStringContainsString("{$booking->reference} · 1 guest.", str_replace("\r\n ", '', $ics));
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), $line);
        }
        $this->assertSame("{$booking->reference}.ics", TripCalendar::filename($booking));
    }
}
