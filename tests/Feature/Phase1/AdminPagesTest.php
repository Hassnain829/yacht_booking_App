<?php

namespace Tests\Feature\Phase1;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 1.7 — admin UI kit, shell and static screens (demo data, no login yet).
 * Phase 2 adds: guest → login, customer/owner → 403, admin without 2FA → 2FA setup,
 * sensitive actions → password.confirm.
 */
class AdminPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-07-14 10:00:00', 'America/Chicago'));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function screens(): array
    {
        return [
            'dashboard' => ['/admin', 'Dashboard', 'Needs attention'],
            'bookings' => ['/admin/bookings', 'Bookings', 'House + owner boats'],
            'booking' => ['/admin/bookings/CYB-K8D2PZ', 'Bookings', 'Notes for captain'],
            'boats' => ['/admin/boats', 'Boats', 'House boats'],
            'listings' => ['/admin/listings', 'Listing reviews', 'Blue Horizon'],
            'listing' => ['/admin/listings/01JDQ7M3K8R2V5X9C4B6N0P1TB', 'Listing reviews', 'Review checklist'],
            'captains' => ['/admin/captains', 'Captains', 'MML-4471902'],
            'users' => ['/admin/users', 'Users', 'marcus.reed@example.com'],
            'inbox' => ['/admin/inbox', 'Inbox', 'Company outing for 35 people'],
            'payouts' => ['/admin/payouts', 'Payouts', 'PO-9C4T2X'],
            'payments' => ['/admin/payments', 'Payments', 'Card declined'],
            'complaints' => ['/admin/complaints', 'Complaints', 'Cleanliness'],
            'catalog' => ['/admin/catalog', 'Experiences &amp; add-ons', 'WELCOME10'],
            'settings' => ['/admin/settings', 'Settings', 'Commission'],
            'audit' => ['/admin/audit-log', 'Audit log', 'Approved withdrawal'],
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

    public function test_booking_filters_and_sorts_are_whitelisted(): void
    {
        $this->get('/admin/bookings?status=%3Cscript%3E&sort=password&dir=sideways&kind=x&captain=y&page=999')
            ->assertOk()
            ->assertDontSee('<script>', false)
            ->assertSee('aria-sort="descending"', false);

        $this->get('/admin/bookings?status=cancelled')->assertOk()->assertSee('CYB-6MQ8HN')->assertDontSee('CYB-7Q2M4K');
        $this->get('/admin/bookings?q=cyb-7q2')->assertOk()->assertSee('CYB-7Q2M4K')->assertDontSee('CYB-K8D2PZ');
        $this->get('/admin/bookings?q[]=x')->assertOk();
        $this->get('/admin/bookings?captain=none')->assertOk()->assertSee('Not assigned')->assertDontSee('Capt. Devon J.');
        $this->get('/admin/bookings?sort=total&dir=asc')->assertOk()->assertSee('aria-sort="ascending"', false);
    }

    public function test_bookings_are_paginated(): void
    {
        $this->get('/admin/bookings')->assertOk()->assertSee('Showing 1–10 of 17');
        $this->get('/admin/bookings?page=2')->assertOk()->assertSee('Showing 11–17 of 17');
    }

    public function test_unknown_or_malformed_records_are_404(): void
    {
        $this->get('/admin/bookings/CYB-ZZZZZZ')->assertNotFound();
        $this->get('/admin/bookings/cyb-k8d2pz')->assertNotFound();
        $this->get('/admin/bookings/1')->assertNotFound();
        $this->get('/admin/listings/01JDQ7M3K8R2V5X9C4B6N0P1TZ')->assertNotFound();
        $this->get('/admin/listings/abc')->assertNotFound();
    }

    public function test_captain_checks_block_a_six_pack_captain_for_a_large_group(): void
    {
        $html = $this->get('/admin/bookings/CYB-K8D2PZ')->assertOk()->getContent(); // 10 guests

        $this->assertMatchesRegularExpression('/<input type="radio" name="captain" value="luis"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="radio" name="captain" value="devon"[^>]*disabled/', $html);
        $this->assertStringContainsString('max 6 guests (this trip has 10)', $html);
        $this->assertStringNotContainsString('value="grace"', $html, 'Inactive captains are not offered');
    }

    public function test_sensitive_actions_ask_for_a_reason(): void
    {
        $html = $this->get('/admin/payouts')->assertOk()->getContent();

        $this->assertSame(substr_count($html, 'data-demo-form'), substr_count($html, 'name="reason"'), 'Every payout action has a reason field');
        $this->assertStringContainsString('minlength="10"', $html);
        $this->assertStringContainsString('recorded in the audit log', $html);
    }

    public function test_users_role_filter_is_whitelisted(): void
    {
        $this->get('/admin/users?role=owner')->assertOk()->assertSee('marcus.reed@example.com')->assertDontSee('alex@example.com');
        $this->get('/admin/users?role=superuser')->assertOk()->assertSee('alex@example.com');
    }
}
