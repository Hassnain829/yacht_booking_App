<?php

namespace Tests\Feature\Phase3;

use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\Harbor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.4.1 — Admin → Harbors and the shared admin pieces it proves: the Trash pattern (TrashService),
 * sensitive actions (reason + password in the dialog), audit entries with before / after, whitelisted lists
 * and the §8.2 endpoint set (guest, wrong role, 404, 422, valid, 429, duplicate).
 */
class AdminHarborsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function fields(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Montrose Harbor',
            'slug' => '',
            'area' => 'Uptown',
            'address' => '601 W Montrose Dr, Chicago, IL',
            'map_query' => 'Montrose Harbor Chicago',
            'notes' => 'Park in lot B; we meet you at the fuel dock.',
            'lat' => '41.963400',
            'lng' => '-87.636900',
            'is_active' => '1',
            'sort' => '5',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function sensitive(array $overrides = []): array
    {
        return array_merge(['reason' => 'Duplicate of the Navy Pier entry.', 'password' => 'password', 'dialog' => 'trash-record'], $overrides);
    }

    /* ------------------------------------------------------------------ access */

    public function test_guests_go_to_login_and_other_roles_are_refused(): void
    {
        $harbor = Harbor::factory()->create();

        $this->get('/admin/harbors')->assertRedirect('/login');
        $this->post('/admin/harbors', $this->fields())->assertRedirect('/login');
        $this->deleteJson("/admin/harbors/{$harbor->slug}", $this->sensitive())->assertUnauthorized();

        foreach ([User::factory()->create(), User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->get('/admin/harbors')->assertForbidden();
            $this->actingAs($user)->post('/admin/harbors', $this->fields())->assertForbidden();
            $this->actingAs($user)->delete("/admin/harbors/{$harbor->slug}", $this->sensitive())->assertForbidden();
        }

        $this->assertSame(1, Harbor::query()->count());
        $this->assertFalse($harbor->fresh()->trashed());
    }

    public function test_unknown_or_malformed_harbors_are_404(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/harbors/no-such-harbor/edit')->assertNotFound();
        $this->get('/admin/harbors/1/edit')->assertNotFound();
        $this->get('/admin/harbors/Navy_Pier/edit')->assertNotFound();
        $this->put('/admin/harbors/no-such-harbor', $this->fields())->assertNotFound();
    }

    /* ------------------------------------------------------------------ list */

    public function test_photos_load_from_the_host_the_page_came_from(): void
    {
        // Found by the user (2026-11-23): APP_URL = localhost:8000, browser on 127.0.0.1:8000 → absolute image
        // URLs pointed at another origin, and the CSP (img-src 'self') blocked every photo.
        config(['app.url' => 'http://localhost:8000']);
        Harbor::factory()->create(['slug' => 'navy-pier', 'image_path' => 'harbors/navy-pier.webp']);

        $this->actingAs($this->admin)->get('http://127.0.0.1:8000/admin/harbors')
            ->assertOk()
            ->assertSee('src="/storage/harbors/navy-pier.webp"', false);
        $this->get('http://127.0.0.1:8000/admin/harbors/navy-pier/edit')->assertSee('src="/storage/harbors/navy-pier.webp"', false);
    }

    public function test_the_list_shows_harbors_with_their_boats_and_searches_and_sorts_safely(): void
    {
        $pier = Harbor::factory()->create(['name' => 'Navy Pier', 'slug' => 'navy-pier', 'area' => 'Streeterville', 'sort' => 2]);
        Harbor::factory()->create(['name' => 'Burnham Harbor', 'slug' => 'burnham-harbor', 'area' => 'Museum Campus', 'sort' => 1, 'is_active' => false]);
        Boat::factory()->count(2)->create(['harbor_id' => $pier->id]);

        $html = $this->actingAs($this->admin)->get('/admin/harbors')->assertOk()->assertSee('Navy Pier')->assertSee('Hidden')->getContent();
        $this->assertLessThan(strpos($html, '>Navy Pier</a>'), strpos($html, '>Burnham Harbor</a>'), 'ordered by "Order" first');
        $this->assertMatchesRegularExpression('/Navy Pier<\/a>.*?<\/td>\s*<td>2<\/td>/s', $html, 'boat count');

        $this->get('/admin/harbors?q=museum')->assertOk()->assertSee('Burnham Harbor')->assertDontSee('>Navy Pier</a>', false);
        $this->get('/admin/harbors?q=%25')->assertOk()->assertSee('No harbors match'); // "%" is searched literally
        $this->get('/admin/harbors?q[]=x&sort=password&dir=sideways&view=everything')->assertOk()->assertSee('Navy Pier');
        $this->get('/admin/harbors?sort=boats&dir=desc')->assertOk()->assertSee('aria-sort="descending"', false);
    }

    /* ------------------------------------------------------------------ add */

    public function test_the_admin_adds_a_harbor_with_a_photo(): void
    {
        $this->actingAs($this->admin)->get('/admin/harbors/create')->assertOk()->assertSee('Add a harbor');

        $response = $this->post('/admin/harbors', $this->fields(['image' => UploadedFile::fake()->image('dock.jpg', 1200, 800)]));

        $harbor = Harbor::query()->sole();
        $response->assertRedirect('/admin/harbors/montrose-harbor/edit');
        $this->assertSame(['montrose-harbor', 'Uptown', '41.963400', true, 5], [$harbor->slug, $harbor->area, $harbor->lat, $harbor->is_active, $harbor->sort]);
        $this->assertMatchesRegularExpression('#^harbors/[0-9a-f-]{36}\.webp$#', $harbor->image_path);
        Storage::disk('public')->assertExists($harbor->image_path);

        $log = AuditLog::query()->where('action', 'harbor.created')->sole();
        $this->assertSame([$this->admin->id, 'harbor', $harbor->id], [$log->user_id, $log->subject_type, $log->subject_id]);
        $this->assertSame('Montrose Harbor', $log->after['name']);
        $this->assertNull($log->before);

        $this->get("/admin/harbors/{$harbor->slug}/edit")->assertOk()->assertSee('Montrose Harbor was added.');
    }

    public function test_invalid_input_is_refused_with_clear_messages(): void
    {
        $this->actingAs($this->admin)->from('/admin/harbors/create');

        $this->post('/admin/harbors', $this->fields([
            'name' => '<script>alert(1)</script>',
            'slug' => 'Bad Slug!',
            'lat' => '41.9',
            'lng' => '',
            'sort' => '-3',
            'is_active' => 'maybe',
            'notes' => str_repeat('a', 1001),
        ]))->assertRedirect('/admin/harbors/create');

        $this->get('/admin/harbors/create')
            ->assertSee('Please use plain text here (no HTML).')
            ->assertSee('Use lowercase letters, numbers and single dashes only')
            ->assertSee('Enter both latitude and longitude, or neither.');
        $this->assertSame(0, Harbor::query()->count());

        $this->postJson('/admin/harbors', $this->fields(['name' => '', 'sort' => 'abc', 'image' => UploadedFile::fake()->create('shell.jpg', 2, 'image/jpeg')]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sort', 'image']);
        $this->assertSame(0, Harbor::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'a refused file is never stored');
    }

    public function test_saving_the_same_new_harbor_twice_does_not_create_two(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/harbors', $this->fields())->assertRedirect('/admin/harbors/montrose-harbor/edit');
        $this->postJson('/admin/harbors', $this->fields())->assertUnprocessable()->assertJsonValidationErrors(['slug']);

        $this->assertSame(1, Harbor::query()->count());
    }

    public function test_a_web_address_used_by_a_harbor_in_the_trash_is_still_taken(): void
    {
        Harbor::factory()->create(['name' => 'Montrose Harbor', 'slug' => 'montrose-harbor'])->delete();

        $this->actingAs($this->admin)->postJson('/admin/harbors', $this->fields())
            ->assertUnprocessable()
            ->assertJsonPath('errors.slug.0', 'Another harbor already uses this web address (it may be in the Trash). Choose a different name or address.');
    }

    /* ------------------------------------------------------------------ edit */

    public function test_editing_logs_only_what_changed_and_follows_a_new_web_address(): void
    {
        Harbor::factory()->create(['name' => 'Montrose', 'slug' => 'montrose', 'area' => 'Uptown', 'map_query' => 'Montrose Harbor Chicago', 'sort' => 5, 'lat' => '41.963400', 'lng' => '-87.636900']);
        $this->actingAs($this->admin);

        $this->put('/admin/harbors/montrose', $this->fields(['name' => 'Montrose', 'slug' => 'montrose', 'address' => null, 'map_query' => 'Montrose Harbor Chicago', 'notes' => null, 'area' => 'Uptown']))
            ->assertRedirect('/admin/harbors/montrose/edit');
        $this->assertSame(0, AuditLog::query()->where('action', 'harbor.updated')->count(), 'nothing changed → no entry');

        $this->put('/admin/harbors/montrose', $this->fields(['name' => 'Montrose Harbor', 'slug' => 'montrose-harbor', 'area' => 'Uptown', 'address' => null, 'notes' => null]))
            ->assertRedirect('/admin/harbors/montrose-harbor/edit');

        $log = AuditLog::query()->where('action', 'harbor.updated')->sole();
        $this->assertEquals(['name' => 'Montrose', 'slug' => 'montrose'], $log->before);
        $this->assertEquals(['name' => 'Montrose Harbor', 'slug' => 'montrose-harbor'], $log->after);
        $this->get('/admin/harbors/montrose-harbor/edit')->assertOk()->assertSee('Changes saved.');
        $this->get('/admin/harbors/montrose/edit')->assertNotFound();
    }

    public function test_a_new_photo_replaces_the_old_file_and_remove_deletes_it(): void
    {
        $harbor = Harbor::factory()->create(['slug' => 'montrose-harbor', 'name' => 'Montrose Harbor']);
        $this->actingAs($this->admin);

        $this->put("/admin/harbors/{$harbor->slug}", $this->fields(['image' => UploadedFile::fake()->image('a.jpg', 900, 600)]))->assertRedirect();
        $first = $harbor->fresh()->image_path;
        Storage::disk('public')->assertExists($first);

        $this->put("/admin/harbors/{$harbor->slug}", $this->fields(['image' => UploadedFile::fake()->image('b.png', 900, 600)]))->assertRedirect();
        $second = $harbor->fresh()->image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->put("/admin/harbors/{$harbor->slug}", $this->fields(['remove_image' => '1']))->assertRedirect();
        $this->assertNull($harbor->fresh()->image_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_a_refused_photo_leaves_the_harbor_and_its_photo_unchanged(): void
    {
        $harbor = Harbor::factory()->create(['slug' => 'montrose-harbor', 'name' => 'Montrose Harbor', 'image_path' => 'harbors/old.webp']);
        Storage::disk('public')->put('harbors/old.webp', 'x');

        $this->actingAs($this->admin)->putJson("/admin/harbors/{$harbor->slug}", $this->fields([
            'name' => 'Changed',
            'image' => UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "owned"; ?>'),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['image']);

        $this->assertSame(['Montrose Harbor', 'harbors/old.webp'], [$harbor->fresh()->name, $harbor->fresh()->image_path]);
        $this->assertSame(['harbors/old.webp'], Storage::disk('public')->allFiles());
    }

    /* ------------------------------------------------------------------ trash */

    public function test_moving_to_the_trash_needs_a_reason_and_the_password(): void
    {
        $harbor = Harbor::factory()->create();
        $this->actingAs($this->admin)->from("/admin/harbors/{$harbor->slug}/edit");

        $this->delete("/admin/harbors/{$harbor->slug}", ['dialog' => 'trash-record'])->assertRedirect();
        $this->delete("/admin/harbors/{$harbor->slug}", $this->sensitive(['reason' => 'short']))->assertRedirect();
        $this->delete("/admin/harbors/{$harbor->slug}", $this->sensitive(['password' => 'wrong-password']))->assertRedirect();
        $this->assertFalse($harbor->fresh()->trashed());

        $this->get("/admin/harbors/{$harbor->slug}/edit")
            ->assertSee('That password is not correct.')
            ->assertSee('data-open-on-load', false); // the dialog opens again with its errors

        $this->delete("/admin/harbors/{$harbor->slug}", $this->sensitive())->assertRedirect('/admin/harbors');
        $this->assertTrue($harbor->fresh()->trashed());

        $log = AuditLog::query()->where('action', 'harbor.trashed')->sole();
        $this->assertSame(['Duplicate of the Navy Pier entry.', $this->admin->id], [$log->reason, $log->user_id]);
        $this->assertNotNull(session('auth.password_confirmed_at'), 'a correct password counts as a fresh confirmation');
    }

    public function test_after_a_recent_password_confirmation_only_the_reason_is_needed(): void
    {
        $harbor = Harbor::factory()->create();

        $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time() - 60])
            ->delete("/admin/harbors/{$harbor->slug}", ['reason' => 'No longer a departure point.'])
            ->assertRedirect('/admin/harbors');
        $this->assertTrue($harbor->fresh()->trashed());

        $other = Harbor::factory()->create();
        $this->withSession(['auth.password_confirmed_at' => time() - 3 * 3600 - 1])
            ->deleteJson("/admin/harbors/{$other->slug}", ['reason' => 'No longer a departure point.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_a_harbor_that_boats_depart_from_cannot_go_to_the_trash(): void
    {
        $harbor = Harbor::factory()->create();
        Boat::factory()->create(['harbor_id' => $harbor->id]);
        $this->actingAs($this->admin)->from("/admin/harbors/{$harbor->slug}/edit");

        $this->get("/admin/harbors/{$harbor->slug}/edit")->assertSee('1 boat departs from this harbor.');
        $this->delete("/admin/harbors/{$harbor->slug}", $this->sensitive())->assertRedirect("/admin/harbors/{$harbor->slug}/edit");

        $this->assertFalse($harbor->fresh()->trashed());
        $this->assertSame(0, AuditLog::query()->where('action', 'harbor.trashed')->count());
    }

    public function test_a_harbor_in_the_trash_is_hidden_read_only_and_can_be_restored(): void
    {
        $harbor = Harbor::factory()->create(['name' => 'Old Dock', 'slug' => 'old-dock']);
        $harbor->delete();
        $this->actingAs($this->admin);

        $this->assertSame(0, Harbor::query()->active()->count());
        $this->get('/admin/harbors')->assertDontSee('Old Dock');
        $this->get('/admin/harbors?view=trash')->assertSee('Old Dock')->assertSee('In the Trash');
        $this->get('/admin/harbors/old-dock/edit')->assertOk()->assertSee('Restore the harbor to edit it.')->assertDontSee('name="name"', false);
        $this->put('/admin/harbors/old-dock', $this->fields(['name' => 'Old Dock']))->assertNotFound();

        $this->post('/admin/harbors/old-dock/restore', $this->sensitive(['reason' => 'Trashed by mistake.', 'dialog' => 'restore-record']))
            ->assertRedirect('/admin/harbors/old-dock/edit');
        $this->assertFalse($harbor->fresh()->trashed());
        $this->assertSame('Trashed by mistake.', AuditLog::query()->where('action', 'harbor.restored')->sole()->reason);
    }

    public function test_delete_permanently_works_only_from_the_trash_and_removes_the_row_and_its_photo(): void
    {
        $harbor = Harbor::factory()->create(['name' => 'Old Dock', 'slug' => 'old-dock', 'image_path' => 'harbors/old-dock.webp']);
        Storage::disk('public')->put('harbors/old-dock.webp', 'x');
        $this->actingAs($this->admin)->from('/admin/harbors/old-dock/edit');

        $this->delete('/admin/harbors/old-dock/permanent', $this->sensitive(['dialog' => 'destroy-record']))->assertRedirect('/admin/harbors/old-dock/edit');
        $this->assertNotNull(Harbor::withTrashed()->find($harbor->id), 'not in the Trash yet → refused');

        $harbor->delete();
        $this->delete('/admin/harbors/old-dock/permanent', $this->sensitive(['reason' => 'Closed for good in 2026.', 'dialog' => 'destroy-record']))
            ->assertRedirect('/admin/harbors?view=trash');

        $this->assertNull(Harbor::withTrashed()->find($harbor->id));
        Storage::disk('public')->assertMissing('harbors/old-dock.webp');
        $log = AuditLog::query()->where('action', 'harbor.deleted_permanently')->sole();
        $this->assertSame(['name' => 'Old Dock', 'slug' => 'old-dock'], $log->before);
        $this->assertSame('Closed for good in 2026.', $log->reason);
    }

    public function test_a_harbor_still_used_by_a_boat_in_the_trash_cannot_be_deleted_permanently(): void
    {
        $harbor = Harbor::factory()->create();
        Boat::factory()->create(['harbor_id' => $harbor->id])->delete();
        $harbor->delete();
        $this->actingAs($this->admin)->from("/admin/harbors/{$harbor->slug}/edit");

        $this->get("/admin/harbors/{$harbor->slug}/edit")->assertSee('1 boat (in the Trash or not) still uses this harbor.');
        $this->delete("/admin/harbors/{$harbor->slug}/permanent", $this->sensitive(['dialog' => 'destroy-record']))->assertRedirect();

        $this->assertNotNull(Harbor::withTrashed()->find($harbor->id));
    }

    /* ------------------------------------------------------------------ abuse */

    public function test_sensitive_actions_are_rate_limited(): void
    {
        $harbor = Harbor::factory()->create();
        Boat::factory()->create(['harbor_id' => $harbor->id]); // every try is refused, so the harbor stays
        $this->actingAs($this->admin);

        for ($i = 0; $i < 10; $i++) {
            $this->deleteJson("/admin/harbors/{$harbor->slug}", $this->sensitive(['password' => 'guess-'.$i]))->assertUnprocessable();
        }
        $this->deleteJson("/admin/harbors/{$harbor->slug}", $this->sensitive())->assertTooManyRequests();
    }
}
