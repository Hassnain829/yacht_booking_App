<?php

namespace Tests\Feature\Phase5;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\GuestCancelledTrip;
use App\Notifications\TripCancelled;
use App\Services\CancellationPolicyService;
use App\Services\PublicCatalog;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBookings;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 5.4 — the guest cancels a confirmed trip on the trip page. The time is freed at once; the refund the
 * booking's policy gives, less the card fee, is only asked for — no money moves until the admin approves it
 * (D23). §8.2 set: guest, wrong user (404), invalid input, valid, duplicate request, rate limit.
 */
class CancelTripTest extends TestCase
{
    use MakesBookings, RefreshDatabase, SeedsPublicCatalog;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago')); // a Monday
        $this->seedPublicCatalog();
        $azure = Boat::query()->where('slug', 'azure')->sole();
        $azure->forceFill(['allow_multiple_bookings' => true])->save();
        foreach (range(0, 6) as $day) {
            $azure->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        PublicCatalog::flush();
        $this->guest = User::factory()->create(['first_name' => 'Jamie', 'email' => 'jamie@example.com']);
    }

    /** @param  array<string, mixed>  $data */
    private function cancel(Booking $trip, array $data = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->guest)->post("/account/trips/{$trip->ulid}/cancel", $data + ['reason' => 'plans', 'note' => 'Our flight moved.', 'idempotency_key' => (string) Str::uuid()]);
    }

    public function test_the_guest_cancels_and_the_refund_waits_for_the_admin(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12'); // Moderate, 9 days ahead: 100%
        $fee = CancellationPolicyService::estimateFee($trip->total_cents);

        $this->cancel($trip)->assertRedirect(route('account.trips.show', $trip))->assertSessionHas('status', 'cancelled-refund-requested');

        $trip->refresh();
        $this->assertSame([BookingStatus::Cancelled, 'customer', 'Change of plans — Our flight moved.'], [$trip->status, $trip->cancelled_by_role, $trip->cancellation_reason]);
        $this->assertFalse($trip->takesTime(), 'the time is free again');
        $this->assertSame('Cancelled by the guest: Change of plans', $trip->statusHistory->last()->note);

        $refund = Refund::query()->sole();
        $this->assertSame([RefundStatus::Requested, RefundReason::CustomerCancel, $trip->total_cents - $fee, $fee, 100, 'plans', 'Our flight moved.'], [$refund->status, $refund->reason, $refund->amount_cents, $refund->fee_cents, $refund->policy_pct, $refund->guest_reason, $refund->note]);
        $this->assertNull($refund->provider_refund_id, 'nothing was sent to the card');
        $this->assertSame([0, PaymentStatus::Paid, 0], [$trip->amount_refunded_cents, $trip->payment_status, $trip->payments()->sole()->amount_refunded_cents]);

        Notification::assertSentTo(new AnonymousNotifiable, TripCancelled::class, fn (TripCancelled $mail, array $channels, AnonymousNotifiable $to) => array_key_first($to->routes['mail']) === 'jamie@example.com' && $mail->byGuest);
        Notification::assertSentTo(new AnonymousNotifiable, GuestCancelledTrip::class, fn (GuestCancelledTrip $mail, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'hello@chicagoyachtbooking.com');

        $this->get("/account/trips/{$trip->ulid}")->assertOk()->assertSee('Your trip is cancelled.')->assertSee('is being reviewed by our team')->assertSee('Refund under review')->assertDontSee('id="cancel-dialog"', false);
        $this->get('/account/trips')->assertSee('Cancelled · refund under review');
    }

    public function test_a_late_cancellation_keeps_only_the_gratuity_and_a_started_trip_cant_be_cancelled(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-04'); // 26 h ahead: 0% under Moderate
        $fee = CancellationPolicyService::estimateFee($trip->total_cents);
        $this->cancel($trip)->assertRedirect();
        $this->assertSame([0, $trip->gratuity_cents - $fee], [Refund::query()->sole()->policy_pct, Refund::query()->sole()->amount_cents], 'the gratuity comes back, less the card fee');

        $started = $this->paidTrip($this->guest, 'azure', '2027-05-05');
        $this->travelTo(CarbonImmutable::parse('2027-05-05 12:30', 'America/Chicago'));
        $this->cancel($started)->assertSessionHasErrors('cancel');
        $this->assertSame(BookingStatus::Confirmed, $started->fresh()->status);
        $this->get("/account/trips/{$started->ulid}")->assertOk()->assertDontSee('id="cancel-dialog"', false);
    }

    public function test_someone_elses_trip_is_a_404_and_bad_input_is_refused(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');

        auth()->logout();
        $this->post("/account/trips/{$trip->ulid}/cancel", ['reason' => 'plans', 'idempotency_key' => (string) Str::uuid()])->assertRedirect('/login');
        $this->cancel($trip, as: User::factory()->create())->assertNotFound();
        $this->cancel($trip, ['reason' => 'bogus', 'note' => '<script>x</script>'])->assertSessionHasErrors(['reason', 'note']);
        $this->actingAs($this->guest)->postJson("/account/trips/{$trip->ulid}/cancel", ['reason' => 'plans'])->assertStatus(400); // no idempotency key
        $this->assertSame(BookingStatus::Confirmed, $trip->fresh()->status);
        $this->assertSame(0, Refund::query()->count());
    }

    public function test_the_same_request_twice_cancels_once(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        $key = (string) Str::uuid();

        $this->cancel($trip, ['idempotency_key' => $key])->assertRedirect();
        $this->cancel($trip, ['idempotency_key' => $key])->assertRedirect()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, Refund::query()->count());
        $this->assertSame(1, $trip->statusHistory()->where('to_status', BookingStatus::Cancelled)->count());
    }

    public function test_cancelling_is_rate_limited(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        for ($i = 0; $i < 5; $i++) {
            $this->cancel($trip);
        }
        $this->cancel($trip)->assertTooManyRequests();
    }
}
