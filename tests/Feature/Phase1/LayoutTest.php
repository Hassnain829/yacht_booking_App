<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsPublicCatalog;
use Tests\TestCase;

/**
 * Phase 1.1 — shared layout: every public URL renders with the header, menu and footer,
 * the active link is marked, the promo strip honours its cookie, and dev pages stay private.
 */
class LayoutTest extends TestCase
{
    use RefreshDatabase, SeedsPublicCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPublicCatalog(); // the public pages read the database since 3.5
    }

    private const NAV_LABELS = ['Home', 'Yachts', 'Experiences', 'Pricing', 'About Us', 'Contact Us', 'List Your Boat'];

    /** @return array<string, array{0: string, 1: string}> [url, nav label that must be active] */
    public static function publicPages(): array
    {
        return [
            'home' => ['/', 'Home'],
            'yachts' => ['/yachts', 'Yachts'],
            'yacht detail' => ['/yachts/azure', 'Yachts'],
            'experiences' => ['/experiences', 'Experiences'],
            'pricing' => ['/pricing', 'Pricing'],
            'about' => ['/about', 'About Us'],
            'contact' => ['/contact', 'Contact Us'],
            'my boats' => ['/owner/boats', 'List Your Boat'],
            'owner dashboard' => ['/owner', 'List Your Boat'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_page_renders_layout_and_marks_active_link(string $url, string $activeLabel): void
    {
        if (str_starts_with($url, '/owner')) {
            $this->actingAs(User::factory()->owner()->create()); // the owner area needs a login (Phase 2.3)
        }
        $response = $this->get($url);

        $response->assertOk()
            ->assertSee('id="header"', false)
            ->assertSee('id="mobile-menu"', false)
            ->assertSee('id="site-footer"', false)
            ->assertSee('href="#main"', false)
            ->assertSee('<html lang="en" class="js scroll-smooth">', false);

        preg_match_all('/aria-current="page"[^>]*>([^<]+)</', $response->getContent(), $matches);
        $current = array_unique(array_map('trim', $matches[1]));

        $this->assertContains($activeLabel, $current, "\"$activeLabel\" should be the current nav link on $url");
        foreach (array_diff(self::NAV_LABELS, [$activeLabel]) as $other) {
            $this->assertNotContains($other, $current, "\"$other\" must not be marked current on $url");
        }
    }

    public function test_every_header_and_footer_link_points_to_a_real_page(): void
    {
        $base = rtrim(url('/'), '/');
        $html = $this->get('/about')->assertOk()->getContent();
        preg_match_all('/href="([^"#]+)/', $html, $matches);

        $paths = collect($matches[1])
            ->filter(fn (string $href) => str_starts_with($href, $base))
            ->map(fn (string $href) => (parse_url($href, PHP_URL_PATH) ?: '/').(parse_url($href, PHP_URL_QUERY) ? '?'.parse_url($href, PHP_URL_QUERY) : ''))
            ->reject(fn (string $path) => preg_match('#^/(build|images|css)/|\.svg$#', $path))
            ->unique();

        $this->assertGreaterThan(10, $paths->count());
        foreach ($paths as $path) {
            // Pages behind a login (owner area, list your boat) send guests to the login page.
            $response = $this->get($path);
            $this->assertTrue($response->isOk() || $response->isRedirect(route('login')), "{$path} → {$response->getStatusCode()}");
        }
    }

    public function test_titles_and_meta_are_set_per_page(): void
    {
        $this->get('/about')
            ->assertSee('<title>About Us — Chicago Yacht Booking</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical" href="'.url('/about').'">', false)
            ->assertSee('<meta name="csrf-token"', false);

    }

    public function test_layout_uses_the_brand_title_when_a_page_sets_none(): void
    {
        $html = (string) $this->blade("@extends('layouts.app') @section('content') <p>Body</p> @endsection");

        $this->assertStringContainsString('<title>Chicago Yacht Booking — Private Yacht Charters on Lake Michigan</title>', $html);
        $this->assertStringContainsString('<p>Body</p>', $html);
    }

    public function test_pages_contain_no_inline_scripts_or_event_handlers(): void
    {
        // Required for the strict Content-Security-Policy in Phase 2: scripts only from files.
        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<[^>]+\son[a-z]+\s*=\s*["\']/i', $html, 'Inline event handlers (onclick=…) are not allowed.');
    }

    public function test_promo_strip_shows_until_its_id_is_dismissed(): void
    {
        $this->get('/about')->assertSee('id="promo-bar"', false);

        $this->withUnencryptedCookie('promo_hidden', config('site.promo.id'))
            ->get('/about')
            ->assertDontSee('id="promo-bar"', false);
    }

    public function test_a_tampered_promo_cookie_is_ignored_and_never_echoed(): void
    {
        $this->withUnencryptedCookie('promo_hidden', '<script>alert(1)</script>')
            ->get('/about')
            ->assertSee('id="promo-bar"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_unknown_pages_and_bad_slugs_return_404(): void
    {
        $this->get('/does-not-exist')->assertNotFound();
        $this->get('/yachts/Bad_Slug!')->assertNotFound();
    }

    public function test_dev_pages_are_not_available_outside_local(): void
    {
        $this->assertNotSame('local', app()->environment());
        $this->get('/dev/components')->assertNotFound();
        $this->get('/dev/theme')->assertNotFound();
    }

    public function test_yacht_card_unavailable_state_has_no_book_link(): void
    {
        $html = (string) $this->blade('<x-yacht-card :yacht="$yacht" />', ['yacht' => $this->demoYacht(['booking_enabled' => false])]);

        $this->assertStringContainsString('Unavailable', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringNotContainsString('book?yacht=azure', $html);
        $this->assertStringContainsString('/yachts/azure', $html, 'The details page stays reachable.');
    }

    public function test_yacht_card_available_state_links_to_booking(): void
    {
        $html = (string) $this->blade('<x-yacht-card :yacht="$yacht" experience="sunset" />', ['yacht' => $this->demoYacht()]);

        $this->assertStringContainsString('book?yacht=azure&amp;experience=sunset', $html);
        $this->assertStringContainsString('$450', $html);
        $this->assertStringNotContainsString('aria-disabled', $html);
    }

    public function test_yacht_card_escapes_text(): void
    {
        $html = (string) $this->blade('<x-yacht-card :yacht="$yacht" />', ['yacht' => $this->demoYacht(['title' => '<img src=x onerror=alert(1)>'])]);

        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_header_account_menu_for_guests(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Log in or create an account"', $html);
        $this->assertStringContainsString('id="account-panel" hidden', $html);
        $this->assertStringContainsString('href="'.route('login').'"', $html);
        $this->assertStringContainsString('href="'.route('register').'"', $html);
        $this->assertStringNotContainsString('Log out', $html);
    }

    public function test_header_account_menu_for_signed_in_users(): void
    {
        $owner = User::factory()->owner()->create(['first_name' => 'Marcus', 'last_name' => 'Reed']);
        $this->actingAs($owner)->get('/owner')->assertOk()
            ->assertSee('Account menu — Marcus Reed')
            ->assertSee('href="'.route('owner.dashboard').'"', false)
            ->assertSee('Earnings &amp; payouts', false)
            ->assertSee('action="'.url('/logout').'"', false);

        $customer = User::factory()->create(['first_name' => 'Alex', 'last_name' => 'Morgan']);
        $this->actingAs($customer)->get('/account/trips')->assertOk()
            ->assertSee('Account menu — Alex Morgan')
            ->assertSee('List your boat')
            ->assertDontSee('Earnings &amp; payouts', false);
    }

    /** @param  array<string, mixed>  $overrides */
    private function demoYacht(array $overrides = []): Fluent
    {
        return new Fluent(array_merge(config('demo.yachts.0'), ['harbor' => new Fluent(config('demo.harbors.0'))], $overrides));
    }
}
