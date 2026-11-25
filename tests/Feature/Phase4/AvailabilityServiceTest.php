<?php

namespace Tests\Feature\Phase4;

use App\Enums\BlockType;
use App\Enums\BoatStatus;
use App\Exceptions\AvailabilityException;
use App\Models\Boat;
use App\Models\BoatBlock;
use App\Models\Booking;
use App\Models\Experience;
use App\Services\AvailabilityService;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4.2 — AvailabilityService (PLAN §7.2, Boatsetter-style start times). The boat departs every half hour
 * 8 AM – 6 PM Monday to Saturday (Chicago time), closed on Sundays, 1 hour's notice, a 30-minute buffer.
 * "Now" is Monday 2027-05-03, 10:00 AM in Chicago (CDT, UTC−5).
 */
class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $availability;

    private Boat $boat;

    private Experience $sunset;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2027-05-03 15:00:00');
        $this->availability = app(AvailabilityService::class);
        $this->boat = Boat::factory()->create(['max_guests' => 12, 'min_hours' => 2, 'durations' => [2, 3, 4], 'advance_notice_minutes' => 60, 'buffer_minutes' => 30, 'allow_multiple_bookings' => true]);
        foreach (range(1, 6) as $day) {
            $this->boat->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        $this->boat->availabilityRules()->create(['weekday' => 0, 'is_open' => false, 'start_times' => StartTimes::defaults()]);
        $this->sunset = Experience::factory()->create(['name' => 'Sunset Cruise', 'time_slots' => ['17:30', '18:00', '18:30'], 'allowed_weekdays' => null]);
        $this->boat->experiences()->attach($this->sunset);
    }

    private function boat(): Boat
    {
        return $this->boat->fresh();
    }

    /** A trip on the boat starting at a Chicago time. */
    private function trip(string $date, string $time, int $hours = 3): BookingFactory
    {
        return Booking::factory()->for($this->boat)->at(CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$time}", 'America/Chicago')->utc(), $hours);
    }

    /** @return array<string, ?string> time => null (free) or why it is taken */
    private function states(string $date, int $hours = 3, ?Experience $experience = null, int $guests = 4): array
    {
        return collect($this->availability->slots($this->boat(), $date, $hours, $experience, $guests))
            ->mapWithKeys(fn (array $slot) => [$slot['time'] => $slot['reason']])->all();
    }

    public function test_slots_are_the_boats_start_times_that_day_in_chicago_time(): void
    {
        $slots = $this->availability->slots($this->boat(), '2027-05-10', 3); // a Monday

        $this->assertCount(21, $slots);
        $this->assertSame(['08:00', '8:00 AM', '2027-05-10T13:00:00Z', true, null], [$slots[0]['time'], $slots[0]['label'], $slots[0]['starts_at']->toIso8601ZuluString(), $slots[0]['available'], $slots[0]['reason']]);
        $this->assertSame('18:00', end($slots)['time']);
        $this->assertSame([], $this->availability->slots($this->boat(), '2027-05-09', 3), 'Sunday is closed');

        $this->boat->availabilityRules()->where('weekday', 2)->delete();
        $this->assertSame([], $this->availability->slots($this->boat(), '2027-05-11', 3), 'no row for a weekday = closed');
    }

    public function test_daylight_saving_moves_the_utc_time_not_the_local_start(): void
    {
        $before = $this->availability->slots($this->boat(), '2027-03-13', 2)[0]; // Saturday, CST (UTC−6)
        $after = $this->availability->slots($this->boat(), '2027-03-15', 2)[0];  // Monday after the change, CDT (UTC−5)

        $this->assertSame(['08:00', '2027-03-13T14:00:00Z'], [$before['time'], $before['starts_at']->toIso8601ZuluString()]);
        $this->assertSame(['08:00', '2027-03-15T13:00:00Z'], [$after['time'], $after['starts_at']->toIso8601ZuluString()]);
    }

    public function test_an_experience_keeps_only_the_times_both_offer_and_its_days_and_season(): void
    {
        $this->assertSame(['17:30' => null, '18:00' => null], $this->states('2027-05-10', 3, $this->sunset), '18:30 is not a boat start time');

        $this->sunset->forceFill(['allowed_weekdays' => [3, 6]])->save();
        $this->assertSame([], $this->states('2027-05-10', 3, $this->sunset), 'Wednesdays and Saturdays only');
        $this->assertCount(2, $this->states('2027-05-12', 3, $this->sunset));
        $problem = $this->availability->problem($this->boat(), CarbonImmutable::parse('2027-05-10 22:30:00'), 3, $this->sunset);
        $this->assertSame(['time', "Sunset Cruise doesn't run on Mondays."], [$problem['field'], $problem['message']]);

        $this->sunset->forceFill(['allowed_weekdays' => null, 'season_starts_on' => '2027-05-15', 'season_ends_on' => '2027-09-30'])->save();
        $this->assertSame([], $this->states('2027-05-14', 3, $this->sunset));
        $this->assertCount(2, $this->states('2027-05-15', 3, $this->sunset));
        $this->assertSame([], $this->states('2027-10-01', 3, $this->sunset));

        $this->boat->experiences()->updateExistingPivot($this->sunset->id, ['is_active' => false]);
        $this->assertSame([], $this->states('2027-05-15', 3, $this->sunset), 'the boat stopped offering it');
    }

    public function test_a_time_outside_the_experiences_window_says_when_it_departs(): void
    {
        $at = fn (string $time) => CarbonImmutable::createFromFormat('Y-m-d H:i', "2027-05-10 {$time}", 'America/Chicago')->utc();

        $morning = $this->availability->problem($this->boat(), $at('10:00'), 4, $this->sunset);
        $this->assertSame(['time', "Sunset Cruise isn't available at 10:00 AM: it departs 5:30 PM – 6:30 PM. Pick a start time inside that window, or just book the boat."], [$morning['field'], $morning['message']]);
        $this->assertNull($this->availability->problem($this->boat(), $at('10:00'), 4), 'just the boat: fine at 10:00 AM');
        $this->assertNull($this->availability->problem($this->boat(), $at('17:30'), 4, $this->sunset), 'any of the boat\'s lengths: no length of its own');

        // The boat departs 8:00 AM – 6:00 PM: a fireworks window at night never meets it.
        $fireworks = Experience::factory()->create(['name' => 'Fireworks', 'time_slots' => ['20:00', '20:30', '21:00']]);
        $this->boat->experiences()->attach($fireworks);
        $this->assertSame([], $this->states('2027-05-10', 3, $fireworks));
        $night = $this->availability->problem($this->boat(), $at('18:00'), 3, $fireworks);
        $this->assertSame(['time', "Fireworks departs 8:00 PM – 9:00 PM, and the boat doesn't depart then on Monday, May 10. Pick another day or just book the boat."], [$night['field'], $night['message']]);
    }

    public function test_advance_notice_and_the_past(): void
    {
        $today = $this->states('2027-05-03', 2); // now: 10:00 AM, 1 hour's notice → 11:00 AM at the earliest
        $this->assertSame(['notice', 'notice', 'notice', 'notice', 'notice', 'notice', null], array_values(array_slice($today, 0, 7)));
        $this->assertSame('11:00', array_search(null, $today, true));
        $this->assertSame(['notice'], array_values(array_unique($this->states('2027-04-30', 2))), 'a past day');

        $this->boat->forceFill(['advance_notice_minutes' => 1440])->save(); // a day: tomorrow from 10:00 AM
        $tomorrow = $this->states('2027-05-04', 2);
        $this->assertSame(['notice', null], [$tomorrow['09:30'], $tomorrow['10:00']]);
    }

    public function test_trips_take_their_time_plus_the_buffer_on_both_sides(): void
    {
        $this->trip('2027-05-10', '12:00')->create(); // 12:00–15:00, busy 11:30–15:30 with the buffer
        $states = $this->states('2027-05-10', 3);

        $this->assertNull($states['08:30'], 'back at 11:30, when the buffer starts');
        $this->assertSame('booked', $states['09:00']);
        $this->assertSame('booked', $states['15:00']);
        $this->assertNull($states['15:30'], 'the buffer ends at 15:30');
        $this->assertNull($this->states('2027-05-11', 3)['12:00'], 'other days are free');

        $this->boat->forceFill(['buffer_minutes' => 0])->save();
        $this->assertNull($this->states('2027-05-10', 3)['09:00'], 'no buffer: back at 12:00 is fine');
    }

    public function test_holds_count_until_they_run_out_and_cancelled_trips_never(): void
    {
        $hold = $this->trip('2027-05-10', '08:00', 2)->hold()->create();
        $this->trip('2027-05-10', '11:00', 2)->hold(CarbonImmutable::now()->subMinute())->create(); // ran out
        $this->trip('2027-05-10', '14:00', 2)->create(); // confirmed (paid in full, D22)
        $this->trip('2027-05-10', '16:30', 2)->cancelled()->create();
        $states = $this->states('2027-05-10', 2);

        $this->assertSame(['booked', null, 'booked', null], [$states['08:00'], $states['11:00'], $states['14:00'], $states['16:30']]);
        $this->assertNull($this->availability->problem($this->boat(), $hold->starts_at, 2, ignore: $hold), 'its own hold does not block a trip being re-checked');
    }

    public function test_blocks_of_the_boat_and_fleet_wide_blocks(): void
    {
        $chicago = fn (string $time) => CarbonImmutable::createFromFormat('Y-m-d H:i', "2027-05-10 {$time}", 'America/Chicago')->utc();
        BoatBlock::factory()->create(['boat_id' => $this->boat->id, 'starts_at' => $chicago('09:00'), 'ends_at' => $chicago('10:00'), 'type' => BlockType::Maintenance]);
        BoatBlock::factory()->create(['boat_id' => null, 'starts_at' => $chicago('16:00'), 'ends_at' => $chicago('23:00'), 'type' => BlockType::Weather]);
        $states = $this->states('2027-05-10', 2);

        $this->assertSame(['blocked', 'blocked', null, 'blocked', 'blocked'], [$states['08:00'], $states['09:30'], $states['10:00'], $states['14:30'], $states['18:00']]);
        $this->assertNull($states['14:00'], 'back at 16:00, when the blackout starts');
    }

    public function test_a_boat_that_takes_one_trip_a_day(): void
    {
        $this->boat->forceFill(['allow_multiple_bookings' => false])->save();
        $this->trip('2027-05-10', '08:00', 2)->create();

        $this->assertSame(['one_a_day'], array_values(array_unique($this->states('2027-05-10', 2))));
        $this->assertNull($this->states('2027-05-11', 2)['08:00']);
        $problem = $this->availability->problem($this->boat(), CarbonImmutable::parse('2027-05-10 20:00:00'), 2);
        $this->assertSame('This boat takes one trip a day, and that day is booked. Pick another day.', $problem['message']);
    }

    public function test_the_whole_day_is_out_when_the_boat_or_the_party_does_not_fit(): void
    {
        $this->assertSame([], $this->states('2027-05-10', 3, null, 13), 'more guests than the boat takes');
        $this->assertSame([], $this->states('2027-05-10', 5), 'a length the boat does not offer');
        $tooMany = $this->availability->problem($this->boat(), CarbonImmutable::parse('2027-05-10 13:00:00'), 3, null, 13);
        $this->assertSame(['guests', 'This boat takes 1 to 12 guests.'], [$tooMany['field'], $tooMany['message']]);
        $this->assertSame('hours', $this->availability->problem($this->boat(), CarbonImmutable::parse('2027-05-10 13:00:00'), 5)['field']);

        foreach ([['booking_enabled' => false], ['status' => BoatStatus::Draft]] as $change) {
            $this->boat->forceFill($change)->save();
            $this->assertSame([], $this->states('2027-05-10'), json_encode($change));
            $this->assertSame('boat', $this->availability->problem($this->boat(), CarbonImmutable::parse('2027-05-10 13:00:00'), 3)['field']);
            $this->boat->forceFill(['booking_enabled' => true, 'status' => BoatStatus::Published])->save();
        }
        $this->boat->delete(); // the Trash
        $this->assertSame([], $this->availability->slots(Boat::withTrashed()->find($this->boat->id), '2027-05-10', 3));
    }

    public function test_one_exact_trip_is_checked_and_a_taken_one_is_a_422(): void
    {
        $at = CarbonImmutable::createFromFormat('Y-m-d H:i', '2027-05-10 12:00', 'America/Chicago')->utc();

        $this->assertNull($this->availability->problem($this->boat(), $at, 3));
        $this->assertSame(
            ['time', 'The boat doesn\'t depart at that time. Pick one of the start times shown.'],
            array_values($this->availability->problem($this->boat(), $at->addMinutes(15), 3)),
        );

        $this->trip('2027-05-10', '12:00')->create();
        try {
            $this->availability->ensureFree($this->boat(), $at, 3);
            $this->fail('Expected an AvailabilityException.');
        } catch (AvailabilityException $exception) {
            $this->assertSame(['time' => ['That time is already booked. Pick another time.']], $exception->errors());
        }
    }

    public function test_the_calendar_marks_each_day_with_one_query_for_trips_and_one_for_blocks(): void
    {
        foreach (['08:00', '11:30', '15:00'] as $time) { // Tuesday the 11th: 3 trips leave few starts free
            $this->trip('2027-05-11', $time, 2)->create();
        }
        BoatBlock::factory()->create(['boat_id' => $this->boat->id, 'type' => BlockType::Blackout, // Wednesday the 12th: closed all day
            'starts_at' => CarbonImmutable::parse('2027-05-12 05:00:00'), 'ends_at' => CarbonImmutable::parse('2027-05-13 05:00:00')]);

        $boat = $this->boat();
        DB::enableQueryLog();
        $days = $this->availability->calendar($boat, '2027-05', 2);
        $queries = count(DB::getQueryLog());

        $this->assertCount(31, $days);
        $this->assertSame(['past', 'past', 'available', 'available', 'unavailable'], [$days['2027-05-01'], $days['2027-05-02'], $days['2027-05-03'], $days['2027-05-10'], $days['2027-05-09']]);
        $this->assertSame(['limited', 'soldout'], [$days['2027-05-11'], $days['2027-05-12']]);
        $this->assertLessThanOrEqual(5, $queries, 'rules + experiences + trips + blocks, not one per day');
    }

    public function test_busy_intervals_are_the_one_source_of_busy_time(): void
    {
        $this->trip('2027-05-10', '12:00', 3)->create();
        BoatBlock::factory()->create(['boat_id' => $this->boat->id, 'type' => BlockType::Maintenance, 'starts_at' => CarbonImmutable::parse('2027-05-10 22:00:00'), 'ends_at' => CarbonImmutable::parse('2027-05-10 23:00:00')]);

        $busy = $this->availability->busyIntervals($this->boat(), CarbonImmutable::parse('2027-05-10 00:00:00'), CarbonImmutable::parse('2027-05-11 00:00:00'));

        $this->assertSame([
            ['booking', '2027-05-10T16:30:00Z', '2027-05-10T20:30:00Z'], // 12:00–15:00 Chicago + 30 min each side
            ['block', '2027-05-10T22:00:00Z', '2027-05-10T23:00:00Z'],
        ], array_map(fn (array $i) => [$i['kind'], $i['starts_at']->toIso8601ZuluString(), $i['ends_at']->toIso8601ZuluString()], $busy));
    }
}
