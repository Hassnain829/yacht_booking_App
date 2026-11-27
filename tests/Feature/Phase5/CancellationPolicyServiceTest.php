<?php

namespace Tests\Feature\Phase5;

use App\Enums\PaymentIntentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\CancellationPolicyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 5.4 — the refund math (PLAN §7.4, D23) for every window of every policy, from the booking's own policy
 * snapshot: refund % × (total − gratuity) + gratuity, never more than is left, less the card processing fee
 * (kept back once per booking; the provider's real fee, else the 2.9% + 30¢ estimate).
 */
class CancellationPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    private const RULES = [
        'flexible' => [['min_hours_before' => 24, 'refund_pct' => 100]],
        'moderate' => [['min_hours_before' => 120, 'refund_pct' => 100], ['min_hours_before' => 72, 'refund_pct' => 50]],
        'strict' => [['min_hours_before' => 720, 'refund_pct' => 100], ['min_hours_before' => 360, 'refund_pct' => 50]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'UTC'));
    }

    private function booking(string $policy, float $hoursBefore, int $total = 100000, int $gratuity = 0, ?int $fee = 3000): Booking
    {
        $booking = Booking::factory()->at(CarbonImmutable::now()->addMinutes((int) round($hoursBefore * 60)))->create([
            'total_cents' => $total, 'gratuity_cents' => $gratuity, 'amount_paid_cents' => $total,
            'policy_snapshot' => ['name' => ucfirst($policy), 'rules' => self::RULES[$policy]],
        ]);
        (new Payment)->forceFill([
            'booking_id' => $booking->id, 'provider' => 'fake', 'provider_intent_id' => 'fake_pi_'.Str::lower((string) Str::ulid()), 'amount_cents' => $total,
            'fee_cents' => $fee, 'status' => PaymentIntentStatus::Succeeded, 'idempotency_key' => (string) Str::uuid(),
        ])->save();

        return $booking;
    }

    /** @return array<string, array{string, float, int}> */
    public static function windows(): array
    {
        return [
            'flexible, 25 h before' => ['flexible', 25, 100],
            'flexible, exactly 24 h' => ['flexible', 24, 100],
            'flexible, 23 h before' => ['flexible', 23, 0],
            'moderate, 6 days before' => ['moderate', 144, 100],
            'moderate, 4 days before' => ['moderate', 96, 50],
            'moderate, exactly 72 h' => ['moderate', 72, 50],
            'moderate, 2 days before' => ['moderate', 48, 0],
            'strict, 31 days before' => ['strict', 744, 100],
            'strict, 20 days before' => ['strict', 480, 50],
            'strict, 10 days before' => ['strict', 240, 0],
        ];
    }

    #[DataProvider('windows')]
    public function test_every_window_of_every_policy_less_the_card_fee(string $policy, float $hoursBefore, int $percent): void
    {
        $preview = app(CancellationPolicyService::class)->preview($this->booking($policy, $hoursBefore));

        $byPolicy = 100000 * $percent / 100;
        $this->assertSame([$percent, $byPolicy, min($byPolicy, 3000), max(0, $byPolicy - 3000)], [$preview['percent'], $preview['policy_cents'], $preview['fee_cents'], $preview['refund_cents']]);
    }

    public function test_the_gratuity_always_comes_back_and_the_next_drop_is_known(): void
    {
        $service = app(CancellationPolicyService::class);

        $late = $service->preview($this->booking('moderate', 48, total: 118000, gratuity: 18000));
        $this->assertSame([0, 18000, 3000, 15000], [$late['percent'], $late['policy_cents'], $late['fee_cents'], $late['refund_cents']], '0% of the charter, the whole gratuity, less the fee');

        $half = $service->preview($this->booking('moderate', 96, total: 118000, gratuity: 18000));
        $this->assertSame(50000 + 18000 - 3000, $half['refund_cents']);

        $early = $service->preview($this->booking('moderate', 200));
        $this->assertSame(50, $early['next']['percent']);
        $this->assertTrue($early['next']['at']->equalTo(CarbonImmutable::now()->addHours(80)), 'it drops to 50% at 120 h before the start');

        $started = $service->preview($this->booking('flexible', -1));
        $this->assertSame([0, null], [$started['percent'], $started['next']]);
    }

    public function test_the_fee_is_kept_once_and_money_already_asked_for_is_not_refundable_again(): void
    {
        $service = app(CancellationPolicyService::class);
        $booking = $this->booking('flexible', 48);
        $this->assertSame([100000, 3000], [$service->refundableCents($booking), $service->feeToKeep($booking)]);

        $asked = Refund::factory()->for($booking)->create(['amount_cents' => 40000, 'fee_cents' => 3000, 'status' => RefundStatus::Requested]);
        $this->assertSame([60000, 0], [$service->refundableCents($booking), $service->feeToKeep($booking)], 'in flight: counted, and the fee was kept');
        $this->assertSame([100000, 3000], [$service->refundableCents($booking, except: $asked), $service->feeToKeep($booking, except: $asked)], 'approving that refund itself');

        $asked->forceFill(['status' => RefundStatus::Rejected])->save();
        $this->assertSame([100000, 3000], [$service->refundableCents($booking), $service->feeToKeep($booking)], 'a declined refund holds nothing');
    }

    public function test_without_the_providers_fee_the_estimate_is_used(): void
    {
        $this->assertSame(2930, CancellationPolicyService::estimateFee(100000), '2.9% + 30¢');
        $this->assertSame(0, CancellationPolicyService::estimateFee(0));

        $preview = app(CancellationPolicyService::class)->preview($this->booking('flexible', 48, fee: null));
        $this->assertSame([2930, 100000 - 2930], [$preview['fee_cents'], $preview['refund_cents']]);
    }
}
