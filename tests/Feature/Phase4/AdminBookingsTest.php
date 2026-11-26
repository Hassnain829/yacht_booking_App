<?php

namespace Tests\Feature\Phase4;

use App\Enums\BoatOwnership;
use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\PublicCatalog;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBookings;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 4.8 — Admin → Bookings: the list (search, whitelisted filters / sort, pages), the month calendar, a
 * booking's page (contact, lines, payments, history), notes (audit-logged) and the status changes allowed now
 * (a reason + the password, `admin-sensitive`). §8.2 set: guest, wrong role, invalid input, valid, rate limit.
 */
class AdminBookingsTest extends TestCase
{
    use MakesBookings, RefreshDatabase, SeedsPublicCatalog;

    private User $admin;

    private Boat $azure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago'));
        $this->seedPublicCatalog();
        $this->azure = Boat::query()->where('slug', 'azure')->sole();
        $this->azure->forceFill(['allow_multiple_bookings' => true])->save();
        foreach (range(0, 6) as $day) {
            $this->azure->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        PublicCatalog::flush();
        $this->admin = User::factory()->admin()->create();
    }

    /** @param  array<string, mixed>  $attributes */
    private function trip(string $start, BookingStatus $status, array $attributes = [], ?Boat $boat = null): Booking
    {
        return Booking::factory()->for($boat ?? $this->azure)->at(CarbonImmutable::parse($start, 'America/Chicago')->utc(), 3)
            ->create($attributes + ['status' => $status, 'contact_first_name' => 'Ana', 'contact_last_name' => 'Ruiz', 'contact_email' => 'ana@example.com', 'total_cents' => 150000]);
    }

    /** @return array<string, string> */
    private function sensitive(array $fields): array
    {
        return $fields + ['reason' => 'The captain confirmed it by phone.', 'password' => 'password', 'dialog' => 'booking-status'];
    }

    public function test_only_the_admin_sees_bookings(): void
    {
        $trip = $this->trip('2027-05-12 12:00', BookingStatus::Confirmed);

        $this->get('/admin/bookings')->assertRedirect('/login');
        foreach ([User::factory()->create(), User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->get('/admin/bookings')->assertForbidden();
            $this->actingAs($user)->get("/admin/bookings/{$trip->reference}")->assertForbidden();
            $this->actingAs($user)->put("/admin/bookings/{$trip->reference}/notes", ['admin_notes' => 'x'])->assertForbidden();
        }
        $this->actingAs($this->admin)->get('/admin/bookings/CYB-ZZZZZZ')->assertNotFound();
    }

    public function test_the_list_searches_filters_and_sorts_on_whitelists(): void
    {
        $paid = $this->paidTrip(User::factory()->create(['first_name' => 'Jamie', 'last_name' => 'Rivera']), 'azure', '2027-05-12');
        $past = $this->trip('2027-04-20 12:00', BookingStatus::Completed, ['reference' => 'CYB-PAST22', 'contact_first_name' => 'Olu']);
        $this->trip('2027-05-14 12:00', BookingStatus::Expired, ['reference' => 'CYB-EXPRD4']);
        $ownerBoat = Boat::query()->where('slug', 'golden')->sole();
        $ownerBoat->forceFill(['ownership' => BoatOwnership::Owner, 'owner_id' => User::factory()->owner()->create()->id])->save();
        $this->trip('2027-05-20 12:00', BookingStatus::Confirmed, ['reference' => 'CYB-OWNER2'], $ownerBoat);
        $this->actingAs($this->admin);

        $this->get('/admin/bookings')->assertOk()->assertSee($paid->reference)->assertSee('CYB-PAST22')->assertSee('CYB-OWNER2')->assertDontSee('CYB-EXPRD4')->assertSee('Jamie Rivera');
        $this->get('/admin/bookings?status=expired')->assertOk()->assertSee('CYB-EXPRD4')->assertDontSee('CYB-PAST22');
        $this->get('/admin/bookings?q=rivera')->assertOk()->assertSee($paid->reference)->assertDontSee('CYB-PAST22');
        $this->get('/admin/bookings?q=cyb-past')->assertOk()->assertSee('CYB-PAST22')->assertDontSee($paid->reference);
        $this->get('/admin/bookings?q=golden')->assertOk()->assertSee('CYB-OWNER2')->assertDontSee('CYB-PAST22');
        $this->get('/admin/bookings?when=past')->assertOk()->assertSee('CYB-PAST22')->assertDontSee($paid->reference);
        $this->get('/admin/bookings?kind=owner')->assertOk()->assertSee('CYB-OWNER2')->assertDontSee($paid->reference);
        $this->get('/admin/bookings?captain=none&when=upcoming')->assertOk()->assertSee('Not assigned');
        $this->get('/admin/bookings?status=%3Cscript%3E&sort=password&dir=sideways&kind=x&when=y&q[]=x&page=999')->assertOk()->assertDontSee('<script>', false)->assertSee('aria-sort="descending"', false);
        $html = $this->get('/admin/bookings?sort=date&dir=asc')->assertOk()->assertSee('aria-sort="ascending"', false)->getContent();
        $this->assertLessThan(strpos($html, $paid->reference), strpos($html, 'CYB-PAST22'), 'oldest first');
    }

    public function test_the_calendar_shows_a_month_of_trips_by_chicago_day(): void
    {
        $paid = $this->paidTrip(User::factory()->create(), 'azure', '2027-05-12', '17:30');
        $hold = $this->holdTrip(User::factory()->create(), 'azure', '2027-05-21', '09:00');
        $this->trip('2027-05-14 12:00', BookingStatus::Expired, ['reference' => 'CYB-EXPRD5']);
        $this->trip('2027-06-02 12:00', BookingStatus::Confirmed, ['reference' => 'CYB-JUNE22']);
        $this->actingAs($this->admin);

        $this->get('/admin/bookings/calendar')->assertOk()->assertSee('May 2027')->assertSee('2 trips in May 2027')
            ->assertSee($paid->reference)->assertSee('5:30 PM')->assertSee($hold->reference)->assertSee('on hold')
            ->assertDontSee('CYB-EXPRD5')->assertDontSee('CYB-JUNE22')->assertSee('month=2027-06', false);
        $this->get('/admin/bookings/calendar?month=2027-06')->assertOk()->assertSee('CYB-JUNE22')->assertSee('1 trip in June 2027');
        $this->get('/admin/bookings/calendar?month=%3Cx%3E')->assertOk()->assertSee('May 2027');
        $this->get('/admin/bookings/calendar?month=1999-01')->assertOk()->assertSee('May 2027');
    }

    public function test_a_bookings_page_shows_the_contact_lines_payments_and_history(): void
    {
        $paid = $this->paidTrip(User::factory()->create(['first_name' => 'Jamie', 'last_name' => 'Rivera', 'email' => 'jamie@example.com', 'phone' => '(312) 555-0101']), 'azure', '2027-05-12');

        $this->actingAs($this->admin)->get("/admin/bookings/{$paid->reference}")->assertOk()
            ->assertSee('jamie@example.com')->assertSee('(312) 555-0101')->assertSee('Yacht charter')->assertSee('Visa •••• 4242')
            ->assertSee('On hold → Confirmed')->assertSee('Paid in full (Visa •••• 4242)')->assertSee('Moderate')
            ->assertDontSee('Change status'); // upcoming: nothing to change yet
    }

    public function test_notes_are_saved_plain_text_and_audit_logged(): void
    {
        $trip = $this->trip('2027-05-12 12:00', BookingStatus::Confirmed);
        $this->actingAs($this->admin);

        $this->put("/admin/bookings/{$trip->reference}/notes", ['admin_notes' => '  VIP guest  ', 'captain_notes' => 'Birthday cake on board.'])
            ->assertRedirect("/admin/bookings/{$trip->reference}");
        $this->assertSame(['VIP guest', 'Birthday cake on board.'], [$trip->fresh()->admin_notes, $trip->fresh()->captain_notes]);
        $log = AuditLog::query()->where('action', 'booking.notes_updated')->sole();
        $this->assertEquals(['admin_notes' => 'VIP guest', 'captain_notes' => 'Birthday cake on board.'], $log->after);

        $this->put("/admin/bookings/{$trip->reference}/notes", ['admin_notes' => 'VIP guest', 'captain_notes' => 'Birthday cake on board.']);
        $this->assertSame(1, AuditLog::query()->where('action', 'booking.notes_updated')->count(), 'nothing changed: nothing logged');

        $this->from("/admin/bookings/{$trip->reference}")->put("/admin/bookings/{$trip->reference}/notes", ['admin_notes' => '<b>bold</b>', 'captain_notes' => str_repeat('x', 1001)])
            ->assertSessionHasErrors(['admin_notes', 'captain_notes']);
        $this->assertSame('VIP guest', $trip->fresh()->admin_notes);
    }

    public function test_status_changes_only_when_allowed_with_a_reason_and_the_password(): void
    {
        $ended = $this->trip('2027-05-02 12:00', BookingStatus::Confirmed, ['reference' => 'CYB-ENDED2']);
        $upcoming = $this->trip('2027-05-12 12:00', BookingStatus::Confirmed, ['reference' => 'CYB-LATER2']);
        $this->actingAs($this->admin);

        $this->get('/admin/bookings/CYB-ENDED2')->assertSee('Change status')->assertSee('Completed — the trip took place')->assertSee('No-show — the guest didn&#039;t come', false);
        $this->post('/admin/bookings/CYB-ENDED2/status', $this->sensitive(['status' => 'completed', 'reason' => 'short']))->assertSessionHasErrorsIn('sensitive', ['reason']);
        $this->post('/admin/bookings/CYB-ENDED2/status', $this->sensitive(['status' => 'completed', 'password' => 'wrong']))->assertSessionHasErrorsIn('sensitive', ['password']);
        $this->post('/admin/bookings/CYB-ENDED2/status', $this->sensitive(['status' => 'cancelled']))->assertSessionHasErrorsIn('sensitive', ['status']);
        $this->assertSame(BookingStatus::Confirmed, $ended->fresh()->status);

        $this->post('/admin/bookings/CYB-ENDED2/status', $this->sensitive(['status' => 'completed']))->assertRedirect('/admin/bookings/CYB-ENDED2')->assertSessionHas('status', 'CYB-ENDED2 is now Completed.');
        $ended->refresh();
        $this->assertSame([BookingStatus::Completed, 'The captain confirmed it by phone.'], [$ended->status, $ended->statusHistory->last()->note]);
        $this->assertNotNull($ended->completed_at);
        $this->assertSame(['status' => 'completed'], AuditLog::query()->where('action', 'booking.status_changed')->sole()->after);

        $this->post('/admin/bookings/CYB-LATER2/status', $this->sensitive(['status' => 'completed']))->assertSessionHasErrorsIn('sensitive', ['status' => "This booking can't be marked Completed now."]);
        $this->assertSame(BookingStatus::Confirmed, $upcoming->fresh()->status);
    }

    public function test_letting_a_hold_go_frees_the_time_and_cancels_its_payment(): void
    {
        $hold = $this->holdTrip(User::factory()->create(), 'azure', '2027-05-12');
        $payment = tap((new Payment)->forceFill(['booking_id' => $hold->id, 'provider' => 'fake', 'provider_intent_id' => 'fake_pi_x', 'amount_cents' => $hold->total_cents, 'status' => PaymentIntentStatus::RequiresPayment, 'idempotency_key' => 'k-0000000000000001']))->save();

        $this->actingAs($this->admin)->post("/admin/bookings/{$hold->reference}/status", $this->sensitive(['status' => 'expired']))->assertRedirect();
        $this->assertSame([BookingStatus::Expired, PaymentIntentStatus::Canceled], [$hold->fresh()->status, $payment->fresh()->status]);
        $this->assertFalse($hold->fresh()->takesTime());
    }

    public function test_status_changes_are_rate_limited(): void
    {
        $trip = $this->trip('2027-05-12 12:00', BookingStatus::Confirmed);
        $this->actingAs($this->admin);
        for ($i = 0; $i < 10; $i++) {
            $this->post("/admin/bookings/{$trip->reference}/status", $this->sensitive(['status' => 'completed']));
        }
        $this->post("/admin/bookings/{$trip->reference}/status", $this->sensitive(['status' => 'completed']))->assertTooManyRequests();
    }
}
