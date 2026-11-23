<?php

namespace Tests\Feature\Phase1;

use App\Models\Boat;
use App\Services\PublicCatalog;
use App\Support\Demo\BookingSelection;
use App\Support\Demo\DemoQuote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 1.3 — booking wizard, checkout and confirmation (demo: nothing is stored, no payment).
 *
 * Covers the §8.2 set that applies before auth exists: invalid input (422), valid input,
 * server-side recalculation (posted prices ignored), rate limits (429) and escaping.
 * Guest / wrong user / wrong role / idempotency tests arrive with holds and payments (Phase 4–5).
 */
class BookingFlowTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    /** "Today" for every test: Tuesday 14 July 2026, 10:00 in Chicago. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-07-14 10:00:00', 'America/Chicago'));
        $this->seedPublicCatalog(); // the booking pages read the database since 3.5
    }

    /* ------------------------------------------------------------------ helpers */

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            // The booking (hidden fields)
            'experience' => 'sunset', 'yacht' => 'azure', 'guests' => '6', 'date' => '2026-08-12', 'time' => '18:00', 'hours' => '3',
            'addons' => ['champagne'],
            // The guest
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'phone' => '(312) 555-0199',
            'occasion' => 'Birthday', 'heard' => 'Google', 'notes' => 'Window seat please',
            // Payment choices (card data: brand + last 4 only)
            'gratuity' => '18', 'plan' => 'deposit', 'promo' => '', 'agree' => '1',
            'card_brand' => 'Visa', 'card_last4' => '4242',
        ], $overrides);
    }

    private function selection(array $input): BookingSelection
    {
        return BookingSelection::from($input, app(PublicCatalog::class));
    }

    /** @return array<string, mixed> */
    private function embedded(string $html, string $attribute): array
    {
        $this->assertMatchesRegularExpression('/'.$attribute.'="([^"]+)"/', $html);
        preg_match('/'.$attribute.'="([^"]+)"/', $html, $match);

        return json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }

    /** What the server should charge for a payload (same calculator as the controller). */
    private function expectedQuote(array $payload, string $plan): array
    {
        $catalog = app(PublicCatalog::class);

        return DemoQuote::fromConfig()->quote($catalog->yacht($payload['yacht']), $catalog->experience($payload['experience']), [
            'date' => $payload['date'],
            'hours' => (int) $payload['hours'],
            'guests' => (int) $payload['guests'],
            'addons' => $catalog->addons()->whereIn('slug', $payload['addons'])->values()->all(),
            'promo' => $payload['promo'] ?: null,
            'gratuity' => (int) $payload['gratuity'],
            'plan' => $plan,
        ]);
    }

    /* ------------------------------------------------------------------ selection rules */

    public function test_wizard_cleans_a_deep_link_before_embedding_it(): void
    {
        $html = $this->get('/book?experience=sunset&yacht=azure&guests=999&date=2026-02-30&time=%3Cscript%3E&hours=99&addons=champagne,evilware,champagne&occasion=%3Cb%3Ehi')
            ->assertOk()
            ->getContent();

        $this->assertSame([
            'experience' => 'sunset', 'yacht' => 'azure', 'guests' => 13, 'date' => null, 'time' => null,
            'hours' => 3, 'addons' => ['champagne'], 'occasion' => '',
        ], $this->embedded($html, 'data-wizard')['initial']);
        $this->assertStringNotContainsString('evilware', $html);
        $this->assertStringNotContainsString('<b>hi', $html);
    }

    public function test_wizard_never_preselects_a_boat_that_cannot_take_the_booking(): void
    {
        $this->assertNull($this->selection(['experience' => 'sunset', 'yacht' => 'lakeside'])->yacht, 'Lakeside does not run sunset cruises');
        $this->assertNull($this->selection(['yacht' => 'no-such-boat'])->yacht);

        Boat::query()->where('slug', 'azure')->update(['booking_enabled' => false]); // the admin's Unavailable switch
        $this->app->forgetInstance(PublicCatalog::class);
        $this->assertNull($this->selection(['experience' => 'sunset', 'yacht' => 'azure'])->yacht);

        $wizard = $this->embedded($this->get('/book?yacht=azure')->getContent(), 'data-wizard');
        $this->assertNull($wizard['initial']['yacht']);
        $this->assertFalse(collect($wizard['yachts'])->firstWhere('slug', 'azure')['booking_enabled']);
    }

    /** @return array<string, array{0: string, 1: string, 2: ?string}> */
    public static function dates(): array
    {
        return [
            'today' => ['sunset', '2026-07-14', '2026-07-14'],
            'yesterday' => ['sunset', '2026-07-13', null],
            'not a real date' => ['sunset', '2026-02-30', null],
            'wrong format' => ['sunset', '07/20/2026', null],
            'too far ahead' => ['sunset', '2028-02-01', null],
            'last day of the window' => ['sunset', '2028-01-14', '2028-01-14'],
            'fireworks on a Wednesday' => ['fireworks', '2026-07-15', '2026-07-15'],
            'fireworks on a Monday' => ['fireworks', '2026-07-20', null],
        ];
    }

    #[DataProvider('dates')]
    public function test_dates_are_checked_against_the_window_and_weekdays(string $experience, string $date, ?string $expected): void
    {
        $time = $experience === 'fireworks' ? '20:00' : '18:00';
        $selection = $this->selection(['experience' => $experience, 'yacht' => 'azure', 'date' => $date, 'time' => $time]);

        $this->assertSame($expected, $selection->date);
        $this->assertSame($expected ? $time : null, $selection->time, 'No time without a valid date');
    }

    public function test_same_day_departures_need_more_than_two_hours_notice(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-14 15:10:00', 'America/Chicago'));
        $base = ['experience' => 'sunset', 'yacht' => 'azure', 'date' => '2026-07-14', 'hours' => '3'];

        $this->assertNull($this->selection($base + ['time' => '17:30'])->time, '17:30 is not more than 2 hours after 15:xx');
        $this->assertSame('18:00', $this->selection($base + ['time' => '18:00'])->time);
        $this->assertSame('17:30', $this->selection(['date' => '2026-07-15', 'time' => '17:30'] + $base)->time, 'Tomorrow is not limited');
    }

    /* ------------------------------------------------------------------ checkout page */

    public function test_checkout_page_shows_the_selection_without_leaking_promo_codes_or_naming_card_inputs(): void
    {
        $html = $this->get('/checkout?experience=sunset&yacht=azure&guests=6&date=2026-08-12&time=18:00&hours=3&addons=champagne')
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertDontSee('Showing a sample booking')
            ->assertDontSee('SUNSET50') // WELCOME10 is advertised on purpose; the code list is never sent
            ->getContent();

        $data = $this->embedded($html, 'data-checkout');
        $this->assertSame(['date' => '2026-08-12', 'hours' => 3, 'guests' => 6], $data['selection']);
        $this->assertArrayNotHasKey('promos', $data['pricing']);
        $this->assertSame('2026-08-05', $data['balanceDate']);

        preg_match_all('/<input[^>]*data-card="[a-z]+"[^>]*>/', $html, $cards);
        $this->assertCount(4, $cards[0], 'number, expiry, CVC and ZIP inputs');
        foreach ($cards[0] as $input) {
            $this->assertStringNotContainsString(' name=', $input, 'Card inputs must never be posted');
        }
    }

    public function test_checkout_without_a_complete_selection_shows_a_sample_booking(): void
    {
        $this->get('/checkout?yacht=azure&date=2026-02-30')->assertOk()->assertSee('Showing a sample booking');
    }

    public function test_trips_inside_the_balance_window_offer_pay_in_full_only(): void
    {
        $html = $this->get('/checkout?experience=sunset&yacht=azure&guests=6&date=2026-07-17&time=18:00&hours=3')->assertOk()->getContent();

        $this->assertNull($this->embedded($html, 'data-checkout')['balanceDate']);
    }

    /* ------------------------------------------------------------------ checkout POST */

    public function test_valid_checkout_is_priced_by_the_server_and_ignores_posted_prices(): void
    {
        $payload = $this->payload();
        $tampered = $payload + [
            'total' => '1', 'total_cents' => '1', 'hourly_rate_cents' => '1', 'deposit_cents' => '1',
            'status' => 'paid', 'user_id' => '1', 'owner_id' => '1', 'role' => 'admin',
            'card_number' => '4242424242424242', 'cvc' => '123',
        ];

        $this->post('/checkout', $tampered)->assertRedirect(route('booking.confirmation'))->assertSessionHasNoErrors();

        $quote = $this->expectedQuote($payload, 'deposit');
        $saved = session('booking.confirmation');
        $this->assertSame(['total' => $quote['total'], 'paid' => $quote['deposit'], 'balance' => $quote['total'] - $quote['deposit'], 'balance_date' => '2026-08-05'], $saved['totals']);
        $this->assertSame(['plan' => 'deposit', 'gratuity' => 18, 'promo' => null, 'brand' => 'Visa', 'last4' => '4242'], $saved['payment']);
        $this->assertMatchesRegularExpression('/^CYB-[A-Z0-9]{6}$/', $saved['ref']);

        $stored = json_encode($saved);
        foreach (['4242424242424242', '"cvc"', '"status"', '"role"', '"user_id"', '"owner_id"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $stored);
        }

        $this->get('/booking/confirmation')
            ->assertOk()
            ->assertSee("You're booked, Jane!", false)
            ->assertSee($saved['ref'])
            ->assertSee('4242')
            ->assertDontSee('Sample confirmation');
    }

    public function test_short_notice_trips_are_charged_in_full_even_if_deposit_is_posted(): void
    {
        $payload = $this->payload(['date' => '2026-07-17']); // Friday: balance date already passed

        $this->post('/checkout', $payload)->assertRedirect(route('booking.confirmation'));

        $quote = $this->expectedQuote($payload, 'full');
        $this->assertSame('full', session('booking.confirmation.payment.plan'));
        $this->assertSame($quote['total'], session('booking.confirmation.totals.paid'));
        $this->assertSame(0, session('booking.confirmation.totals.balance'));
        $this->assertNull(session('booking.confirmation.totals.balance_date'));
    }

    public function test_a_known_promo_is_applied_by_the_server_and_an_unknown_one_is_ignored(): void
    {
        $this->post('/checkout', $this->payload(['promo' => 'welcome10', 'plan' => 'full']));
        $quote = $this->expectedQuote($this->payload(['promo' => 'WELCOME10']), 'full');
        $this->assertGreaterThan(0, $quote['discount']);
        $this->assertSame('WELCOME10', session('booking.confirmation.payment.promo'));
        $this->assertSame($quote['total'], session('booking.confirmation.totals.total'));

        $this->post('/checkout', $this->payload(['promo' => 'FREE100', 'plan' => 'full']));
        $this->assertNull(session('booking.confirmation.payment.promo'));
        $this->assertSame($this->expectedQuote($this->payload(), 'full')['total'], session('booking.confirmation.totals.total'));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidCheckouts(): array
    {
        return [
            'missing first name' => [['first_name' => ''], 'first_name'],
            'long first name' => [['first_name' => str_repeat('a', 61)], 'first_name'],
            'bad email' => [['email' => 'jane@'], 'email'],
            'letters in phone' => [['phone' => 'call me'], 'phone'],
            'short phone' => [['phone' => '555-0199'], 'phone'],
            'unknown occasion' => [['occasion' => 'Rave'], 'occasion'],
            'unknown referral' => [['heard' => '<b>TV</b>'], 'heard'],
            'long notes' => [['notes' => str_repeat('n', 1001)], 'notes'],
            'gratuity not offered' => [['gratuity' => '50'], 'gratuity'],
            'unknown plan' => [['plan' => 'later'], 'plan'],
            'promo with spaces' => [['promo' => 'WEL COME'], 'promo'],
            'non-ascii promo' => [['promo' => 'WELCOMÉ10'], 'promo'],
            'terms not accepted' => [['agree' => '0'], 'agree'],
            'unknown card brand' => [['card_brand' => 'Diners<script>'], 'card_brand'],
            'more than 4 card digits' => [['card_last4' => '424242'], 'card_last4'],
            'no card digits' => [['card_last4' => ''], 'card_last4'],
            'zero guests' => [['guests' => '0'], 'guests'],
            'too many guests' => [['guests' => '81'], 'guests'],
            'bad date' => [['date' => '2026-13-01'], 'date'],
            'bad time' => [['time' => '6pm'], 'time'],
            'too many add-ons' => [['addons' => array_fill(0, 11, 'champagne')], 'addons'],
            'duplicate add-ons' => [['addons' => ['champagne', 'champagne']], 'addons.1'],
            'add-ons not a list' => [['addons' => 'champagne'], 'addons'],
        ];
    }

    #[DataProvider('invalidCheckouts')]
    public function test_invalid_checkout_input_is_rejected(array $overrides, string $field): void
    {
        $this->postJson('/checkout', $this->payload($overrides))->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertNull(session('booking.confirmation'));
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function tamperedBookings(): array
    {
        return [
            'yacht does not run this experience' => [['yacht' => 'lakeside']],
            'unknown yacht' => [['yacht' => 'black-pearl']],
            'unknown experience' => [['experience' => 'submarine']],
            'more guests than the yacht holds' => [['guests' => '20']],
            'time that is not a slot' => [['time' => '17:45']],
            'duration not offered' => [['hours' => '2']],
            'unknown add-on' => [['addons' => ['champagne', 'jetski']]],
            'past date' => [['date' => '2026-07-01']],
            'fireworks on a Monday' => [['experience' => 'fireworks', 'date' => '2026-07-20', 'time' => '20:00', 'hours' => '3']],
            'same day without notice' => [['date' => '2026-07-14', 'time' => '11:00', 'experience' => 'private', 'hours' => '3']],
        ];
    }

    #[DataProvider('tamperedBookings')]
    public function test_a_tampered_or_unavailable_booking_is_rejected(array $overrides): void
    {
        $this->postJson('/checkout', $this->payload($overrides))->assertStatus(422)->assertJsonValidationErrors('booking');
    }

    public function test_a_boat_that_stops_taking_bookings_is_rejected_at_checkout(): void
    {
        Boat::query()->where('slug', 'azure')->update(['booking_enabled' => false]); // the admin's Unavailable switch
        $this->app->forgetInstance(PublicCatalog::class);

        $this->postJson('/checkout', $this->payload())->assertStatus(422)->assertJsonValidationErrors('booking');
    }

    public function test_form_errors_send_the_guest_back_to_checkout_with_their_input(): void
    {
        $from = '/checkout?experience=sunset&yacht=azure&guests=6&date=2026-08-12&time=18:00&hours=3&addons=champagne';

        $this->from($from)->post('/checkout', $this->payload(['email' => 'nope']))
            ->assertRedirect($from)
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('first_name', 'Jane');
    }

    public function test_guest_input_is_escaped_on_the_confirmation_page(): void
    {
        $this->post('/checkout', $this->payload(['first_name' => '<img src=x onerror=alert(1)>']));

        $this->get('/booking/confirmation')
            ->assertOk()
            ->assertDontSee('<img src=x', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    }

    public function test_confirmation_without_a_booking_shows_the_sample(): void
    {
        $this->get('/booking/confirmation')->assertOk()->assertSee('CYB-DEMO42')->assertSee('Sample confirmation');
    }

    /* ------------------------------------------------------------------ promo endpoint */

    public function test_promo_endpoint_returns_only_the_discount_rule(): void
    {
        $this->postJson('/checkout/promo', ['code' => 'welcome10'])
            ->assertOk()
            ->assertExactJson(['data' => ['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'label' => '10% off yacht charter']]); // an API Resource (Phase 2.6)
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function badPromos(): array
    {
        return [
            'unknown' => [['code' => 'FREE100']],
            'missing' => [[]],
            'empty' => [['code' => '']],
            'not a string' => [['code' => ['WELCOME10']]],
            'symbols' => [['code' => "WELCOME10' OR 1=1"]],
            'non-ascii' => [['code' => 'WELCOMÉ10']],
            'too long' => [['code' => str_repeat('A', 31)]],
        ];
    }

    #[DataProvider('badPromos')]
    public function test_promo_endpoint_rejects_bad_codes(array $body): void
    {
        $response = $this->postJson('/checkout/promo', $body)->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertStringNotContainsString('SUNSET50', $response->getContent(), 'Other codes are never revealed');
    }

    public function test_promo_endpoint_is_post_only(): void
    {
        $this->get('/checkout/promo')->assertStatus(405);
    }

    /* ------------------------------------------------------------------ rate limits */

    public function test_promo_checks_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/checkout/promo', ['code' => 'GUESS'.$i])->assertStatus(422);
        }
        $this->postJson('/checkout/promo', ['code' => 'WELCOME10'])->assertStatus(429);
    }

    public function test_checkout_submits_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/checkout', $this->payload(['email' => 'bad']))->assertStatus(422);
        }
        $this->postJson('/checkout', $this->payload())->assertStatus(429);
    }
}
