<?php

namespace Tests\Feature\Phase5;

use App\Enums\BookingStatus;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\CustomerPaymentMethod;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use App\Services\PublicCatalog;
use App\Services\UserAccountService;
use App\Support\Payments\SavedCard;
use App\Support\Payments\SetupStart;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stripe\StripeClient;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 5.3 — saved cards: added from Profile & security (Stripe SetupIntent; the fake gateway's test card in
 * tests), made the default, removed; saved by paying with "Save this card" and used to pay next time. Only the
 * brand, last 4 and expiry are kept; the guest's own cards only (else 404). §8.2 set.
 */
class SavedCardsTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago'));
        $this->guest = User::factory()->create(['first_name' => 'Jamie', 'last_name' => 'Rivera', 'email' => 'jamie@example.com', 'phone' => '(312) 555-0101']);
    }

    /** @param  array<string, mixed>  $card */
    private function addTestCard(array $card = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->guest)->postJson('/account/cards/test-card', $card + ['brand' => 'Visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2029]);
    }

    public function test_the_guest_adds_cards_picks_the_default_and_removes_one(): void
    {
        $this->actingAs($this->guest)->postJson('/account/cards/setup')->assertOk()->assertJsonPath('data.provider', 'fake')->assertJsonPath('data.test_card_url', route('account.cards.test-card'));
        $visa = $this->addTestCard()->assertCreated()->assertJsonPath('data.is_default', true)->assertJsonPath('data.expires', '12/29')->json('data.id');
        $mc = $this->addTestCard(['brand' => 'Mastercard', 'last4' => '4444', 'exp_month' => 8, 'exp_year' => 2028])->assertCreated()->assertJsonPath('data.is_default', false)->json('data.id');
        $this->assertArrayNotHasKey('provider_payment_method_id', $this->addTestCard()->json('data'), 'never the provider\'s id');

        $this->get('/account')->assertOk()->assertSee('Your card is saved.')->assertSee('Visa •••• 4242')->assertSee('Mastercard •••• 4444')->assertSee('Make default')->assertSee('Add a card');

        $this->post("/account/cards/{$mc}/default")->assertRedirect(route('account.profile').'#cards');
        $this->assertSame([false, true], [CustomerPaymentMethod::query()->where('ulid', $visa)->value('is_default'), CustomerPaymentMethod::query()->where('ulid', $mc)->value('is_default')]);

        $this->delete("/account/cards/{$mc}")->assertRedirect(route('account.profile').'#cards');
        $this->assertNull(CustomerPaymentMethod::query()->where('ulid', $mc)->first());
        $this->assertSame(1, $this->guest->paymentMethods()->where('is_default', true)->count(), 'another card becomes the default');
    }

    public function test_someone_elses_card_is_a_404_and_bad_cards_are_refused(): void
    {
        $theirs = CustomerPaymentMethod::factory()->for(User::factory())->create();

        $this->post("/account/cards/{$theirs->ulid}/default")->assertRedirect('/login');
        $this->actingAs($this->guest)->post("/account/cards/{$theirs->ulid}/default")->assertNotFound();
        $this->delete("/account/cards/{$theirs->ulid}")->assertNotFound();
        $this->assertNotNull($theirs->fresh());

        $this->addTestCard(['exp_month' => 4, 'exp_year' => 2027])->assertUnprocessable()->assertJsonValidationErrors(['exp_month' => 'expired']);
        $this->addTestCard(['brand' => '<b>', 'last4' => '42'])->assertUnprocessable()->assertJsonValidationErrors(['brand', 'last4']);
        $this->actingAs($this->guest)->postJson('/account/cards', ['setup_intent' => 'seti_123'])->assertNotFound(); // Stripe only
        $this->assertSame(0, $this->guest->paymentMethods()->count());
    }

    public function test_with_stripe_a_card_is_saved_only_when_stripe_says_so(): void
    {
        $detached = [];
        config(['payments.driver' => 'stripe']);
        $this->app->instance(PaymentGateway::class, new class(new StripeClient('sk_test_x'), 'pk_test_x', null, $detached) extends StripeGateway
        {
            public function __construct(StripeClient $stripe, ?string $key, ?string $secret, private array &$detached)
            {
                parent::__construct($stripe, $key, $secret);
            }

            public function createCustomer(User $user): string
            {
                return 'cus_test_1';
            }

            public function createSetup(string $customerId): SetupStart
            {
                return new SetupStart('seti_new', 'seti_new_secret_x');
            }

            public function retrieveSetup(string $setupId, string $customerId): ?SavedCard
            {
                return $setupId === 'seti_ok' && $customerId === 'cus_test_1' ? new SavedCard('pm_test_1', 'visa', '1881', 3, 2030) : null;
            }

            public function detach(string $paymentMethodId): void
            {
                $this->detached[] = $paymentMethodId;
            }
        });

        $this->actingAs($this->guest)->postJson('/account/cards/setup')->assertOk()->assertJsonPath('data.client_secret', 'seti_new_secret_x')->assertJsonPath('data.store_url', route('account.cards.store'));
        $this->assertSame('cus_test_1', $this->guest->fresh()->stripe_customer_id);

        $this->postJson('/account/cards', ['setup_intent' => 'seti_bad'])->assertUnprocessable()->assertJsonValidationErrors('card');
        $this->postJson('/account/cards', ['setup_intent' => 'not-a-setup'])->assertUnprocessable()->assertJsonValidationErrors('setup_intent');
        $card = $this->postJson('/account/cards', ['setup_intent' => 'seti_ok'])->assertCreated()->assertJsonPath('data.last4', '1881')->json('data.id');
        $this->get('/account/cards/added?setup_intent=seti_ok')->assertRedirect(route('account.profile').'#cards')->assertSessionHas('status', 'card-added');
        $this->assertSame(1, $this->guest->paymentMethods()->count(), 'the same card isn\'t saved twice');
        $this->get('/account/cards/added?setup_intent=seti_bad')->assertSessionHas('status', 'card-failed');
        $this->postJson('/account/cards/test-card', ['brand' => 'Visa', 'last4' => '4242', 'exp_month' => 1, 'exp_year' => 2030])->assertNotFound(); // fake only

        $this->delete("/account/cards/{$card}")->assertRedirect();
        $this->assertSame(['pm_test_1'], $detached, 'forgotten at Stripe too');
    }

    public function test_checkout_saves_the_card_when_asked_and_pays_with_it_next_time(): void
    {
        Notification::fake();
        $this->seedPublicCatalog();
        $azure = Boat::query()->where('slug', 'azure')->sole();
        $azure->forceFill(['allow_multiple_bookings' => true])->save();
        foreach (range(0, 6) as $day) {
            $azure->availabilityRules()->create(['weekday' => $day, 'is_open' => true, 'start_times' => StartTimes::defaults()]);
        }
        PublicCatalog::flush();
        $book = function (string $date, array $pay): Booking {
            $this->actingAs($this->guest)->post('/checkout', ['yacht' => 'azure', 'date' => $date, 'time' => '12:00', 'hours' => 3, 'guests' => 6, 'idempotency_key' => (string) Str::uuid()]);
            $booking = Booking::query()->latest('id')->firstOrFail();
            $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$booking->ulid}/pay", $pay + [
                'addons' => [], 'gratuity' => 18, 'first_name' => 'Jamie', 'last_name' => 'Rivera', 'email' => 'jamie@example.com', 'phone' => '(312) 555-0101', 'agree' => true,
            ])->assertOk();

            return $booking;
        };

        $first = $book('2027-05-12', ['save_card' => true]);
        $this->assertTrue(Payment::query()->where('booking_id', $first->id)->value('save_card'));
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$first->ulid}/test-card", ['brand' => 'Visa', 'last4' => '4242', 'decline' => false, 'exp_month' => 9, 'exp_year' => 2030])->assertOk();
        $saved = CustomerPaymentMethod::query()->sole();
        $this->assertSame([$this->guest->id, 'visa', '4242', 9, 2030, true], [$saved->user_id, $saved->brand, $saved->last4, $saved->exp_month, $saved->exp_year, $saved->is_default]);
        $this->assertSame(BookingStatus::Confirmed, $first->fresh()->status);

        $this->actingAs($this->guest)->post('/checkout', ['yacht' => 'azure', 'date' => '2027-05-19', 'time' => '12:00', 'hours' => 3, 'guests' => 6, 'idempotency_key' => (string) Str::uuid()]);
        $second = Booking::query()->latest('id')->firstOrFail();
        $this->get("/checkout/{$second->ulid}")->assertOk()->assertSee('Visa •••• 4242')->assertSee('Use a new card')->assertSee('value="'.$saved->ulid.'"', false);

        $theirs = CustomerPaymentMethod::factory()->for(User::factory())->create();
        $expired = CustomerPaymentMethod::factory()->for($this->guest)->create(['last4' => '1111', 'exp_month' => 1, 'exp_year' => 2026]);
        $pay = ['addons' => [], 'gratuity' => 18, 'first_name' => 'Jamie', 'last_name' => 'Rivera', 'email' => 'jamie@example.com', 'phone' => '(312) 555-0101', 'agree' => true];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$second->ulid}/pay", $pay + ['card' => $theirs->ulid])->assertUnprocessable()->assertJsonValidationErrors('card');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$second->ulid}/pay", $pay + ['card' => $expired->ulid])->assertUnprocessable()->assertJsonValidationErrors(['card' => 'expired']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$second->ulid}/pay", $pay + ['card' => $saved->ulid, 'save_card' => true])->assertOk();
        $payment = Payment::query()->where('booking_id', $second->id)->sole();
        $this->assertSame([$saved->provider_payment_method_id, false], [$payment->provider_payment_method_id, $payment->save_card]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/checkout/{$second->ulid}/test-card", ['brand' => 'Card', 'last4' => '0000', 'decline' => false])->assertOk();
        $this->assertSame(['visa', '4242'], [$payment->fresh()->card_brand, $payment->fresh()->card_last4], 'the saved card\'s details, not the browser\'s');
        $this->assertSame(BookingStatus::Confirmed, $second->fresh()->status);
        $this->assertSame(2, CustomerPaymentMethod::query()->where('user_id', $this->guest->id)->count(), 'nothing saved twice');
    }

    public function test_deleting_the_account_forgets_its_cards(): void
    {
        CustomerPaymentMethod::factory()->for($this->guest)->default()->create();
        app(UserAccountService::class)->deleteAccount($this->guest);
        $this->assertSame(0, CustomerPaymentMethod::query()->count());
    }

    public function test_card_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->guest)->postJson('/account/cards/setup');
        }
        $this->postJson('/account/cards/setup')->assertTooManyRequests();
    }
}
