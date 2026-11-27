<?php

namespace Tests\Feature\Phase5;

use App\Actions\Booking\CancelBooking;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundDeclined;
use App\Notifications\RefundSent;
use App\Notifications\TripCancelled;
use App\Services\CancellationPolicyService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\PublicCatalog;
use App\Support\Payments\ProviderRefund;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\MakesBookings;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 5.4 — refunds from the admin (D23: no money goes back without the admin): approve (the amount can change)
 * or decline a guest's refund; cancel a trip with the refund the admin picks (the policy's, everything, a custom
 * amount or none; the card fee kept back or not); refund without cancelling. A reason + the password, the audit
 * log, the guest's emails, money counted once. §8.2 set: guest, wrong role, wrong booking (404), invalid input,
 * valid, duplicate request, rate limit; the provider refusing.
 */
class AdminRefundsTest extends TestCase
{
    use MakesBookings, RefreshDatabase, SeedsPublicCatalog;

    private User $admin;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago'));
        $this->seedPublicCatalog();
        $azure = Boat::query()->where('slug', 'azure')->sole();
        $azure->forceFill(['allow_multiple_bookings' => true])->save();
        foreach (range(0, 6) as $day) {
            $azure->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        PublicCatalog::flush();
        $this->admin = User::factory()->admin()->create();
        $this->guest = User::factory()->create(['first_name' => 'Jamie', 'email' => 'jamie@example.com']);
    }

    /** A paid trip the guest cancelled online: its refund waits for the admin. */
    private function requested(): Refund
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        app(CancelBooking::class)->byGuest($trip, $this->guest, 'plans', null, (string) Str::uuid());

        return Refund::query()->sole();
    }

    /** @param  array<string, mixed>  $fields */
    private function sensitive(string $dialog, array $fields = []): array
    {
        return $fields + ['reason' => 'Checked with the guest by phone.', 'password' => 'password', 'dialog' => $dialog, 'idempotency_key' => (string) Str::uuid()];
    }

    private function approve(Refund $refund, string $amount, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->post("/admin/bookings/{$refund->booking->reference}/refunds/{$refund->ulid}/approve", $this->sensitive("approve-{$refund->ulid}", ['amount' => $amount]));
    }

    public function test_the_admin_approves_a_guests_refund_and_the_money_is_counted_once(): void
    {
        $refund = $this->requested();
        $booking = $refund->booking;

        $this->actingAs($this->admin)->get('/admin/bookings')->assertSee('1 refund waits for your approval')->assertSee('Refund to approve');
        $this->get('/admin/bookings?refund=requested')->assertSee($booking->reference);
        $this->get("/admin/bookings/{$booking->reference}")->assertOk()->assertSee('Awaiting approval')->assertSee('Approve')->assertSee('Decline')->assertSee('policy 100%');

        $this->approve($refund, '1000.50')->assertRedirect("/admin/bookings/{$booking->reference}")->assertSessionHas('status');
        $refund->refresh();
        $booking->refresh();
        $this->assertSame([RefundStatus::Succeeded, 100050, $this->admin->id], [$refund->status, $refund->amount_cents, $refund->decided_by]);
        $this->assertStringStartsWith('fake_re_', (string) $refund->provider_refund_id);
        $this->assertSame([100050, PaymentStatus::PartiallyRefunded, 100050], [$booking->amount_refunded_cents, $booking->payment_status, Payment::query()->sole()->amount_refunded_cents]);
        $this->assertSame(['status' => 'pending', 'amount_cents' => 100050, 'refund' => $refund->ulid], AuditLog::query()->where('action', 'refund.approved')->sole()->after);
        Notification::assertSentTo(new AnonymousNotifiable, RefundSent::class);

        $this->approve($refund, '10')->assertSessionHasErrorsIn('sensitive', 'action'); // decided already
        $this->assertSame(100050, $booking->fresh()->amount_refunded_cents, 'counted once');
        $this->get("/account/trips/{$booking->ulid}")->assertNotFound(); // a guest's trip page is theirs only
        $this->actingAs($this->guest)->get("/account/trips/{$booking->ulid}")->assertSee('$1,000.50 was refunded to Visa •••• 4242');
    }

    public function test_the_admin_declines_and_the_guest_gets_the_reason(): void
    {
        $refund = $this->requested();

        $this->actingAs($this->admin)->post("/admin/bookings/{$refund->booking->reference}/refunds/{$refund->ulid}/decline", $this->sensitive("decline-{$refund->ulid}", ['reason' => 'The guest booked a new date instead.']))
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame([RefundStatus::Rejected, 'The guest booked a new date instead.'], [$refund->fresh()->status, $refund->fresh()->decision_note]);
        $this->assertSame(0, $refund->booking->fresh()->amount_refunded_cents);
        Notification::assertSentTo(new AnonymousNotifiable, RefundDeclined::class);
        $this->actingAs($this->guest)->get("/account/trips/{$refund->booking->ulid}")->assertSee('Your refund request was declined: The guest booked a new date instead.');
    }

    public function test_the_admin_cancels_by_phone_with_the_policy_refund_less_the_card_fee(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        $fee = CancellationPolicyService::estimateFee($trip->total_cents);
        $key = (string) Str::uuid();
        $data = $this->sensitive('booking-cancel', ['cause' => 'customer_cancel', 'refund' => 'policy', 'keep_fee' => '1', 'idempotency_key' => $key]);

        $this->actingAs($this->admin)->get("/admin/bookings/{$trip->reference}")->assertSee('Cancel booking')->assertSee('By the policy');
        $this->post("/admin/bookings/{$trip->reference}/cancel", $data)->assertRedirect()->assertSessionHas('status');
        $this->post("/admin/bookings/{$trip->reference}/cancel", $data)->assertRedirect(); // the same form again

        $trip->refresh();
        $refund = Refund::query()->sole();
        $this->assertSame([BookingStatus::Cancelled, 'admin'], [$trip->status, $trip->cancelled_by_role]);
        $this->assertSame([RefundStatus::Succeeded, RefundReason::CustomerCancel, $trip->total_cents - $fee, $fee, 100], [$refund->status, $refund->reason, $refund->amount_cents, $refund->fee_cents, $refund->policy_pct]);
        $this->assertSame([$trip->total_cents - $fee, PaymentStatus::PartiallyRefunded], [$trip->amount_refunded_cents, $trip->payment_status]);
        $this->assertSame(['booking.cancelled', 'refund.issued'], AuditLog::query()->orderBy('id')->pluck('action')->filter(fn ($a) => str_starts_with($a, 'booking.') || str_starts_with($a, 'refund.'))->values()->all());
        Notification::assertSentTo(new AnonymousNotifiable, TripCancelled::class, fn (TripCancelled $mail) => ! $mail->byGuest);
        Notification::assertNotSentTo(new AnonymousNotifiable, RefundSent::class);
    }

    public function test_weather_refunds_everything_and_none_or_too_much_is_handled(): void
    {
        $weather = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        $this->actingAs($this->admin)->post("/admin/bookings/{$weather->reference}/cancel", $this->sensitive('booking-cancel', ['cause' => 'weather', 'refund' => 'full', 'keep_fee' => '0']))->assertRedirect();
        $this->assertSame([$weather->total_cents, PaymentStatus::Refunded], [$weather->fresh()->amount_refunded_cents, $weather->fresh()->payment_status]);

        $tooMuch = $this->paidTrip($this->guest, 'azure', '2027-05-13');
        $this->post("/admin/bookings/{$tooMuch->reference}/cancel", $this->sensitive('booking-cancel', ['cause' => 'owner_cancel', 'refund' => 'custom', 'amount' => '999999']))->assertSessionHasErrorsIn('sensitive', 'amount');
        $this->assertSame(BookingStatus::Confirmed, $tooMuch->fresh()->status, 'nothing changed');
        $this->post("/admin/bookings/{$tooMuch->reference}/cancel", $this->sensitive('booking-cancel', ['cause' => 'owner_cancel', 'refund' => 'none']))->assertRedirect();
        $this->assertSame([BookingStatus::Cancelled, 0], [$tooMuch->fresh()->status, $tooMuch->refunds()->count()]);

        $this->post("/admin/bookings/{$tooMuch->reference}/cancel", $this->sensitive('booking-cancel', ['cause' => 'owner_cancel', 'refund' => 'none']))->assertSessionHasErrorsIn('sensitive', 'action'); // not confirmed any more
    }

    public function test_a_refund_without_cancelling_and_its_limits(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        $this->actingAs($this->admin)->post("/admin/bookings/{$trip->reference}/refunds", $this->sensitive('booking-refund', ['cause' => 'complaint', 'amount' => '150']))->assertRedirect()->assertSessionHas('status');
        $this->assertSame([BookingStatus::Confirmed, 15000], [$trip->fresh()->status, $trip->fresh()->amount_refunded_cents]);

        $this->post("/admin/bookings/{$trip->reference}/refunds", $this->sensitive('booking-refund', ['cause' => 'complaint', 'amount' => (string) ($trip->total_cents / 100)]))->assertSessionHasErrorsIn('sensitive', 'amount');
        $this->post("/admin/bookings/{$trip->reference}/refunds", $this->sensitive('booking-refund', ['cause' => 'bogus', 'amount' => '-5']))->assertSessionHasErrorsIn('sensitive', ['cause', 'amount']);
        $this->post("/admin/bookings/{$trip->reference}/cancel", $this->sensitive('booking-cancel', ['cause' => 'weather', 'refund' => 'custom']))->assertSessionHasErrorsIn('sensitive', 'amount');
        $this->post("/admin/bookings/{$trip->reference}/refunds", $this->sensitive('booking-refund', ['cause' => 'complaint', 'amount' => '10', 'password' => 'wrong']))->assertSessionHasErrorsIn('sensitive', 'password');
        $this->assertSame(1, Refund::query()->count());
    }

    public function test_the_provider_refusing_marks_the_refund_failed(): void
    {
        $this->app->instance(PaymentGateway::class, new class extends FakeGateway
        {
            public function refund(Payment $payment, int $amountCents, string $idempotencyKey, array $metadata = []): ProviderRefund
            {
                throw new RuntimeException('Charge already refunded.');
            }
        });
        $refund = $this->requested();

        $this->approve($refund, '100')->assertRedirect()->assertSessionHas('error', 'The refund failed: The payment provider refused the refund: Charge already refunded.');
        $this->assertSame(RefundStatus::Failed, $refund->fresh()->status);
        $this->assertSame(0, $refund->booking->fresh()->amount_refunded_cents);
        Notification::assertNotSentTo(new AnonymousNotifiable, RefundSent::class);
    }

    public function test_only_the_admin_refunds_and_a_refund_of_another_booking_is_a_404(): void
    {
        $refund = $this->requested();
        $other = $this->paidTrip($this->guest, 'azure', '2027-05-13');

        auth()->logout();
        $this->post("/admin/bookings/{$other->reference}/refunds", $this->sensitive('booking-refund', ['cause' => 'complaint', 'amount' => '10']))->assertRedirect('/login');
        foreach ([$this->guest, User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->post("/admin/bookings/{$other->reference}/cancel", $this->sensitive('booking-cancel', ['cause' => 'weather', 'refund' => 'full']))->assertForbidden();
            $this->approve($refund, '10', as: $user)->assertForbidden();
        }
        $this->actingAs($this->admin)->post("/admin/bookings/{$other->reference}/refunds/{$refund->ulid}/approve", $this->sensitive('x', ['amount' => '10']))->assertNotFound();
        $this->assertSame([RefundStatus::Requested, BookingStatus::Confirmed], [$refund->fresh()->status, $other->fresh()->status]);
    }

    public function test_refund_actions_are_rate_limited(): void
    {
        $trip = $this->paidTrip($this->guest, 'azure', '2027-05-12');
        $this->actingAs($this->admin);
        for ($i = 0; $i < 10; $i++) {
            $this->post("/admin/bookings/{$trip->reference}/refunds", $this->sensitive('booking-refund', ['cause' => 'complaint', 'amount' => '1']));
        }
        $this->post("/admin/bookings/{$trip->reference}/refunds", $this->sensitive('booking-refund', ['cause' => 'complaint', 'amount' => '1']))->assertTooManyRequests();
    }
}
