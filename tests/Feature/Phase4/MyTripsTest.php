<?php

namespace Tests\Feature\Phase4;

use App\Enums\BookingStatus;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\User;
use App\Services\CancellationPolicyService;
use App\Services\PublicCatalog;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBookings;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 4.7 — My trips + trip detail: the guest's own bookings (someone else's is a 404), a hold that can still
 * be paid as "Finish checkout", and the refund the booking's own policy gives (less the card fee since 5.4).
 * Cancelling online: Phase5/CancelTripTest. The problem report (Phase 7) points to the team for now.
 */
class MyTripsTest extends TestCase
{
    use MakesBookings, RefreshDatabase, SeedsPublicCatalog;

    private User $guest;

    private Boat $azure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago')); // a Monday
        $this->seedPublicCatalog();
        $this->azure = Boat::query()->where('slug', 'azure')->sole();
        $this->azure->forceFill(['allow_multiple_bookings' => true])->save();
        foreach (range(0, 6) as $day) {
            $this->azure->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        PublicCatalog::flush();
        $this->guest = User::factory()->create(['first_name' => 'Jamie']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function trip(string $start, BookingStatus $status, array $attributes = []): Booking
    {
        return Booking::factory()->for($this->azure)->at(CarbonImmutable::parse($start, 'America/Chicago')->utc(), 3)
            ->create($attributes + ['customer_id' => $this->guest->id, 'status' => $status, 'total_cents' => 150000, 'amount_paid_cents' => 150000]);
    }

    public function test_my_trips_lists_the_guests_own_trips_upcoming_first_then_past(): void
    {
        $paid = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        $hold = $this->holdTrip(User::factory()->create(), 'azure', '2027-05-20'); // someone else's
        $open = $this->holdTrip($this->guest, 'azure', '2027-05-25', '15:00');
        $this->trip('2027-04-20 12:00', BookingStatus::Completed);
        $this->trip('2027-04-10 12:00', BookingStatus::Cancelled, ['amount_refunded_cents' => 150000]);
        $this->trip('2027-05-14 12:00', BookingStatus::Hold, ['hold_expires_at' => now()->subMinute(), 'reference' => 'CYB-EXPRD2']); // ran out
        $this->trip('2027-05-15 12:00', BookingStatus::Confirmed, ['customer_id' => User::factory()->create()->id, 'reference' => 'CYB-OTHER2']);

        $html = $this->actingAs($this->guest)->get('/account/trips')->assertOk()
            ->assertSee($paid->reference)->assertSee($open->reference)->assertSee('Finish checkout')->assertSee('Awaiting payment')
            ->assertSee('Paid in full · $')->assertSee('Refunded $1,500.00')
            ->assertDontSee($hold->reference)->assertDontSee('CYB-EXPRD2')->assertDontSee('CYB-OTHER2')
            ->getContent();
        $this->assertLessThan(strpos($html, 'Past &amp; cancelled'), strpos($html, $paid->reference), 'upcoming first');
        $this->assertLessThan(strpos($html, $open->reference), strpos($html, $paid->reference), 'soonest first');
        $this->assertStringContainsString(route('checkout.show', $open), $html);
    }

    public function test_a_trip_page_shows_its_lines_the_card_and_the_refund_its_policy_gives(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        $policy = app(CancellationPolicyService::class);
        $fee = CancellationPolicyService::estimateFee($trip->total_cents); // the fake gateway reports no fee: the estimate
        $refund = $policy->preview($trip->fresh());
        $this->assertSame(100, $refund['percent'], 'Moderate: 5+ days ahead');
        $this->assertSame([$trip->total_cents, $fee, $trip->total_cents - $fee], [$refund['policy_cents'], $refund['fee_cents'], $refund['refund_cents']], 'the card fee is kept back (D23)');
        $this->assertSame([50, '2027-05-07 12:00'], [$refund['next']['percent'], $refund['next']['at']->setTimezone('America/Chicago')->format('Y-m-d H:i')]);

        $this->actingAs($this->guest)->get("/account/trips/{$trip->ulid}")->assertOk()
            ->assertSee($trip->reference)->assertSee('Wednesday, May 12, 2027')->assertSee('Yacht charter')->assertSee('Licensed USCG captain')
            ->assertSee('Paid · Visa •••• 4242')->assertSee('Moderate policy')->assertSee('100% refund under this policy')
            ->assertSee(money($fee, 'always').' card processing fee')->assertSee('After May 7, 12:00 PM: 50% refund.')
            ->assertSee(route('bookings.calendar', $trip), false)->assertSee(route('account.trips.cancel', $trip), false)
            ->assertSee('Cancel trip')->assertSee('assigning a licensed USCG captain');

        $this->travelTo(CarbonImmutable::parse('2027-05-10 12:00', 'America/Chicago')); // 2 days before
        $late = $policy->preview($trip->fresh());
        $this->assertSame([0, $trip->gratuity_cents - $fee], [$late['percent'], $late['refund_cents']], 'the gratuity always comes back, less the card fee');
    }

    public function test_someone_elses_trip_is_a_404_and_a_guest_logs_in_first(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        $hold = $this->holdTrip($this->guest, 'azure', '2027-05-20');
        $expired = $this->trip('2027-05-14 12:00', BookingStatus::Expired, ['reference' => 'CYB-EXPRD3']);

        $this->get("/account/trips/{$trip->ulid}")->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get("/account/trips/{$trip->ulid}")->assertNotFound();
        $this->actingAs($this->guest)->get('/account/trips/01JDTR1PQ7M3K8R2V5X9C4B6NZ')->assertNotFound();
        $this->get('/account/trips/1')->assertNotFound();
        $this->get('/account/trips/%3Cscript%3E')->assertNotFound();
        $this->get("/account/trips/{$hold->ulid}")->assertRedirect(route('checkout.show', $hold));
        $this->get("/account/trips/{$expired->ulid}")->assertNotFound();
    }

    public function test_a_finished_trip_can_be_reported_within_the_window_then_not(): void
    {
        $recent = $this->trip('2027-05-02 12:00', BookingStatus::Completed);
        $old = $this->trip('2027-04-20 12:00', BookingStatus::Completed);
        $cancelled = $this->trip('2027-04-10 12:00', BookingStatus::Cancelled, ['amount_refunded_cents' => 150000, 'cancelled_at' => CarbonImmutable::parse('2027-04-01 12:00', 'America/Chicago')->utc()]);
        $this->actingAs($this->guest);

        $this->get('/account/trips')->assertSee(route('account.trips.show', $recent).'#report', false);
        $this->get("/account/trips/{$recent->ulid}")->assertOk()->assertSee('Report a problem')->assertSee('Tell us by Wed, May 5 at 3:00 PM')->assertSee('This trip has ended.');
        $this->get("/account/trips/{$old->ulid}")->assertOk()->assertSee('The window to report a problem closed on Apr 23')->assertDontSee('id="report"', false);
        $this->get("/account/trips/{$cancelled->ulid}")->assertOk()->assertSee('Cancelled on Apr 1, 2027.')->assertSee('$1,500.00 was refunded')->assertDontSee('Your captain');
    }
}
