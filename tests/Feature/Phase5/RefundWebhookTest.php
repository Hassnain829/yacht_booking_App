<?php

namespace Tests\Feature\Phase5;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\DisputeOpened;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Phase 5.4 — Stripe's refund and dispute webhooks (signed): a refund's status (the money is counted once, when
 * it succeeded; replays and repeats change nothing), found by its id or by our id in its metadata; a dispute is
 * kept on the booking and the business is emailed when one opens.
 */
class RefundWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_refunds';

    private Booking $booking;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['payments.driver' => 'stripe', 'payments.stripe.key' => 'pk_test_x', 'payments.stripe.secret' => 'sk_test_x', 'payments.stripe.webhook_secret' => self::SECRET]);
        $this->app->instance(PaymentGateway::class, new StripeGateway(new StripeClient('sk_test_x'), 'pk_test_x', self::SECRET));

        $this->booking = Booking::factory()->create(['customer_id' => User::factory()->create()->id, 'total_cents' => 100000, 'amount_paid_cents' => 100000, 'payment_status' => PaymentStatus::Paid]);
        $this->payment = tap((new Payment)->forceFill([
            'booking_id' => $this->booking->id, 'provider' => 'stripe', 'provider_intent_id' => 'pi_test_9', 'provider_charge_id' => 'ch_test_9',
            'amount_cents' => 100000, 'status' => PaymentIntentStatus::Succeeded, 'idempotency_key' => (string) Str::uuid(),
        ]))->save();
    }

    /** @param  array<string, mixed>  $object */
    private function event(string $id, string $type, array $object): TestResponse
    {
        $payload = json_encode(['id' => $id, 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]], JSON_THROW_ON_ERROR);
        $time = time();

        return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", self::SECRET), 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    public function test_a_refund_succeeding_is_counted_once(): void
    {
        $refund = Refund::factory()->for($this->booking)->create(['payment_id' => $this->payment->id, 'amount_cents' => 40000, 'status' => RefundStatus::Pending, 'provider_refund_id' => 're_test_1']);
        $object = ['id' => 're_test_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 40000, 'payment_intent' => 'pi_test_9', 'metadata' => []];

        $this->event('evt_r1', 'refund.updated', $object)->assertOk();
        $this->event('evt_r1', 'refund.updated', $object)->assertOk()->assertSee('Already handled');
        $this->event('evt_r2', 'charge.refund.updated', $object)->assertOk();

        $this->assertSame(RefundStatus::Succeeded, $refund->fresh()->status);
        $this->assertSame([40000, PaymentStatus::PartiallyRefunded, 40000], [$this->booking->fresh()->amount_refunded_cents, $this->booking->fresh()->payment_status, $this->payment->fresh()->amount_refunded_cents]);
    }

    public function test_a_refund_is_found_by_our_id_and_a_failure_is_kept(): void
    {
        $refund = Refund::factory()->for($this->booking)->create(['payment_id' => $this->payment->id, 'amount_cents' => 40000, 'status' => RefundStatus::Pending]);

        $this->event('evt_r3', 'refund.failed', ['id' => 're_test_2', 'object' => 'refund', 'status' => 'failed', 'failure_reason' => 'expired_or_canceled_card', 'metadata' => ['refund' => $refund->ulid]])->assertOk();
        $this->assertSame([RefundStatus::Failed, 're_test_2', 'expired_or_canceled_card'], [$refund->fresh()->status, $refund->fresh()->provider_refund_id, $refund->fresh()->failure_reason]);
        $this->assertSame(0, $this->booking->fresh()->amount_refunded_cents);

        $this->event('evt_r4', 'refund.updated', ['id' => 're_from_the_dashboard', 'object' => 'refund', 'status' => 'succeeded', 'metadata' => []])->assertOk(); // not ours: ignored
        $this->assertSame(1, Refund::query()->count());
    }

    public function test_a_dispute_is_kept_and_the_business_is_told_once(): void
    {
        $due = CarbonImmutable::parse('2027-06-01 12:00', 'UTC');
        $dispute = ['id' => 'dp_test_1', 'object' => 'dispute', 'amount' => 100000, 'reason' => 'fraudulent', 'status' => 'needs_response', 'payment_intent' => 'pi_test_9', 'charge' => 'ch_test_9', 'evidence_details' => ['due_by' => $due->getTimestamp()]];

        $this->event('evt_d1', 'charge.dispute.created', $dispute)->assertOk();
        $this->event('evt_d2', 'charge.dispute.updated', ['status' => 'under_review'] + $dispute)->assertOk();
        $this->event('evt_d3', 'charge.dispute.closed', ['status' => 'won'] + $dispute)->assertOk();

        $record = Dispute::query()->sole();
        $this->assertSame([$this->booking->id, $this->payment->id, 100000, 'fraudulent', 'won'], [$record->booking_id, $record->payment_id, $record->amount_cents, $record->reason, $record->status]);
        $this->assertTrue($record->evidence_due_by->equalTo($due));
        $this->assertNotNull($record->closed_at);
        Notification::assertSentToTimes(new AnonymousNotifiable, DisputeOpened::class, 1);

        $this->actingAs(User::factory()->admin()->create())->get("/admin/bookings/{$this->booking->reference}")->assertOk()->assertSee('Disputes')->assertSee('Won');
    }
}
