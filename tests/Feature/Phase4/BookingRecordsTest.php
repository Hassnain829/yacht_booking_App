<?php

namespace Tests\Feature\Phase4;

use App\Enums\BookingItemType;
use App\Enums\BookingStatus;
use App\Enums\Payee;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Captain;
use App\Models\Experience;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4.2 — the booking tables: references, which bookings take a boat's time, and bookings as records that
 * keep boats, experiences, add-ons and captains from being deleted for good (PLAN §8.5).
 */
class BookingRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_references_are_short_unique_and_easy_to_read_out(): void
    {
        $references = collect(range(1, 40))->map(fn () => Booking::factory()->create()->reference);

        $this->assertSame(40, $references->unique()->count());
        $references->each(fn (string $reference) => $this->assertMatchesRegularExpression('/^CYB-[A-HJKMNP-Z2-9]{6}$/', $reference));
    }

    public function test_which_bookings_take_the_boats_time(): void
    {
        CarbonImmutable::setTestNow('2027-05-03 15:00:00');
        $boat = Boat::factory()->create();

        $taking = [
            Booking::factory()->for($boat)->create(), // confirmed
            Booking::factory()->for($boat)->hold()->create(),
            Booking::factory()->for($boat)->create(['status' => BookingStatus::Completed]),
            Booking::factory()->for($boat)->create(['status' => BookingStatus::NoShow]),
        ];
        $free = [
            Booking::factory()->for($boat)->hold(CarbonImmutable::now()->subSecond())->create(),
            Booking::factory()->for($boat)->cancelled()->create(),
            Booking::factory()->for($boat)->create(['status' => BookingStatus::Expired]),
        ];

        $this->assertEqualsCanonicalizing(collect($taking)->pluck('id')->all(), $boat->bookings()->takingTime()->pluck('id')->all());
        foreach ($taking as $booking) {
            $this->assertTrue($booking->takesTime(), $booking->status->value);
        }
        foreach ($free as $booking) {
            $this->assertFalse($booking->takesTime(), $booking->status->value);
        }
    }

    public function test_bookings_keep_their_records_and_hide_numeric_ids(): void
    {
        $boat = Boat::factory()->create();
        $experience = Experience::factory()->create();
        $addon = Addon::factory()->create();
        $captain = Captain::factory()->create();
        $this->assertSame([[], [], [], []], [$boat->permanentDeleteBlockers(), $experience->permanentDeleteBlockers(), $addon->permanentDeleteBlockers(), $captain->permanentDeleteBlockers()]);

        $booking = Booking::factory()->for($boat)->create(['experience_id' => $experience->id, 'captain_id' => $captain->id]);
        $item = new BookingItem;
        $item->forceFill(['booking_id' => $booking->id, 'type' => BookingItemType::Addon, 'addon_id' => $addon->id, 'label' => $addon->name, 'quantity' => 1, 'unit_amount_cents' => 2500, 'amount_cents' => 2500, 'payee' => Payee::Platform])->save();

        $this->assertSame(['1 booking belongs to this boat and is kept for the records.'], $boat->permanentDeleteBlockers());
        $this->assertStringContainsString('1 booking is for this experience', $experience->permanentDeleteBlockers()[0]);
        $this->assertStringContainsString('1 booking line is for this add-on', $addon->permanentDeleteBlockers()[0]);
        $this->assertStringContainsString('1 trip has this captain', $captain->permanentDeleteBlockers()[0]);

        $json = $booking->fresh()->toArray();
        foreach (['id', 'boat_id', 'customer_id', 'experience_id', 'captain_id'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $json);
        }
        $this->assertArrayNotHasKey('addon_id', $item->fresh()->toArray());
    }

    public function test_a_boat_with_an_upcoming_trip_cannot_go_to_the_trash(): void
    {
        $boat = Boat::factory()->create();
        Booking::factory()->for($boat)->at(CarbonImmutable::now()->subDays(10))->create(); // a past trip: fine
        $this->assertSame([], $boat->trashBlockers());

        $upcoming = Booking::factory()->for($boat)->hold()->create();
        $this->assertStringContainsString('1 upcoming trip is booked on this boat', $boat->trashBlockers()[0]);

        $upcoming->forceFill(['status' => BookingStatus::Cancelled])->save();
        $this->assertSame([], $boat->trashBlockers());
    }
}
