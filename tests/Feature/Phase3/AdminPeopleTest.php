<?php

namespace Tests\Feature\Phase3;

use App\Enums\BoatStatus;
use App\Enums\LoginEventType;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\Captain;
use App\Models\LoginEvent;
use App\Models\Media;
use App\Models\User;
use App\Notifications\TwoFactorChanged;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.4.6–3.4.7 — Admin → Captains (profiles, encrypted license, private documents, Trash), Users (list,
 * login history, suspend / ban / reset 2FA with a reason + password), the real Audit log and the dashboard.
 */
class AdminPeopleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
    }

    /** @return array<string, mixed> */
    private function captain(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Devon James', 'slug' => '', 'email' => ' Devon@Example.com ', 'phone' => '(312) 555-0101', 'bio' => '20 years on Lake Michigan.',
            'years_experience' => '20', 'languages_text' => 'English, Spanish', 'license_type' => 'master', 'license_number' => 'mml-4471902',
            'license_expires_on' => '2027-03-01', 'max_passengers_licensed' => '49', 'pay_rate' => '45.50',
            'is_active' => '1', 'is_public' => '1', 'sort' => '1',
        ], $overrides);
    }

    /* ------------------------------------------------------------------ captains */

    public function test_the_admin_adds_a_captain_with_an_encrypted_license_that_is_never_shown_in_full(): void
    {
        $this->actingAs($this->admin)->post('/admin/captains', $this->captain(['photo' => UploadedFile::fake()->image('devon.jpg', 600, 600)]))->assertRedirect();

        $captain = Captain::query()->sole();
        $this->assertSame(['devon@example.com', 'devon-james', ['English', 'Spanish'], 4550], [$captain->email, $captain->slug, $captain->languages, $captain->pay_rate_cents]);
        $this->assertSame('MML-4471902', $captain->license_number);
        $this->assertStringNotContainsString('4471902', (string) $captain->getRawOriginal('license_number'), 'encrypted in the database');
        $this->assertMatchesRegularExpression('#^captains/[0-9a-f-]{36}\.webp$#', $captain->photo_path);

        $log = AuditLog::query()->where('action', 'captain.created')->sole();
        $this->assertSame('(changed)', $log->after['license_number']);

        $this->get('/admin/captains')->assertSee('•••• 1902')->assertDontSee('4471902');
        $this->get("/admin/captains/{$captain->ulid}/edit")->assertOk()->assertSee('•••• 1902')->assertDontSee('4471902');

        $this->put("/admin/captains/{$captain->ulid}", $this->captain(['license_number' => '', 'pay_rate' => '50']))->assertRedirect();
        $this->assertSame(['MML-4471902', 5000], [$captain->fresh()->license_number, $captain->fresh()->pay_rate_cents], 'empty number keeps the saved one');
    }

    public function test_captain_rules_six_pack_unique_email_and_bad_input(): void
    {
        Captain::factory()->create(['email' => 'taken@example.com'])->delete();
        $this->actingAs($this->admin);

        $this->postJson('/admin/captains', $this->captain(['license_type' => 'oupv', 'max_passengers_licensed' => '12']))
            ->assertUnprocessable()->assertJsonPath('errors.max_passengers_licensed.0', 'An OUPV (six-pack) license allows at most 6 passengers.');
        $this->postJson('/admin/captains', $this->captain(['email' => 'taken@example.com']))->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->postJson('/admin/captains', $this->captain(['license_number' => '', 'license_type' => 'admiral', 'bio' => '<a href=x>hi</a>', 'phone' => 'call me']))
            ->assertUnprocessable()->assertJsonValidationErrors(['license_number', 'license_type', 'bio', 'phone']);
        $this->assertSame(1, Captain::withTrashed()->count());
    }

    public function test_license_documents_are_private_and_scoped_to_their_captain(): void
    {
        $captain = Captain::factory()->create();
        $other = Captain::factory()->create();
        $this->actingAs($this->admin);

        $this->post("/admin/captains/{$captain->ulid}/documents", ['document' => UploadedFile::fake()->createWithContent('license.pdf', "%PDF-1.4\n%%EOF\n")])
            ->assertRedirect("/admin/captains/{$captain->ulid}/edit#documents");
        $document = $captain->documents()->sole();
        $this->assertSame('local', $document->disk);
        Storage::disk('local')->assertExists($document->path);
        $this->get("/files/{$document->uuid}")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get("/files/{$document->uuid}")->assertNotFound();

        $this->actingAs($this->admin)->postJson("/admin/captains/{$captain->ulid}/documents", ['document' => UploadedFile::fake()->createWithContent('license.pdf', '<?php echo 1;')])
            ->assertUnprocessable()->assertJsonValidationErrors(['document']);

        $this->delete("/admin/captains/{$other->ulid}/documents/{$document->uuid}")->assertNotFound();
        $this->delete("/admin/captains/{$captain->ulid}/documents/{$document->uuid}")->assertRedirect();
        $this->assertSame(0, Media::query()->count());
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_a_captain_goes_to_the_trash_and_a_permanent_delete_removes_photo_and_documents(): void
    {
        $captain = Captain::factory()->create(['name' => 'Old Captain', 'photo_path' => 'captains/old.webp']);
        Storage::disk('public')->put('captains/old.webp', 'x');
        $this->actingAs($this->admin)->post("/admin/captains/{$captain->ulid}/documents", ['document' => UploadedFile::fake()->createWithContent('l.pdf', "%PDF-1.4\n%%EOF\n")]);
        $path = $captain->documents()->sole()->path;

        $this->delete("/admin/captains/{$captain->ulid}", ['reason' => 'Retired this spring.', 'password' => 'password', 'dialog' => 'trash-record'])->assertRedirect('/admin/captains');
        $this->assertSame(0, Captain::query()->active()->count());
        $this->delete("/admin/captains/{$captain->ulid}/permanent", ['reason' => 'Retired, no trips on record.', 'password' => 'password', 'dialog' => 'destroy-record'])->assertRedirect();

        $this->assertNull(Captain::withTrashed()->find($captain->id));
        Storage::disk('public')->assertMissing('captains/old.webp');
        Storage::disk('local')->assertMissing($path);
    }

    /* ------------------------------------------------------------------ users */

    public function test_the_users_list_filters_safely_and_shows_login_history(): void
    {
        $customer = User::factory()->create(['first_name' => 'Casey', 'last_name' => 'Customer', 'email' => 'casey@example.com']);
        User::factory()->owner()->create(['email' => 'owner@example.com']);
        foreach (range(1, 5) as $i) {
            LoginEvent::query()->create(['user_id' => $customer->id, 'email' => $customer->email, 'event' => LoginEventType::Failed, 'ip_address' => '203.0.113.9']);
        }
        $this->actingAs($this->admin);

        $this->get('/admin/users?role=owner')->assertSee('owner@example.com')->assertDontSee('casey@example.com');
        $this->get('/admin/users?q=casey')->assertSee('casey@example.com')->assertDontSee('owner@example.com');
        $this->get('/admin/users?role=superuser&status[]=x&sort=password')->assertOk()->assertSee('casey@example.com');

        $this->get("/admin/users/{$customer->ulid}")->assertOk()->assertSee('5 failed logins in the last 24 hours.')->assertSee('203.0.113.9');
        $this->get("/admin/users/{$customer->id}")->assertNotFound();
        $this->actingAs($customer)->get("/admin/users/{$customer->ulid}")->assertForbidden();
    }

    public function test_suspending_needs_a_reason_and_the_password_and_logs_the_person_out(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($this->admin)->from("/admin/users/{$customer->ulid}");

        $this->post("/admin/users/{$customer->ulid}/status", ['status' => 'suspended', 'dialog' => 'user-status'])->assertRedirect("/admin/users/{$customer->ulid}");
        $this->assertSame(UserStatus::Active, $customer->fresh()->status);

        $this->post("/admin/users/{$customer->ulid}/status", ['status' => 'suspended', 'reason' => 'Chargeback fraud reported.', 'password' => 'password', 'dialog' => 'user-status'])
            ->assertRedirect("/admin/users/{$customer->ulid}");
        $this->assertSame([UserStatus::Suspended, 'Chargeback fraud reported.'], [$customer->fresh()->status, $customer->fresh()->status_reason]);
        $this->assertSame('Chargeback fraud reported.', AuditLog::query()->where('action', 'user.status_changed')->sole()->reason);

        $this->post("/admin/users/{$this->admin->ulid}/status", ['status' => 'banned', 'reason' => 'Trying to ban myself.', 'password' => 'password'])->assertForbidden();
        $this->post("/admin/users/{$customer->ulid}/status", ['status' => 'emperor', 'reason' => 'Not a real status.', 'password' => 'password'])->assertRedirect();
        $this->assertSame(UserStatus::Suspended, $customer->fresh()->status);
    }

    public function test_the_admin_resets_someones_two_factor_login(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->withTwoFactor()->create();
        $this->actingAs($this->admin);

        $this->get("/admin/users/{$owner->ulid}")->assertSee('Reset two-factor');
        $this->post("/admin/users/{$owner->ulid}/two-factor-reset", ['reason' => 'Lost phone, identity checked by call.', 'password' => 'password', 'dialog' => 'user-two-factor'])
            ->assertRedirect("/admin/users/{$owner->ulid}");

        $this->assertFalse($owner->fresh()->hasEnabledTwoFactorAuthentication());
        Notification::assertSentTo($owner, TwoFactorChanged::class);
        $this->assertSame($this->admin->id, AuditLog::query()->where('action', 'user.two_factor_reset')->sole()->user_id);
    }

    /* ------------------------------------------------------------------ audit log + dashboard */

    public function test_the_audit_log_shows_who_what_and_why_with_safe_filters(): void
    {
        $boat = Boat::factory()->create(['title' => 'The Azure']);
        $this->actingAs($this->admin)->patchJson("/admin/boats/{$boat->ulid}/bookable", ['booking_enabled' => false, 'reason' => 'Engine service']);
        app(SettingsService::class)->update(['tax_rate_bp' => 950], $this->admin, 'City tax changed.');

        $this->get('/admin/audit-log')->assertOk()
            ->assertSee('boat.bookable_changed')->assertSee('Boats: The Azure')->assertSee('Ada Admin')
            ->assertSee('settings.updated')->assertSee('City tax changed.');
        $this->get('/admin/audit-log?area=settings')->assertSee('settings.updated')->assertDontSee('boat.bookable_changed');
        $this->get('/admin/audit-log?area=boat')->assertSee('boat.bookable_changed')->assertDontSee('settings.updated');
        $this->get('/admin/audit-log?who=system')->assertDontSee('boat.bookable_changed');
        $this->get('/admin/audit-log?area=%3Cscript%3E&q[]=x&who=everyone')->assertOk()->assertDontSee('<script>', false);

        $this->actingAs(User::factory()->owner()->create())->get('/admin/audit-log')->assertForbidden();
    }

    public function test_the_dashboard_shows_real_fleet_numbers_and_recent_activity(): void
    {
        Boat::factory()->create(['status' => BoatStatus::Published, 'booking_enabled' => false]);
        Captain::factory()->create(['is_active' => true, 'license_expires_on' => now()->addDays(10)]);
        app(SettingsService::class)->update(['hold_minutes' => 20], $this->admin, 'Longer checkout hold.');

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('Fleet &amp; catalog', false)
            ->assertSee('Shown as Unavailable')
            ->assertSee('Licenses expiring (30 days)')
            ->assertSee('Longer checkout hold.');
    }
}
