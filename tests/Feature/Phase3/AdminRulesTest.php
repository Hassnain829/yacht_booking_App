<?php

namespace Tests\Feature\Phase3;

use App\Enums\AmenityGroup;
use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\CancellationPolicy;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 3.4.5 — Admin → Amenities, Cancellation policies and Settings: lists, edits with a reason + the password
 * where money rules change, refund rules that make sense, cached settings and the audit trail.
 */
class AdminRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'tax_rate' => '10.25', 'weekend_markup' => '20', 'hold_minutes' => '20',
            'gratuity_options' => ['20', '0', '15'], 'default_gratuity' => '15',
            'commission' => '22.5', 'payout_delay_hours' => '72', 'complaint_window_hours' => '48', 'min_withdrawal' => '75',
            'captain_offer_hours' => '24', 'captain_release_hours' => '48', 'captain_ack_reminder_hours' => '12', 'captain_turnaround_minutes' => '30',
            'extension_cutoff_minutes' => '30', 'extension_hold_minutes' => '10', 'extension_max_trip_hours' => '8',
            'business_name' => 'Chicago Yacht Booking', 'business_email' => 'Hello@ChicagoYachtBooking.com', 'business_phone' => '(312) 555-0148',
            'business_office' => 'DuSable Harbor', 'business_hours' => 'Daily 8–9', 'business_tagline' => 'Private charters.',
            'reason' => 'New tax rate from the city.', 'password' => 'password',
        ], $overrides);
    }

    /* ------------------------------------------------------------------ settings */

    public function test_settings_use_defaults_until_saved_and_save_in_stored_units(): void
    {
        $settings = app(SettingsService::class);
        $this->assertSame(900, $settings->get('tax_rate_bp'));
        $this->assertSame(24, $settings->get('captain_offer_hours'));

        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()->assertSee('value="9"', false)->assertSee('Trip extensions (Phase 8)');
        $this->put('/admin/settings', $this->settings())->assertRedirect('/admin/settings');

        $this->assertSame(1025, $settings->get('tax_rate_bp'));
        $this->assertSame(20, $settings->get('hold_minutes'));
        $this->assertSame(12000, $settings->get('weekend_multiplier_bp'));
        $this->assertSame([0, 15, 20], $settings->get('gratuity_options'));
        $this->assertSame(2250, $settings->get('commission_bp'));
        $this->assertSame(7500, $settings->get('min_withdrawal_cents'));
        $this->assertSame('hello@chicagoyachtbooking.com', $settings->get('business_email'));
        $this->assertSame('owners', Setting::query()->where('key', 'commission_bp')->value('group'));

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();
        $this->assertSame('New tax rate from the city.', $log->reason);
        $this->assertSame(900, $log->before['tax_rate_bp']);
        $this->assertSame(1025, $log->after['tax_rate_bp']);
        $this->assertArrayNotHasKey('captain_offer_hours', $log->after, 'unchanged keys are not logged');
        $this->get('/admin/settings')->assertSee('settings saved');
    }

    public function test_saved_settings_are_cached_and_the_cache_is_cleared_on_save(): void
    {
        $settings = app(SettingsService::class);
        $settings->get('tax_rate_bp');
        $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));

        $settings->update(['tax_rate_bp' => 950], $this->admin, 'Rate changed.');
        $this->assertFalse(Cache::has(SettingsService::CACHE_KEY));
        $this->assertSame(950, $settings->get('tax_rate_bp'));
        $this->assertSame([], $settings->update(['tax_rate_bp' => 950], $this->admin, 'Same again.'), 'no change → nothing written');
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_settings_need_a_reason_the_password_and_sane_values(): void
    {
        $this->actingAs($this->admin);

        $this->putJson('/admin/settings', $this->settings(['reason' => '', 'password' => 'nope']))->assertUnprocessable()->assertJsonValidationErrors(['reason', 'password']);
        $this->putJson('/admin/settings', $this->settings([
            'tax_rate' => '99', 'hold_minutes' => '1', 'gratuity_options' => ['17'], 'default_gratuity' => '25',
            'business_email' => 'not-an-email', 'business_name' => '<script>x</script>', 'extension_max_trip_hours' => '100',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['tax_rate', 'hold_minutes', 'gratuity_options.0', 'default_gratuity', 'business_email', 'business_name', 'extension_max_trip_hours']);

        $this->assertSame(0, Setting::query()->count());
        $this->assertSame(0, AuditLog::query()->count());

        foreach ([User::factory()->create(), User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->put('/admin/settings', $this->settings())->assertForbidden();
        }
    }

    /* ------------------------------------------------------------------ cancellation policies */

    public function test_the_admin_edits_a_policy_and_its_refund_rules(): void
    {
        $policy = CancellationPolicy::factory()->create(['slug' => 'moderate', 'name' => 'Moderate']);
        Boat::factory()->create(['cancellation_policy_id' => $policy->id]);
        $this->actingAs($this->admin)->get('/admin/cancellation-policies')->assertOk()->assertSee('Used by 1 boat.');

        $this->put('/admin/cancellation-policies/moderate', [
            'name' => 'Moderate', 'badge' => '', 'points_text' => "Free until 4 days before.\nHalf back until 2 days before.", 'is_active' => '1',
            'rules' => [['hours' => '48', 'pct' => '50'], ['hours' => '', 'pct' => ''], ['hours' => '0', 'pct' => '0'], ['hours' => '96', 'pct' => '100']],
            'reason' => 'Shorter windows for summer.', 'password' => 'password',
        ])->assertRedirect('/admin/cancellation-policies/moderate/edit');

        $policy->refresh();
        $this->assertSame([
            ['min_hours_before' => 96, 'refund_pct' => 100],
            ['min_hours_before' => 48, 'refund_pct' => 50],
            ['min_hours_before' => 0, 'refund_pct' => 0],
        ], $policy->rules);
        $this->assertSame(50, $policy->refundPercent(60));
        $this->assertSame(['Free until 4 days before.', 'Half back until 2 days before.'], $policy->points);
        $this->assertSame('Shorter windows for summer.', AuditLog::query()->where('action', 'cancellation_policy.updated')->sole()->reason);
    }

    public function test_refund_rules_must_make_sense(): void
    {
        CancellationPolicy::factory()->create(['slug' => 'flexible']);
        $this->actingAs($this->admin);
        $base = ['name' => 'Flexible', 'badge' => '', 'points_text' => 'Free until 24 hours before.', 'is_active' => '1', 'reason' => 'Fixing the rules today.', 'password' => 'password'];

        $this->putJson('/admin/cancellation-policies/flexible', $base + ['rules' => [['hours' => '24', 'pct' => '100']]])
            ->assertUnprocessable()->assertJsonPath('errors.rules.0', 'Add a row for 0 hours (cancellations right before the trip).');
        $this->putJson('/admin/cancellation-policies/flexible', $base + ['rules' => [['hours' => '24', 'pct' => '50'], ['hours' => '0', 'pct' => '100']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['rules']);
        $this->putJson('/admin/cancellation-policies/flexible', $base + ['rules' => [['hours' => '24', 'pct' => '150'], ['hours' => '24', 'pct' => '0']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['rules.0.pct', 'rules.0.hours']);
        $this->putJson('/admin/cancellation-policies/flexible', array_merge($base, ['password' => 'wrong']) + ['rules' => [['hours' => '0', 'pct' => '0']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->assertSame(0, AuditLog::query()->count());
    }

    /* ------------------------------------------------------------------ amenities */

    public function test_amenities_are_added_edited_and_deleted_from_the_boats_that_had_them(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/amenities', ['name' => 'Paddleboard', 'group' => 'extra', 'icon' => 'waves', 'sort' => '1'])->assertRedirect('/admin/amenities');
        $amenity = Amenity::query()->sole();
        $this->assertSame([AmenityGroup::Extra, 'waves'], [$amenity->group, $amenity->icon]);
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $amenity->ulid);

        $this->postJson('/admin/amenities', ['name' => 'Paddleboard', 'group' => 'extra', 'icon' => '', 'sort' => '1'])->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->post('/admin/amenities', ['name' => 'Paddleboard', 'group' => 'feature', 'icon' => '', 'sort' => '1'])->assertRedirect(); // another list: fine

        $this->put("/admin/amenities/{$amenity->ulid}", ['name' => 'Stand-up paddleboard', 'group' => 'extra', 'icon' => 'waves', 'sort' => '2'])->assertRedirect();
        $this->assertSame('Stand-up paddleboard', $amenity->fresh()->name);

        $boat = Boat::factory()->create();
        $boat->amenities()->attach($amenity);
        $this->get("/admin/amenities/{$amenity->ulid}/edit")->assertSee('Ticked on 1 boat.');
        $this->delete("/admin/amenities/{$amenity->ulid}", ['reason' => 'Not offered any more.', 'password' => 'password', 'dialog' => 'destroy-record'])->assertRedirect('/admin/amenities');

        $this->assertNull(Amenity::query()->where('ulid', $amenity->ulid)->first());
        $this->assertSame(0, $boat->amenities()->count());
        $this->assertSame(['name' => 'Stand-up paddleboard', 'group' => 'extra', 'boats' => 1], AuditLog::query()->where('action', 'amenity.deleted')->sole()->before);
        $this->get('/admin/amenities/1/edit')->assertNotFound();
    }
}
