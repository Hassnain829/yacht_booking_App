<?php

namespace Tests\Feature\Phase1;

use App\Models\CustomerPaymentMethod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 1.5 — auth screens (preview), customer account, legal pages, error pages.
 * Phase 2 adds: guest → login redirect, unverified → verify notice. My trips are real since 4.7 (Phase4/MyTripsTest).
 */
class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_profile_page_renders_every_section_without_card_numbers(): void
    {
        $user = User::factory()->create(['email' => 'jane.doe@example.com']);
        CustomerPaymentMethod::factory()->for($user)->default()->create();
        $html = $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('value="jane.doe@example.com"', false) // the real account since Phase 2.5
            ->assertSee('Visa •••• 4242') // the guest's saved card since 5.3
            ->assertDontSee('data-demo-form', false)
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
