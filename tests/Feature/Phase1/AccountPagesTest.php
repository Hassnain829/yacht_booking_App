<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use App\Support\Demo\DemoCustomer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 1.5 — auth screens (preview), customer account, legal pages, error pages.
 * Phase 2 adds: guest → login redirect, unverified → verify notice, someone else's trip → 404.
 */
class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    private const UPCOMING = '01JDTR1PQ7M3K8R2V5X9C4B6NA';

    private const RECENT = '01JDTR1PQ7M3K8R2V5X9C4B6NC';

    private const OLD = '01JDTR1PQ7M3K8R2V5X9C4B6ND';

    private const CANCELLED = '01JDTR1PQ7M3K8R2V5X9C4B6NE';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-07-14 10:00:00', 'America/Chicago'));
    }

    /** The account area needs a login (Phase 2.3). */
    private function asCustomer(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function assertCspSafe(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html, 'No inline <script> (CSP)');
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/i', $html, 'No inline style attributes (CSP)');
        $this->assertDoesNotMatchRegularExpression('/<[^>]+\son[a-z]+\s*=\s*["\']/i', $html, 'No inline event handlers');
    }

    /* ------------------------------------------------------------------ account */

    public function test_my_trips_lists_upcoming_soonest_first_then_past(): void
    {
        $html = $this->asCustomer()->get('/account/trips')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSeeInOrder(['Upcoming', 'CYB-8M2K4Q', 'CYB-5T9W3R', 'Past &amp; cancelled', 'CYB-2K7D9V', 'CYB-3W8N2J', 'CYB-6Q4H8M'], false)
            ->assertSee('Report a problem')
            ->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>My trips</', $html);
        $this->assertCspSafe($html);
    }

    public function test_upcoming_trip_shows_payment_captain_and_the_refund_preview(): void
    {
        $trip = app(DemoCustomer::class)->trip(self::UPCOMING);
        $html = $this->asCustomer()->get('/account/trips/'.self::UPCOMING)
            ->assertOk()
            ->assertSee('Capt. Devon J.')
            ->assertSee(money($trip->quote['total'], 'always'))
            ->assertSee(money($trip->refund['refund'], 'always').' <span', false)
            ->assertSee('Moderate policy')
            ->assertSee('id="cancel-dialog"', false)
            ->assertDontSee('id="report-form"', false)
            ->getContent();

        $this->assertSame(100, $trip->refund['percent']);
        $this->assertSame($trip->paid_cents, $trip->refund['refund'], 'Full refund of the deposit');
        preg_match('/data-ics="([^"]+)"/', $html, $m);
        $ics = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('CYB-8M2K4Q', $ics['ref']);
        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}$/', $ics['start']);
        $this->assertCspSafe($html);
    }

    public function test_recent_trip_can_be_reported_within_72_hours(): void
    {
        $this->asCustomer()->get('/account/trips/'.self::RECENT)
            ->assertOk()
            ->assertSee('id="report-form"', false)
            ->assertSee('Cleanliness')
            ->assertDontSee('id="cancel-dialog"', false)
            ->assertSee('This trip has ended.');
    }

    public function test_old_trip_explains_the_report_window_closed(): void
    {
        $this->asCustomer()->get('/account/trips/'.self::OLD)->assertOk()->assertSee('The window to report a problem closed')->assertDontSee('id="report-form"', false);
    }

    public function test_cancelled_trip_shows_the_refund(): void
    {
        $trip = app(DemoCustomer::class)->trip(self::CANCELLED);
        $this->asCustomer()->get('/account/trips/'.self::CANCELLED)
            ->assertOk()
            ->assertSee('Cancelled on')
            ->assertSee(money($trip->refunded_cents, 'always').' was refunded', false)
            ->assertDontSee('data-open-cancel', false);
    }

    public function test_unknown_or_malformed_trip_ids_are_404(): void
    {
        $this->asCustomer()->get('/account/trips/01JDTR1PQ7M3K8R2V5X9C4B6NZ')->assertNotFound();
        $this->asCustomer()->get('/account/trips/01jdtr1pq7m3k8r2v5x9c4b6na')->assertNotFound();
        $this->asCustomer()->get('/account/trips/1')->assertNotFound();
        $this->asCustomer()->get('/account/trips/%3Cscript%3E')->assertNotFound();
    }

    public function test_profile_page_renders_every_section_without_card_numbers(): void
    {
        $html = $this->asCustomer()->get('/account')
            ->assertOk()
            ->assertSee('value="alex@example.com"', false)
            ->assertSee('Visa •••• 4242')
            ->assertSee('data-demo-form', false)
            ->assertSee('name="_token"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>Profile &amp; security</', $html);
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));
        $this->assertDoesNotMatchRegularExpression('/\d{12,}/', strip_tags($main), 'No full card numbers');
        $this->assertCspSafe($html);
    }

    /* ------------------------------------------------------------------ auth screens */

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function authScreens(): array
    {
        return [
            'login' => ['/login', 'Welcome back', '/login'],
            'register' => ['/register', 'Create your account', '/register'],
            'forgot' => ['/forgot-password', 'Forgot your password?', '/forgot-password'],
            'reset' => ['/reset-password/abc123', 'Choose a new password', '/reset-password'],
            // The 2FA challenge only opens after a correct password: tests/Feature/Phase2/TwoFactorTest.
        ];
    }

    #[DataProvider('authScreens')]
    public function test_auth_screen_renders_a_csrf_protected_form(string $url, string $heading, string $action): void
    {
        $html = $this->get($url)
            ->assertOk()
            ->assertSee($heading)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('action="'.url($action).'"', false)
            ->assertSee('name="_token"', false)
            ->getContent();
        $this->assertCspSafe($html);
    }

    public function test_reset_screen_escapes_the_email_and_token(): void
    {
        $this->get('/reset-password/abc123?email=%22%3E%3Cscript%3Ealert(1)%3C%2Fscript%3E')
            ->assertOk()
            ->assertSee('name="token" value="abc123"', false)
            ->assertDontSee('"><script>alert(1)</script>', false);
        $this->get('/reset-password/%3Cscript%3E')->assertOk()->assertSee('name="token" value="&lt;script&gt;"', false); // verify / confirm screens need a login: tests/Feature/Phase2/AuthTest
    }

    public function test_register_links_to_the_legal_pages(): void
    {
        $this->get('/register')->assertSee(route('legal.terms'), false)->assertSee(route('legal.privacy'), false)->assertSee('minlength="12"', false);
    }

    /* ------------------------------------------------------------------ legal + errors */

    /** @return array<string, array{0: string, 1: string}> */
    public static function legalPages(): array
    {
        return [
            'terms' => ['/terms', 'Bookings and payment'],
            'privacy' => ['/privacy', 'Who we share it with'],
            'cancellation' => ['/cancellation-policy', 'The three policies'],
        ];
    }

    #[DataProvider('legalPages')]
    public function test_legal_page_renders(string $url, string $section): void
    {
        $html = $this->get($url)->assertOk()->assertSee($section)->assertSee('Last updated')->getContent();
        $this->assertCspSafe($html);
    }

    public function test_cancellation_policy_matches_the_refund_rules(): void
    {
        $this->get('/cancellation-policy')
            ->assertSee('24 hours or more before the start')
            ->assertSee('5 days (120 hours) or more before')
            ->assertSee('30 days (720 hours) or more before')
            ->assertSee('full refund', false);
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function errorPages(): array
    {
        return [
            '403' => [403, 'have access to this page'],
            '404' => [404, 'find that page'],
            '419' => [419, 'This page has expired'],
            '429' => [429, 'Slow down a little'],
            '500' => [500, 'Something went wrong on our side'],
            '503' => [503, 'be right back'],
        ];
    }

    #[DataProvider('errorPages')]
    public function test_error_page_renders_without_details(int $status, string $text): void
    {
        $html = view('errors.'.$status)->render();

        $this->assertStringContainsString($text, $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertStringNotContainsString('Stack trace', $html);
        $this->assertCspSafe($html);
    }

    public function test_unknown_url_uses_the_custom_404(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('find that page');
    }
}
