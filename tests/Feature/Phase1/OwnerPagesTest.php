<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 1.4 — owner screens (demo owner, no login yet) and the List Your Boat wizard.
 * Phase 2 adds: guest → login, customer → 403/redirect, other owner's boat → 404.
 */
class OwnerPagesTest extends TestCase
{
    use RefreshDatabase;

    private const APPROVED = '01JDQ7M3K8R2V5X9C4B6N0P1TA';

    private const DRAFT = '01JDQ7M3K8R2V5X9C4B6N0P1TC';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-07-14 10:00:00', 'America/Chicago'));
        $this->actingAs(User::factory()->owner()->create()); // the owner area needs an owner login (Phase 2.3)
    }

    /** @return array<string, mixed> */
    private function embedded(string $html, string $attribute): array
    {
        $this->assertMatchesRegularExpression('/'.$attribute.'="([^"]+)"/', $html);
        preg_match('/'.$attribute.'="([^"]+)"/', $html, $match);

        return json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: string, 1: string, 2: list<string>}> */
    public static function pages(): array
    {
        return [
            'dashboard' => ['/owner', 'Dashboard', ['Owner dashboard', 'Marcus Reed', 'Completed bookings', 'List a new boat']],
            'boats' => ['/owner/boats', 'My boats', ['Lady Grace', 'Pending review', 'In progress', 'id="local-boat-template"']],
            'bookings' => ['/owner/bookings', 'Bookings', ['Upcoming trips', 'CYB-7Q2M4K', 'Captain to be assigned']],
            'calendar' => ['/owner/calendar', 'Calendar', ['id="owner-calendar"', 'data-calendar=']],
            'earnings' => ['/owner/earnings', 'Earnings', ['How earnings work', 'Statement', 'Complaint deduction']],
            'payouts' => ['/owner/payouts', 'Payouts', ['Request a withdrawal', 'Chase •••• 6789', 'PO-2N8K4D']],
            'complaints' => ['/owner/complaints', 'Complaints', ['Cleanliness', 'Send response', '$150.00 deducted']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('pages')]
    public function test_owner_page_renders_with_its_nav_item_active(string $url, string $navLabel, array $expected): void
    {
        $response = $this->get($url)->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
        foreach ($expected as $text) {
            $response->assertSee($text, false);
        }

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>'.preg_quote($navLabel, '/').'</', $html);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html, 'No inline <script> (CSP)');
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/i', $html, 'No inline style attributes (CSP)');
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/i', $html, 'No inline event handlers');
    }

    public function test_dashboard_shows_stats_and_action_items(): void
    {
        $this->get('/owner')
            ->assertOk()
            ->assertSee('A guest reported a problem with a trip')
            ->assertSee(route('owner.complaints.index').'#complaint-', false)
            ->assertSee('Your boat listing is not submitted yet')
            ->assertSee(route('list-your-boat', ['listing' => self::DRAFT]), false)
            ->assertSee('1 open');
    }

    public function test_dashboard_without_boats_invites_the_owner_to_list_one(): void
    {
        config(['demo_owner.boats' => [], 'demo_owner.bookings' => [], 'demo_owner.complaints' => [], 'demo_owner.payouts' => [], 'demo_owner.blocks' => []]);

        $this->get('/owner')->assertOk()->assertSee('listed a boat yet')->assertSee('$0.00');
        $this->get('/owner/boats')->assertOk()->assertSee('listed a boat yet');
        $this->get('/owner/calendar')->assertOk()->assertSee('Your calendar opens after approval');
    }

    public function test_booking_tabs_are_whitelisted(): void
    {
        $this->get('/owner/bookings?tab=past')->assertOk()->assertSee('CYB-H7V3QS')->assertDontSee('CYB-6MQ8HN');
        $this->get('/owner/bookings?tab=cancelled')->assertOk()->assertSee('CYB-6MQ8HN')->assertSee('fully refunded');
        $this->get('/owner/bookings?tab=%3Cscript%3E')->assertOk()->assertSee('CYB-7Q2M4K')->assertDontSee('<script>', false);
        $this->get('/owner/bookings?tab[]=past')->assertOk()->assertSee('CYB-7Q2M4K');
    }

    public function test_calendar_data_lists_trips_and_expanded_blocks_of_the_chosen_boat(): void
    {
        $data = $this->embedded($this->get('/owner/calendar?boat=not-a-boat')->getContent(), 'data-calendar');

        $this->assertSame(self::APPROVED, $data['boat']);
        $this->assertSame('2026-07-14', $data['today']);
        $this->assertNotContains('CYB-6MQ8HN', array_column($data['bookings'], 'ref'), 'Cancelled trips are not on the calendar');
        $this->assertSame(['2026-07-22', '2026-07-23', '2026-08-01'], array_column($data['blocks'], 'date'));
        $this->assertArrayNotHasKey('net_cents', $data['bookings'][0], 'Only calendar fields are embedded');

        $this->get('/owner/calendar?boat[]=x')->assertOk();
    }

    public function test_payouts_show_the_paused_banner_and_disable_withdrawals(): void
    {
        config(['demo_owner.payouts_paused' => ['reason' => 'Insurance certificate expired', 'days' => -2]]);

        $html = $this->get('/owner/payouts')->assertOk()->assertSee('Payouts are paused by our team')->assertSee('Insurance certificate expired')->getContent();
        $this->assertFalse($this->embedded($html, 'data-withdraw')['enabled']);
    }

    public function test_payouts_without_an_account_ask_to_connect(): void
    {
        config(['demo_owner.payout_account' => null]);

        $html = $this->get('/owner/payouts')->assertOk()->assertSee('Connect payouts')->getContent();
        $this->assertFalse($this->embedded($html, 'data-withdraw')['enabled']);
        $this->get('/owner')->assertSee('Incomplete');
    }

    public function test_guest_text_in_complaints_is_escaped(): void
    {
        $complaints = config('demo_owner.complaints');
        $complaints[0]['description'] = '<img src=x onerror=alert(1)>';
        config(['demo_owner.complaints' => $complaints]);

        $this->get('/owner/complaints')->assertOk()->assertDontSee('<img src=x', false)->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    }

    /* ------------------------------------------------------------------ wizard */

    public function test_wizard_starts_empty_with_the_options_and_owner_details(): void
    {
        $html = $this->get('/list-your-boat?new=1')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('id="wizard-bar"', false)
            ->getContent();

        $data = $this->embedded($html, 'data-listing');
        $this->assertNull($data['listing']);
        $this->assertTrue($data['isNew']);
        $this->assertSame(2000, $data['commissionBp']);
        $this->assertContains('Sea Ray', $data['options']['makes']);
        $this->assertSame(5000, $data['options']['pricing']['hourly_min_cents']);
        $this->assertSame('Marcus', $data['owner']['first_name']);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html);
    }

    public function test_wizard_opens_an_existing_listing_with_its_pricing(): void
    {
        $data = $this->embedded($this->get('/list-your-boat?listing='.self::APPROVED.'&step=pricing')->assertOk()->getContent(), 'data-listing');

        $this->assertSame('pricing', $data['step']);
        $this->assertSame(self::APPROVED, $data['listing']['id']);
        $this->assertSame('approved', $data['listing']['status']);
        $this->assertSame(['hourly' => '380', 'min_hours' => 3, 'weekend_markup_bp' => 1500, 'cleaning_fee' => '120', 'fixed' => []], $data['listing']['pricing']);
        $this->assertStringStartsWith('http', $data['listing']['photos'][0]);
    }

    public function test_wizard_rejects_unknown_listings_and_steps(): void
    {
        $this->get('/list-your-boat?listing=01JDQ7M3K8R2V5X9C4B6N0P1TZ')->assertNotFound();
        $this->get('/list-your-boat?listing[]='.self::APPROVED)->assertNotFound();
        $this->get('/list-your-boat?listing=')->assertNotFound();

        $data = $this->embedded($this->get('/list-your-boat?step=%3Cscript%3E')->assertOk()->getContent(), 'data-listing');
        $this->assertNull($data['step']);
        $this->assertFalse($data['isNew']);
    }

    public function test_dev_only_owner_links_point_to_real_routes(): void
    {
        $this->get('/owner')->assertSee(route('owner.calendar'), false)->assertSee(route('owner.earnings'), false)->assertSee(route('owner.payouts.index'), false);
        $this->get('/owner/boats')->assertSee(route('owner.calendar', ['boat' => self::APPROVED]), false);
    }
}
