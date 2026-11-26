<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 1.7 — admin UI kit, shell and screens. Since 3.4 the fleet, catalog, captains, users, settings and
 * audit screens are real (their own tests in Phase3/); bookings, listings, inbox and finance stay demo.
 * Phase 2 adds: guest → login, customer/owner → 403 (2FA is the admin's choice: Settings → Security),
 * sensitive actions → password.confirm.
 */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-07-14 10:00:00', 'America/Chicago'));
        $this->actingAs(User::factory()->admin()->create()); // the admin area needs an admin login (Phase 2.3)
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function screens(): array
    {
        return [
            'dashboard' => ['/admin', 'Dashboard', 'Needs attention'],
            'bookings' => ['/admin/bookings', 'Bookings', 'House + owner boats'],
            'booking calendar' => ['/admin/bookings/calendar', 'Bookings', 'Booking calendar'],
            'boats' => ['/admin/boats', 'Boats', 'Add a house boat'],
            'add boat' => ['/admin/boats/create', 'Boats', 'Start times'],
            'amenities' => ['/admin/amenities', 'Amenities', 'Water toys'],
            'add amenity' => ['/admin/amenities/create', 'Amenities', 'Add an amenity'],
            'cancellation policies' => ['/admin/cancellation-policies', 'Cancellation policies', 'How much guests get back'],
            'harbors' => ['/admin/harbors', 'Harbors', 'Add a harbor'],
            'add harbor' => ['/admin/harbors/create', 'Harbors', 'Boarding notes for guests'],
            'listings' => ['/admin/listings', 'Listing reviews', 'Blue Horizon'],
            'listing' => ['/admin/listings/01JDQ7M3K8R2V5X9C4B6N0P1TB', 'Listing reviews', 'Review checklist'],
            'captains' => ['/admin/captains', 'Captains', 'Add a captain'],
            'add captain' => ['/admin/captains/create', 'Captains', 'Passengers allowed'],
            'users' => ['/admin/users', 'Users', 'Everyone'],
            'inbox' => ['/admin/inbox', 'Inbox', 'Company outing for 35 people'],
            'payouts' => ['/admin/payouts', 'Payouts', 'PO-9C4T2X'],
            'payments' => ['/admin/payments', 'Payments', 'Card declined'],
            'complaints' => ['/admin/complaints', 'Complaints', 'Cleanliness'],
            'promo codes' => ['/admin/promo-codes', 'Promo codes', 'Add a promo code'],
            'add promo code' => ['/admin/promo-codes/create', 'Promo codes', 'Uses per guest'],
            'experiences' => ['/admin/experiences', 'Experiences', 'Add an experience'],
            'add experience' => ['/admin/experiences/create', 'Experiences', 'Departure window'],
            'add-ons' => ['/admin/addons', 'Add-ons', 'Add an add-on'],
            'add add-on' => ['/admin/addons/create', 'Add-ons', 'Price'],
            'settings' => ['/admin/settings', 'Settings', 'Commission'],
            'audit' => ['/admin/audit-log', 'Audit log', 'Every admin action'],
        ];
    }

    #[DataProvider('screens')]
    public function test_admin_screen_renders_in_the_shell(string $url, string $navLabel, string $text): void
    {
        $html = $this->get($url)
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('id="admin-sidebar"', false)
            ->assertSee($text, false)
            ->getContent();

        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*<i[^>]*><\/i>\s*<span class="flex-1">'.preg_quote($navLabel, '/').'</', $html);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html, 'No inline <script> (CSP)');
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/i', $html, 'No inline style attributes (CSP)');
        $this->assertDoesNotMatchRegularExpression('/<[^>]+\son[a-z]+\s*=\s*["\']/i', $html, 'No inline event handlers');
    }

    public function test_unknown_or_malformed_records_are_404(): void
    {
        $this->get('/admin/bookings/CYB-ZZZZZZ')->assertNotFound();
        $this->get('/admin/bookings/cyb-k8d2pz')->assertNotFound();
        $this->get('/admin/bookings/1')->assertNotFound();
        $this->get('/admin/promo-codes/abc/edit')->assertNotFound();
        $this->get('/admin/listings/01JDQ7M3K8R2V5X9C4B6N0P1TZ')->assertNotFound();
        $this->get('/admin/listings/abc')->assertNotFound();
    }

    public function test_sensitive_actions_ask_for_a_reason(): void
    {
        $html = $this->get('/admin/payouts')->assertOk()->getContent();

        $this->assertSame(substr_count($html, 'data-demo-form'), substr_count($html, 'name="reason"'), 'Every payout action has a reason field');
        $this->assertStringContainsString('minlength="10"', $html);
        $this->assertStringContainsString('recorded in the audit log', $html);
    }
}
