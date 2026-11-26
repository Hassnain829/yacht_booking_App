<?php

namespace Tests\Feature\Phase4;

use App\Actions\Booking\ConfirmPaidBooking;
use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\NewBookingReceived;
use App\Services\Payments\PaymentGateway;
use App\Services\PublicCatalog;
use App\Support\Payments\Intent;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 4.5 — hold + checkout, paid in full (D22; the payment is taken in 4.5, user decision 2026-11-26), with the
 * fake gateway (PAYMENTS_DRIVER=fake in phpunit.xml). §8.2 set for every endpoint: guest, wrong user (404), invalid
 * input (422), valid input, rate limit (429), duplicate request. The Stripe side is in StripeWebhookTest.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    private Boat $azure;

    private User $guest;

    /** Wednesday, May 12 2027, noon in Chicago, 3 hours, 6 guests on The Azure ($450/hr, captain $85/hr, $150 fuel & cleaning). */
    private const TRIP = ['yacht' => 'azure', 'date' => '2027-05-12', 'time' => '12:00', 'hours' => 3, 'guests' => 6];

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
        $this->guest = User::factory()->create(['first_name' => 'Jamie', 'last_name' => 'Rivera', 'email' => 'jamie@example.com', 'phone' => '(312) 555-0101']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function hold(?User $user = null, array $overrides = [], ?string $key = null): TestResponse
    {
        return $this->actingAs($user ?? $this->guest)->post('/checkout', array_merge(self::TRIP, ['idempotency_key' => $key ?? (string) Str::uuid()], $overrides));
    }

    private function held(): Booking
    {
        return Booking::query()->latest('id')->firstOrFail();
    }

    /** @param  array<string, mixed>  $overrides  @return array<string, mixed> */
    private function payment(array $overrides = []): array
    {
        return array_merge([
            'addons' => [], 'gratuity' => 18, 'first_name' => 'Jamie', 'last_name' => 'Rivera', 'email' => 'jamie@example.com',
            'phone' => '(312) 555-0101', 'occasion' => 'Birthday', 'heard' => null, 'notes' => 'A cake on board, please.', 'agree' => true,
        ], $overrides);
    }

    private function pay(Booking $booking, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->guest)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$booking->ulid}/pay", $this->payment($overrides));
    }

    private function payWithTestCard(Booking $booking, bool $decline = false): TestResponse
    {
        return $this->actingAs($this->guest)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$booking->ulid}/test-card", ['brand' => 'Visa', 'last4' => $decline ? '0002' : '4242', 'decline' => $decline]);
    }

    /* ------------------------------------------------------------------ start page (guests sign in here) */

    public function test_a_guest_sees_the_trip_and_signs_in_on_the_start_page_then_comes_back(): void
    {
        $url = '/checkout?'.http_build_query(self::TRIP);
        $page = $this->get($url)->assertOk()->assertSee('Log in and continue')->assertSee('Create account and continue')->assertSee('$1,912.95')->assertSee('Wed, May 12, 2027');
        $intended = (string) session('url.intended');
        $this->assertStringStartsWith(url('/checkout?'), $intended);
        $this->assertStringContainsString('yacht=azure', $intended);
        $this->assertStringNotContainsString('name="idempotency_key"', $page->getContent(), 'a guest can\'t hold yet');

        $this->post('/login', ['email' => 'jamie@example.com', 'password' => 'password'])->assertRedirect($intended);
        $this->get($url)->assertOk()->assertSee('Booking as Jamie Rivera')->assertSee('Continue to payment')->assertSee('action="'.route('checkout.hold').'"', false);
    }

    public function test_signing_up_on_the_start_page_comes_back_to_the_trip(): void
    {
        $this->get('/checkout?'.http_build_query(self::TRIP))->assertOk();
        $intended = (string) session('url.intended');
        $this->post('/register', ['first_name' => 'Sam', 'last_name' => 'Lee', 'email' => 'sam@example.com', 'password' => 'Sailing-Chicago-2027', 'password_confirmation' => 'Sailing-Chicago-2027', 'terms' => '1'])
            ->assertRedirect($intended);
        $this->post('/logout');
        $this->post('/register', ['first_name' => 'Ana', 'last_name' => 'Ruiz', 'email' => 'ana@example.com', 'password' => 'Sailing-Chicago-2027', 'password_confirmation' => 'Sailing-Chicago-2027', 'terms' => '1'])
            ->assertRedirect(route('verification.notice')); // a sign-up from anywhere else
    }

    public function test_a_wrong_or_taken_trip_goes_back_to_the_boats_card_with_the_reason(): void
    {
        $this->get('/checkout?'.http_build_query(['time' => '12:15'] + self::TRIP))->assertRedirect(route('yachts.show', 'azure').'#book');
        Booking::factory()->for($this->azure)->at(CarbonImmutable::parse('2027-05-12 12:00', 'America/Chicago')->utc(), 3)->create();
        $this->get('/checkout?'.http_build_query(self::TRIP))->assertRedirect()->assertSessionHas('booking_card_error', 'That time is already booked. Pick another time.');
        $this->get('/checkout?'.http_build_query(['yacht' => '%3Cscript%3E'] + self::TRIP))->assertRedirect(route('yachts.index'));
    }

    /* ------------------------------------------------------------------ hold */

    public function test_book_now_holds_the_trip_for_the_signed_in_guest(): void
    {
        $this->get('/yachts/azure')->assertSee('action="'.route('checkout.start').'" method="get"', false);
        $this->actingAs($this->guest)->get('/yachts/azure')->assertSee('action="'.route('checkout.hold').'" method="post"', false)->assertSee('name="idempotency_key"', false);

        $this->hold()->assertRedirect(route('checkout.show', $this->held()));
        $booking = $this->held();
        $this->assertSame([BookingStatus::Hold, PaymentStatus::Unpaid, $this->guest->id, 3, 6], [$booking->status, $booking->payment_status, $booking->customer_id, $booking->hours, $booking->guests]);
        $this->assertSame('2027-05-12T17:00:00Z', $booking->starts_at->toIso8601ZuluString());
        $this->assertSame(now()->addMinutes(15)->toIso8601ZuluString(), $booking->hold_expires_at->toIso8601ZuluString());
        $this->assertMatchesRegularExpression('/^CYB-[A-Z0-9]{6}$/', $booking->reference);
        $this->assertSame(['charter', 'captain', 'cleaning', 'tax', 'gratuity'], $booking->items->pluck('type')->map->value->all(), 'the default 18% gratuity');
        $this->assertSame([135000 + 25500 + 15000, $booking->items->sum('amount_cents')], [$booking->subtotal_cents, $booking->total_cents]);
        $this->assertSame(['Jamie', 'jamie@example.com', 'Moderate'], [$booking->contact_first_name, $booking->contact_email, $booking->policy_snapshot['name']]);
        $this->assertSame([null, 'hold'], [$booking->statusHistory->first()->from_status, $booking->statusHistory->first()->to_status->value]);
    }

    public function test_the_double_booking_lock_gives_the_time_to_the_first_guest_only(): void
    {
        $this->hold()->assertRedirect();
        $other = User::factory()->create();
        $this->hold($other)->assertRedirect(route('yachts.show', ['azure', 'date' => '2027-05-12', 'hours' => 3, 'guests' => 6]).'#book')
            ->assertSessionHas('booking_card_error', 'That time is already booked. Pick another time.');
        $this->assertSame(1, Booking::query()->count());

        $this->travel(16)->minutes(); // the first hold ran out: the time is free again
        $this->hold($other)->assertRedirect();
        $this->assertSame(2, Booking::query()->count());
    }

    public function test_the_same_click_twice_makes_one_hold_and_a_new_hold_lets_go_of_the_old_one(): void
    {
        $key = (string) Str::uuid();
        $this->hold(key: $key)->assertRedirect();
        $this->hold(key: $key)->assertRedirect()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, Booking::query()->count());
        $first = $this->held();

        $this->hold(overrides: ['time' => '15:00'])->assertRedirect();
        $this->assertSame(BookingStatus::Expired, $first->fresh()->status, 'one hold per guest');
        $this->assertSame('Let go: the guest held another trip', $first->fresh()->statusHistory->last()->note);
    }

    public function test_hold_refuses_guests_and_bad_input(): void
    {
        $this->post('/checkout', self::TRIP + ['idempotency_key' => (string) Str::uuid()])->assertRedirect('/login');
        $this->hold(overrides: ['hours' => 7])->assertRedirect()->assertSessionHas('booking_card_error');
        $this->hold(overrides: ['guests' => 99])->assertRedirect()->assertSessionHas('booking_card_error', 'This boat takes 1 to 13 guests.');
        $this->hold(overrides: ['experience' => 'corporate'])->assertRedirect()->assertSessionHas('booking_card_error', "This yacht doesn't offer that experience.");
        $this->hold(overrides: ['date' => '2027-02-30'])->assertRedirect()->assertSessionHasErrors('date');
        $this->actingAs($this->guest)->postJson('/checkout', self::TRIP)->assertStatus(400); // no idempotency key
        $this->assertSame(0, Booking::query()->count());
    }

    /* ------------------------------------------------------------------ checkout page, price, promo */

    public function test_the_checkout_page_and_its_endpoints_are_the_guests_own(): void
    {
        $this->hold();
        $booking = $this->held();
        $stranger = User::factory()->create();

        $this->actingAs($this->guest)->get("/checkout/{$booking->ulid}")->assertOk()->assertSee('Make it yours')->assertSee('value="Jamie"', false)->assertSee('Secure checkout');
        $this->actingAs($stranger)->get("/checkout/{$booking->ulid}")->assertNotFound();
        $this->actingAs($stranger)->getJson("/checkout/{$booking->ulid}/price?gratuity=18")->assertNotFound();
        $this->actingAs($stranger)->postJson("/checkout/{$booking->ulid}/promo", ['code' => 'WELCOME10'])->assertNotFound();
        $this->actingAs($stranger)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$booking->ulid}/pay", $this->payment())->assertNotFound();
        $this->actingAs($stranger)->get("/bookings/{$booking->ulid}/confirmation")->assertNotFound();
        auth()->logout();
        $this->getJson("/checkout/{$booking->ulid}/price?gratuity=18")->assertUnauthorized();
    }

    public function test_the_live_price_comes_from_the_quote_service(): void
    {
        $this->hold();
        $booking = $this->held();
        $this->actingAs($this->guest);

        $price = $this->getJson("/checkout/{$booking->ulid}/price?gratuity=20&addons[]=champagne&addons[]=catering")->assertOk()->json('data');
        $this->assertSame(['charter', 'captain', 'addon-champagne', 'addon-catering', 'cleaning'], array_column($price['lines'], 'key'));
        $this->assertSame([20, (int) round((135000 + 25500) * 0.2)], [$price['gratuity_pct'], $price['gratuity_cents']]);
        $this->assertSame(BookingStatus::Hold, $booking->fresh()->status, 'a GET changes nothing');
        $this->assertSame($booking->total_cents, $booking->fresh()->total_cents);

        $this->getJson("/checkout/{$booking->ulid}/price?gratuity=12")->assertUnprocessable()->assertJsonValidationErrors('gratuity');
        $this->getJson("/checkout/{$booking->ulid}/price?gratuity=18&addons[]=nope")->assertUnprocessable()->assertJsonValidationErrors('addons.0');
    }

    public function test_promo_codes_are_checked_and_applied_on_the_server(): void
    {
        $this->hold();
        $booking = $this->held();
        $this->actingAs($this->guest);

        $this->postJson("/checkout/{$booking->ulid}/promo", ['code' => 'welcome10'])->assertOk()->assertExactJson(['data' => ['code' => 'WELCOME10', 'label' => '10% off yacht charter']]);
        $price = $this->getJson("/checkout/{$booking->ulid}/price?gratuity=0")->json('data');
        $this->assertSame([13500, 'WELCOME10'], [$price['discount_cents'], $price['promo_code']]);

        $this->postJson("/checkout/{$booking->ulid}/promo", ['code' => 'NOPE'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'That promo code is not valid.']);
        $this->postJson("/checkout/{$booking->ulid}/promo", ['code' => '<script>'])->assertUnprocessable();
        PromoCode::query()->where('code', 'SUNSET50')->update(['max_redemptions' => 1, 'redemptions_count' => 1]);
        $this->postJson("/checkout/{$booking->ulid}/promo", ['code' => 'SUNSET50'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'SUNSET50 has been used up.']);
        PromoCode::query()->where('code', 'WELCOME10')->update(['ends_at' => now()->subDay()]);
        $this->postJson("/checkout/{$booking->ulid}/promo", ['code' => 'WELCOME10'])->assertUnprocessable();

        $this->deleteJson("/checkout/{$booking->ulid}/promo")->assertNoContent();
        $this->assertSame(0, $this->getJson("/checkout/{$booking->ulid}/price?gratuity=0")->json('data.discount_cents'));
    }

    /* ------------------------------------------------------------------ pay */

    public function test_paying_in_full_confirms_the_booking_emails_the_guest_and_records_the_promo(): void
    {
        Notification::fake();
        $this->hold();
        $booking = $this->held();
        $this->actingAs($this->guest)->postJson("/checkout/{$booking->ulid}/promo", ['code' => 'WELCOME10'])->assertOk();

        $start = $this->pay($booking, ['addons' => ['champagne'], 'gratuity' => 15, 'total_cents' => 1, 'status' => 'confirmed'])->assertOk()->json('data');
        $booking->refresh();
        $this->assertSame(['fake', null, $booking->total_cents, route('checkout.complete', $booking)], [$start['provider'], $start['client_secret'], $start['amount_cents'], $start['return_url']]);
        $this->assertSame(BookingStatus::Hold, $booking->status, 'not confirmed before the money comes in');
        $this->assertSame(['Birthday', 'A cake on board, please.', 1500, 13500], [$booking->occasion, $booking->customer_notes, $booking->gratuity_bp, $booking->discount_cents]);
        $this->assertNotSame(1, $booking->total_cents, 'a posted price is ignored');

        $this->payWithTestCard($booking)->assertOk()->assertJsonPath('data.redirect', route('bookings.confirmation', $booking));
        $booking->refresh();
        $payment = Payment::query()->sole();
        $this->assertSame([BookingStatus::Confirmed, PaymentStatus::Paid, $booking->total_cents], [$booking->status, $booking->payment_status, $booking->amount_paid_cents]);
        $this->assertSame([PaymentIntentStatus::Succeeded, 'visa', '4242', $booking->total_cents], [$payment->status, $payment->card_brand, $payment->card_last4, $payment->amount_cents]);
        $this->assertSame(['WELCOME10', 13500, 1], [PromoRedemption::query()->sole()->promoCode->code, PromoRedemption::query()->sole()->discount_cents, PromoCode::query()->where('code', 'WELCOME10')->value('redemptions_count')]);
        $this->assertSame(['hold', 'confirmed'], $booking->statusHistory->pluck('to_status')->map->value->all());

        Notification::assertSentTo(new AnonymousNotifiable, BookingConfirmed::class, function (BookingConfirmed $notification, array $channels, AnonymousNotifiable $notifiable) use ($booking) {
            $mail = $notification->toMail($notifiable);

            return array_key_first($notifiable->routes['mail']) === 'jamie@example.com' && $mail->rawAttachments[0]['name'] === "{$booking->reference}.ics";
        });
        Notification::assertSentTo(new AnonymousNotifiable, NewBookingReceived::class);

        $this->get("/bookings/{$booking->ulid}/confirmation")->assertOk()->assertSee("You're booked, Jamie!", false)->assertSee($booking->reference)->assertSee('Visa •••• 4242')->assertSee('Champagne toast');
        $ics = $this->get("/bookings/{$booking->ulid}/calendar.ics")->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString("DTSTART:20270512T170000Z\r\n", $ics->getContent());
        $this->get("/checkout/{$booking->ulid}")->assertRedirect(route('bookings.confirmation', $booking));
    }

    public function test_a_declined_card_keeps_the_hold_and_a_retry_pays(): void
    {
        $this->hold();
        $booking = $this->held();
        $this->pay($booking)->assertOk();

        $this->payWithTestCard($booking, decline: true)->assertUnprocessable()->assertJsonValidationErrors(['card' => 'Your card was declined. Try another card.']);
        $this->assertSame([BookingStatus::Hold, 'Your card was declined.'], [$booking->fresh()->status, Payment::query()->sole()->failure_message]);

        $this->pay($booking)->assertOk();
        $this->assertSame(1, Payment::query()->count(), 'the open payment is used again');
        $this->payWithTestCard($booking)->assertOk();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_pay_refuses_bad_input(): void
    {
        $this->hold();
        $booking = $this->held();

        $this->pay($booking, ['agree' => false])->assertUnprocessable()->assertJsonValidationErrors('agree');
        $this->pay($booking, ['email' => 'not-an-email', 'phone' => '<b>', 'occasion' => 'Rocket launch', 'notes' => '<script>x</script>'])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'occasion', 'notes']);
        $this->pay($booking, ['addons' => array_fill(0, 11, 'champagne')])->assertUnprocessable();
        $this->flushHeaders()->actingAs($this->guest)->postJson("/checkout/{$booking->ulid}/pay", $this->payment())->assertStatus(400); // no idempotency key
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_an_expired_hold_cannot_be_paid_and_the_job_closes_it(): void
    {
        $this->hold();
        $booking = $this->held();
        $this->pay($booking)->assertOk();

        $this->travel(16)->minutes();
        $this->pay($booking)->assertUnprocessable()->assertJsonValidationErrors('hold');
        $this->actingAs($this->guest)->get("/checkout/{$booking->ulid}")->assertOk()->assertSee('This hold has ended');

        $this->artisan('bookings:expire-holds')->expectsOutput('Expired 1 hold.')->assertSuccessful();
        $this->assertSame([BookingStatus::Expired, PaymentIntentStatus::Canceled], [$booking->fresh()->status, Payment::query()->sole()->status]);
        $this->assertSame('The hold ran out', $booking->fresh()->statusHistory->last()->note);
        $this->artisan('bookings:expire-holds')->expectsOutput('Expired 0 holds.');
    }

    public function test_a_payment_that_lands_after_the_hold_ran_out_confirms_only_if_the_time_is_still_free(): void
    {
        $this->hold();
        $late = $this->held();
        $this->pay($late)->assertOk();
        $payment = Payment::query()->sole();
        $this->travel(16)->minutes();
        $this->artisan('bookings:expire-holds');

        app(ConfirmPaidBooking::class)->handle($payment, new Intent((string) $payment->provider_intent_id, PaymentIntentStatus::Succeeded, $payment->amount_cents));
        $this->assertSame([BookingStatus::Confirmed, PaymentStatus::Paid], [$late->fresh()->status, $late->fresh()->payment_status], 'nobody took the time');

        // A second guest's late payment, for a time someone else took meanwhile: kept expired, marked for a refund.
        $other = User::factory()->create();
        $this->hold($other, ['time' => '16:00']);
        $taken = $this->held();
        $this->actingAs($other)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$taken->ulid}/pay", $this->payment())->assertOk();
        $second = Payment::query()->latest('id')->first();
        $this->travel(16)->minutes();
        Booking::factory()->for($this->azure)->at(CarbonImmutable::parse('2027-05-12 16:00', 'America/Chicago')->utc(), 3)->create();
        app(ConfirmPaidBooking::class)->handle($second, new Intent((string) $second->provider_intent_id, PaymentIntentStatus::Succeeded, $second->amount_cents));
        $this->assertSame([BookingStatus::Expired, PaymentStatus::Paid], [$taken->fresh()->status, $taken->fresh()->payment_status]);
        $this->assertStringContainsString('refund the guest', (string) $taken->fresh()->admin_notes);
    }

    public function test_the_test_card_route_exists_only_with_the_fake_gateway(): void
    {
        $this->hold();
        $booking = $this->held();
        $this->pay($booking)->assertOk();
        config(['payments.driver' => 'stripe', 'payments.stripe.secret' => 'sk_test_x', 'payments.stripe.key' => 'pk_test_x']);
        $this->app->forgetInstance(PaymentGateway::class);

        $this->payWithTestCard($booking)->assertNotFound();
        $this->assertSame(BookingStatus::Hold, $booking->fresh()->status);
    }

    public function test_checkout_endpoints_are_rate_limited(): void
    {
        $this->hold();
        $booking = $this->held();
        $this->actingAs($this->guest);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/checkout/{$booking->ulid}/promo", ['code' => 'GUESS'.$i]);
        }
        $this->postJson("/checkout/{$booking->ulid}/promo", ['code' => 'GUESS10'])->assertTooManyRequests();

        for ($i = 0; $i < 9; $i++) {
            $this->hold(overrides: ['time' => '08:00']);
        }
        $this->hold(overrides: ['time' => '09:00'])->assertTooManyRequests();
    }
}
