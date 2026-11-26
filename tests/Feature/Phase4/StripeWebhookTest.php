<?php

namespace Tests\Feature\Phase4;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use App\Services\PublicCatalog;
use App\Support\Payments\Intent;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Stripe\StripeClient;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 4.5 — Stripe (PLAN §8.4): the webhook is trusted only with a valid signature, every event is handled once,
 * and the booking is confirmed from Stripe's own answer (the webhook, or the return page asking Stripe) — never
 * from the browser. Stripe's API is replaced by a stand-in that answers `retrieve` (no network in tests).
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    private const SECRET = 'whsec_test_secret';

    private User $guest;

    /** What the stand-in says the intent is now. */
    private PaymentIntentStatus $stripeSays = PaymentIntentStatus::Succeeded;

    private bool $stripeFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago'));
        $this->seedPublicCatalog();
        $azure = Boat::query()->where('slug', 'azure')->sole();
        foreach (range(0, 6) as $day) {
            $azure->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        PublicCatalog::flush();
        config(['payments.driver' => 'stripe', 'payments.stripe.key' => 'pk_test_x', 'payments.stripe.secret' => 'sk_test_x', 'payments.stripe.webhook_secret' => self::SECRET]);

        $test = $this;
        $this->app->instance(PaymentGateway::class, new class(new StripeClient('sk_test_x'), 'pk_test_x', self::SECRET, $test) extends StripeGateway
        {
            public function __construct(StripeClient $stripe, ?string $key, ?string $secret, private StripeWebhookTest $test)
            {
                parent::__construct($stripe, $key, $secret);
            }

            public function retrieve(Payment $payment): Intent
            {
                return $this->test->stripeAnswer($payment);
            }
        });
        $this->guest = User::factory()->create();
    }

    public function stripeAnswer(Payment $payment): Intent
    {
        if ($this->stripeFails) {
            throw new RuntimeException('Stripe is down');
        }

        return new Intent((string) $payment->provider_intent_id, $this->stripeSays, $payment->amount_cents, chargeId: 'ch_test_1', cardBrand: 'visa', cardLast4: '4242');
    }

    /** A held booking with an open Stripe payment, as PrepareCheckoutPayment leaves it. */
    private function heldWithPayment(): Payment
    {
        $this->actingAs($this->guest)->post('/checkout', ['yacht' => 'azure', 'date' => '2027-05-12', 'time' => '12:00', 'hours' => 3, 'guests' => 6, 'idempotency_key' => (string) Str::uuid()]);
        $booking = Booking::query()->latest('id')->firstOrFail();

        return tap((new Payment)->forceFill([
            'booking_id' => $booking->id, 'user_id' => $this->guest->id, 'provider' => 'stripe', 'provider_intent_id' => 'pi_test_123',
            'amount_cents' => $booking->total_cents, 'status' => PaymentIntentStatus::RequiresPayment, 'idempotency_key' => (string) Str::uuid(),
        ]))->save();
    }

    /** @return array{0: string, 1: string} payload, Stripe-Signature header */
    private function signed(string $id, string $type, string $intent = 'pi_test_123', string $secret = self::SECRET): array
    {
        $payload = json_encode(['id' => $id, 'object' => 'event', 'type' => $type, 'data' => ['object' => ['id' => $intent, 'object' => 'payment_intent', 'status' => 'succeeded']]], JSON_THROW_ON_ERROR);
        $time = time();

        return [$payload, "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", $secret)];
    }

    private function webhook(string $payload, string $signature): TestResponse
    {
        auth()->logout();

        return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    public function test_a_signed_success_confirms_the_booking_once(): void
    {
        Notification::fake();
        $payment = $this->heldWithPayment();
        [$payload, $signature] = $this->signed('evt_1', 'payment_intent.succeeded');

        $this->webhook($payload, $signature)->assertOk();
        $booking = $payment->booking->fresh();
        $this->assertSame([BookingStatus::Confirmed, PaymentIntentStatus::Succeeded, '4242'], [$booking->status, $payment->fresh()->status, $payment->fresh()->card_last4]);
        $this->assertNotNull(WebhookEvent::query()->where('event_id', 'evt_1')->value('processed_at'));

        $this->webhook($payload, $signature)->assertOk()->assertSeeText('Already handled');
        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(2, $booking->statusHistory()->count(), 'hold → confirmed, once');
    }

    public function test_a_wrong_or_missing_signature_is_refused_and_nothing_is_stored(): void
    {
        $payment = $this->heldWithPayment();
        [$payload] = $this->signed('evt_2', 'payment_intent.succeeded');
        [, $wrong] = $this->signed('evt_2', 'payment_intent.succeeded', secret: 'whsec_someone_else');

        $this->webhook($payload, $wrong)->assertStatus(400);
        $this->webhook($payload, '')->assertStatus(400);
        $this->assertSame(0, WebhookEvent::query()->count());
        $this->assertSame(BookingStatus::Hold, $payment->booking->fresh()->status);
    }

    public function test_a_failed_handling_is_retried_by_stripe(): void
    {
        $payment = $this->heldWithPayment();
        [$payload, $signature] = $this->signed('evt_3', 'payment_intent.succeeded');

        $this->stripeFails = true;
        $this->webhook($payload, $signature)->assertStatus(500);
        $this->assertSame('Stripe is down', WebhookEvent::query()->sole()->error);

        $this->stripeFails = false;
        $this->webhook($payload, $signature)->assertOk();
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->fresh()->status);
    }

    public function test_a_failed_payment_keeps_the_hold_and_other_events_are_only_stored(): void
    {
        $payment = $this->heldWithPayment();
        $this->stripeSays = PaymentIntentStatus::RequiresPayment;
        [$payload, $signature] = $this->signed('evt_4', 'payment_intent.payment_failed');
        $this->webhook($payload, $signature)->assertOk();
        $this->assertSame(BookingStatus::Hold, $payment->booking->fresh()->status);

        [$payload, $signature] = $this->signed('evt_5', 'customer.created', 'cus_1');
        $this->webhook($payload, $signature)->assertOk();
        $this->assertSame(2, WebhookEvent::query()->whereNotNull('processed_at')->count());
    }

    public function test_the_return_page_asks_stripe_and_never_trusts_the_redirect(): void
    {
        Notification::fake();
        $payment = $this->heldWithPayment();
        $booking = $payment->booking;

        $this->stripeSays = PaymentIntentStatus::Processing;
        $this->actingAs($this->guest)->get("/checkout/{$booking->ulid}/complete?payment_intent=pi_test_123&redirect_status=succeeded")
            ->assertOk()->assertSee('Confirming your payment');
        $this->assertSame(BookingStatus::Hold, $booking->fresh()->status, 'the redirect says succeeded; Stripe says processing');

        $this->stripeSays = PaymentIntentStatus::Succeeded;
        $this->getJson("/checkout/{$booking->ulid}/status")->assertOk()->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmation_url', route('bookings.confirmation', $booking));
        $this->get("/checkout/{$booking->ulid}/complete")->assertRedirect(route('bookings.confirmation', $booking));
    }

    public function test_the_csp_allows_stripe_only_with_the_stripe_driver(): void
    {
        $payment = $this->heldWithPayment();
        $page = $this->actingAs($this->guest)->get("/checkout/{$payment->booking->ulid}")->assertOk()->assertSee('id="payment-element"', false)->assertSee('https://js.stripe.com/v3/', false);
        $csp = (string) $page->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://js.stripe.com', $csp);
        $this->assertMatchesRegularExpression('/connect-src [^;]*https:\/\/api\.stripe\.com/', $csp);

        config(['payments.driver' => 'fake']);
        $this->assertStringNotContainsString('stripe', (string) $this->get('/')->headers->get('Content-Security-Policy'));
    }
}
