<?php

namespace Tests\Feature\Phase3;

use App\Enums\AmenityGroup;
use App\Enums\BoatOwnership;
use App\Enums\BoatStatus;
use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\BoatBlock;
use App\Models\CancellationPolicy;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\Media;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.4.3–3.4.4 — Admin → Boats: list + filters, add / edit with amenities, offered experiences and opening
 * hours, publish / hide, the Bookable switch (JSON), photos, blocks and the Trash; never status / owner from
 * the form (PLAN §8.2).
 */
class AdminBoatsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
        CancellationPolicy::factory()->create(['slug' => 'flexible', 'name' => 'Flexible', 'is_active' => true]);
        Harbor::factory()->create(['slug' => 'navy-pier', 'name' => 'Navy Pier']);
    }

    /** @return array<string, mixed> */
    private function fields(array $overrides = []): array
    {
        $hours = [];
        foreach (range(0, 6) as $day) {
            $hours[$day] = ['open' => $day === 1 ? '0' : '1', 'opens' => '09:00', 'closes' => '22:00'];
        }

        return array_replace_recursive([
            'title' => 'The Azure', 'slug' => '', 'short' => '62 ft motor yacht', 'description' => 'Flybridge lounge.', 'badge' => '',
            'type' => 'power', 'category' => 'Motor yacht', 'length_ft' => '62', 'max_guests' => '13', 'make' => 'Azimut', 'model' => '62',
            'year' => '2021', 'staterooms' => '3', 'sleeps' => '', 'beds' => '', 'heads' => '2', 'engine_count' => '2', 'engine_type' => 'Inboard',
            'horsepower' => '1600', 'engine_year' => '', 'uscg_inspected' => '1', 'registration_no' => '',
            'harbor' => 'navy-pier', 'street' => '', 'city' => 'Chicago', 'state' => 'IL', 'zip' => '60611', 'lat' => '', 'lng' => '',
            'hourly_rate' => '650', 'weekend_markup' => '1500', 'min_hours' => '3', 'durations' => ['4', '3'], 'duration_prices' => ['3' => '1800', '4' => '', '6' => '999'],
            'cleaning_fee' => '150', 'captain_rate' => '75.50', 'crew_count' => '2',
            'cancellation_policy' => 'flexible', 'advance_notice_minutes' => '1440', 'buffer_minutes' => '60', 'allow_multiple_bookings' => '1',
            'not_allowed' => ['Pets', 'Smoking'],
            'captain_source' => 'platform', 'renter_operated_allowed' => '0', 'fishing_charter' => '0', 'fuel_policy' => 'Included',
            'is_featured' => '1', 'sort' => '2', 'rating_avg' => '4.9', 'reviews_count' => '120',
            'booking_enabled' => '1', 'unavailable_reason' => '', 'extensions_enabled' => '1', 'publish' => '1',
            'hours' => $hours,
        ], $overrides);
    }

    public function test_only_the_admin_reaches_boats_and_bad_ids_are_404(): void
    {
        $boat = Boat::factory()->create();

        $this->get('/admin/boats')->assertRedirect('/login');
        $this->patchJson("/admin/boats/{$boat->ulid}/bookable", ['booking_enabled' => false])->assertUnauthorized();
        foreach ([User::factory()->create(), User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->get('/admin/boats')->assertForbidden();
            $this->actingAs($user)->patchJson("/admin/boats/{$boat->ulid}/bookable", ['booking_enabled' => false])->assertForbidden();
            $this->actingAs($user)->post("/admin/boats/{$boat->ulid}/status", ['action' => 'hide'])->assertForbidden();
        }
        $this->assertTrue($boat->fresh()->booking_enabled);

        $this->actingAs($this->admin)->get('/admin/boats/'.$boat->id.'/edit')->assertNotFound();
        $this->get('/admin/boats/01aaaaaaaaaaaaaaaaaaaaaaaa/edit')->assertNotFound();
        $this->get("/admin/boats/{$boat->ulid}/edit")->assertOk()->assertSee($boat->title);
    }

    public function test_the_list_filters_house_and_owner_boats_safely(): void
    {
        Boat::factory()->create(['title' => 'House Hero', 'status' => BoatStatus::Published]);
        $owned = Boat::factory()->create(['title' => 'Owner Boat', 'booking_enabled' => false]);
        $owned->forceFill(['ownership' => BoatOwnership::Owner, 'owner_id' => User::factory()->owner()->create(['first_name' => 'Marcus', 'last_name' => 'Reed'])->id, 'status' => BoatStatus::PendingReview])->save();
        $this->actingAs($this->admin);

        $this->get('/admin/boats')->assertOk()->assertSee('House Hero')->assertSee('Owner Boat')->assertSee('Marcus Reed');
        $this->get('/admin/boats?ownership=owner')->assertSee('Owner Boat')->assertDontSee('House Hero');
        $this->get('/admin/boats?status=review')->assertSee('Owner Boat')->assertDontSee('House Hero');
        $this->get('/admin/boats?bookable=no')->assertSee('Owner Boat')->assertDontSee('House Hero');
        $this->get('/admin/boats?ownership[]=x&status=%3Cscript%3E&sort=password&dir=up&bookable=maybe')->assertOk()->assertSee('House Hero')->assertDontSee('<script>', false);
    }

    /* ------------------------------------------------------------------ add / edit */

    public function test_the_admin_adds_a_house_boat_with_everything_it_offers(): void
    {
        $sunset = Experience::factory()->create(['slug' => 'sunset', 'name' => 'Sunset']);
        Experience::factory()->create(['slug' => 'fireworks', 'name' => 'Fireworks']);
        $wifi = Amenity::factory()->create(['name' => 'WiFi', 'group' => AmenityGroup::Feature]);
        $this->actingAs($this->admin)->get('/admin/boats/create')->assertOk()->assertSee('Weekly opening hours');

        $response = $this->post('/admin/boats', $this->fields([
            'amenities' => [$wifi->ulid],
            'experiences' => ['sunset' => ['offered' => '1', 'fee' => '25'], 'fireworks' => ['offered' => '0', 'fee' => '']],
            // never taken from the form:
            'status' => 'archived', 'ownership' => 'owner', 'owner_id' => (string) $this->admin->id, 'published_at' => '2001-01-01',
        ]));

        $boat = Boat::query()->sole();
        $response->assertRedirect("/admin/boats/{$boat->ulid}/edit");
        $this->assertSame([BoatOwnership::House, null, BoatStatus::Published], [$boat->ownership, $boat->owner_id, $boat->status]);
        $this->assertTrue($boat->published_at->isToday());
        $this->assertSame([65000, 11500, 15000, 7550], [$boat->hourly_rate_cents, $boat->weekend_multiplier_bp, $boat->cleaning_fee_cents, $boat->captain_rate_cents]);
        $this->assertSame([3, 4], $boat->durations);
        $this->assertSame(['3' => 180000], $boat->duration_prices, 'a price for a length the boat does not offer is dropped');
        $this->assertSame(['not_allowed' => ['Pets', 'Smoking']], $boat->rules);
        $this->assertSame(['navy-pier', 'flexible'], [$boat->harbor->slug, $boat->cancellationPolicy->slug]);
        $this->assertSame(['WiFi'], $boat->amenities->pluck('name')->all());
        $this->assertSame([$sunset->id => 2500], $boat->experiences->mapWithKeys(fn ($e) => [$e->id => $e->pivot->package_fee_override_cents])->all());
        $this->assertSame([true, false, true], $boat->availabilityRules()->pluck('is_open')->take(3)->all());
        $this->assertSame('09:00:00', (string) $boat->availabilityRules()->where('weekday', 0)->value('opens_at'));

        $log = AuditLog::query()->where('action', 'boat.created')->sole();
        $this->assertSame(['WiFi'], $log->after['amenities']);
        $this->assertSame(['sunset' => 2500], $log->after['experiences']);
    }

    public function test_a_demo_boat_with_its_own_category_saves_without_changes(): void
    {
        // Found in the 3.4 browser QA: the seeded boats use the mockup's categories ("Superyacht"), which are not in
        // the wizard's list, so the select came up empty and the browser refused to save the form.
        $boat = Boat::factory()->create(['category' => 'Superyacht']);
        $this->actingAs($this->admin)->get("/admin/boats/{$boat->ulid}/edit")->assertSee('<option value="Superyacht" selected>', false);

        $this->put("/admin/boats/{$boat->ulid}", $this->fields(['category' => 'Superyacht']))->assertRedirect("/admin/boats/{$boat->ulid}/edit");
        $this->assertSame('Superyacht', $boat->fresh()->category);
        $this->putJson("/admin/boats/{$boat->ulid}", $this->fields(['category' => 'Hovercraft']))->assertUnprocessable()->assertJsonValidationErrors(['category']);
        $this->postJson('/admin/boats', $this->fields(['category' => 'Superyacht']))->assertUnprocessable()->assertJsonValidationErrors(['category']);
    }

    public function test_a_boat_can_be_saved_as_a_draft(): void
    {
        $this->actingAs($this->admin)->post('/admin/boats', $this->fields(['publish' => '0']))->assertRedirect();

        $this->assertSame(BoatStatus::Draft, Boat::query()->sole()->status);
        $this->assertNull(Boat::query()->sole()->published_at);
    }

    public function test_invalid_boat_input_is_refused(): void
    {
        $this->actingAs($this->admin);
        Harbor::factory()->create(['slug' => 'old-dock'])->delete();

        $this->postJson('/admin/boats', $this->fields([
            'title' => '<b>Bold</b>', 'category' => 'Spaceship', 'type' => 'hover', 'hourly_rate' => '10', 'harbor' => 'old-dock',
            'cancellation_policy' => 'free-for-all', 'advance_notice_minutes' => '5', 'not_allowed' => ['Jet skis'], 'zip' => 'abc',
            'durations' => ['7'], 'weekend_markup' => '9999', 'rating_avg' => '6',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'title', 'category', 'type', 'hourly_rate', 'harbor', 'cancellation_policy', 'advance_notice_minutes', 'not_allowed.0', 'zip', 'durations.0', 'weekend_markup', 'rating_avg',
        ]);

        $this->postJson('/admin/boats', $this->fields(['length_ft' => '40', 'renter_operated_allowed' => '1']))
            ->assertUnprocessable()->assertJsonValidationErrors(['renter_operated_allowed']);
        $this->postJson('/admin/boats', $this->fields(['hours' => [2 => ['open' => '1', 'opens' => '20:00', 'closes' => '18:00']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['hours.2.closes']);
        $this->postJson('/admin/boats', $this->fields(['experiences' => ['no-such-thing' => ['offered' => '1', 'fee' => '']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['experiences']);

        $this->assertSame(0, Boat::query()->count());
    }

    public function test_editing_logs_only_what_changed_including_hours_and_offers(): void
    {
        $sunset = Experience::factory()->create(['slug' => 'sunset']);
        $this->actingAs($this->admin)->post('/admin/boats', $this->fields(['experiences' => ['sunset' => ['offered' => '1', 'fee' => '']]]));
        $boat = Boat::query()->sole();

        $this->put("/admin/boats/{$boat->ulid}", $this->fields(['experiences' => ['sunset' => ['offered' => '1', 'fee' => '']]]))->assertRedirect();
        $this->assertSame(0, AuditLog::query()->where('action', 'boat.updated')->count(), 'nothing changed');

        $this->put("/admin/boats/{$boat->ulid}", $this->fields([
            'hourly_rate' => '700',
            'experiences' => ['sunset' => ['offered' => '0', 'fee' => '']],
            'hours' => [1 => ['open' => '1', 'opens' => '10:00', 'closes' => '20:00']],
        ]))->assertRedirect("/admin/boats/{$boat->ulid}/edit");

        $log = AuditLog::query()->where('action', 'boat.updated')->sole();
        $this->assertSame(['hourly_rate_cents', 'experiences', 'hours'], array_keys($log->after));
        $this->assertSame([65000, 70000], [$log->before['hourly_rate_cents'], $log->after['hourly_rate_cents']]);
        $this->assertSame(['closed', '10:00–20:00'], [$log->before['hours'][1], $log->after['hours'][1]]);
        $this->assertSame(0, $boat->experiences()->count());
        $this->assertTrue($sunset->exists);
    }

    /* ------------------------------------------------------------------ status + bookable */

    public function test_publish_and_hide_a_house_boat(): void
    {
        $boat = Boat::factory()->create(['status' => BoatStatus::Draft]);
        $this->actingAs($this->admin);

        $this->post("/admin/boats/{$boat->ulid}/status", ['action' => 'publish'])->assertRedirect("/admin/boats/{$boat->ulid}/edit");
        $this->assertSame(BoatStatus::Published, $boat->fresh()->status);
        $this->assertNotNull($boat->fresh()->published_at);

        $this->post("/admin/boats/{$boat->ulid}/status", ['action' => 'hide'])->assertRedirect();
        $this->assertSame(BoatStatus::Paused, $boat->fresh()->status);
        $this->assertSame(2, AuditLog::query()->where('action', 'boat.status_changed')->count());

        $this->postJson("/admin/boats/{$boat->ulid}/status", ['action' => 'archive'])->assertUnprocessable();
    }

    public function test_hiding_an_owner_boat_suspends_it_with_a_reason_and_review_boats_wait_for_the_queue(): void
    {
        $boat = Boat::factory()->create();
        $boat->forceFill(['ownership' => BoatOwnership::Owner, 'owner_id' => User::factory()->owner()->create()->id, 'status' => BoatStatus::Published])->save();
        $this->actingAs($this->admin)->from("/admin/boats/{$boat->ulid}/edit");

        $this->post("/admin/boats/{$boat->ulid}/status", ['action' => 'hide'])->assertRedirect("/admin/boats/{$boat->ulid}/edit");
        $this->assertSame(BoatStatus::Published, $boat->fresh()->status, 'a reason is required for an owner boat');

        $this->post("/admin/boats/{$boat->ulid}/status", ['action' => 'hide', 'note' => 'Insurance expired last week.'])->assertRedirect();
        $this->assertSame(BoatStatus::Suspended, $boat->fresh()->status);
        $this->assertSame('Insurance expired last week.', AuditLog::query()->where('action', 'boat.status_changed')->sole()->reason);

        $boat->forceFill(['status' => BoatStatus::PendingReview])->save();
        $this->post("/admin/boats/{$boat->ulid}/status", ['action' => 'publish'])->assertRedirect();
        $this->assertSame(BoatStatus::PendingReview, $boat->fresh()->status);
        $this->get("/admin/boats/{$boat->ulid}/edit")->assertSee('Approve it from Listing reviews first.');
    }

    public function test_the_bookable_switch_answers_with_only_the_tag_state(): void
    {
        $boat = Boat::factory()->create(['title' => 'The Azure']);
        $this->actingAs($this->admin);

        $this->patchJson("/admin/boats/{$boat->ulid}/bookable", ['booking_enabled' => false, 'reason' => 'Engine service'])
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $boat->ulid, 'title' => 'The Azure', 'booking_enabled' => false, 'unavailable_reason' => 'Engine service']]);
        $this->assertFalse($boat->fresh()->booking_enabled);
        $this->assertSame(['booking_enabled' => false, 'unavailable_reason' => 'Engine service'], AuditLog::query()->where('action', 'boat.bookable_changed')->sole()->after);

        $this->patchJson("/admin/boats/{$boat->ulid}/bookable", ['booking_enabled' => 'sometimes'])->assertUnprocessable()->assertJsonValidationErrors(['booking_enabled']);
        $this->patchJson("/admin/boats/{$boat->ulid}/bookable", ['booking_enabled' => true])->assertOk()->assertJsonPath('data.unavailable_reason', null);
        $this->get('/admin/boats')->assertSee('data-bookable-toggle', false);
    }

    /* ------------------------------------------------------------------ photos */

    public function test_photos_upload_reorder_describe_and_delete(): void
    {
        $boat = Boat::factory()->create();
        $this->actingAs($this->admin);

        $this->post("/admin/boats/{$boat->ulid}/photos", ['photos' => [UploadedFile::fake()->image('a.jpg', 1200, 800), UploadedFile::fake()->image('b.png', 1200, 800)]])
            ->assertRedirect("/admin/boats/{$boat->ulid}/edit#photos");
        [$first, $second] = $boat->photos()->get()->all();
        $this->assertSame([0, 1], [$first->sort, $second->sort]);
        $this->assertStringEndsWith('.webp', $first->path);
        $this->assertSame($boat->title, $first->alt);

        $this->patch("/admin/boats/{$boat->ulid}/photos/{$second->uuid}", ['move' => 'first'])->assertRedirect();
        $this->assertSame([$second->uuid, $first->uuid], $boat->photos()->pluck('uuid')->all(), 'the second photo is the cover now');

        $this->patch("/admin/boats/{$boat->ulid}/photos/{$first->uuid}", ['alt' => 'Flybridge at sunset'])->assertRedirect();
        $this->assertSame('Flybridge at sunset', $first->fresh()->alt);

        $this->delete("/admin/boats/{$boat->ulid}/photos/{$second->uuid}")->assertRedirect();
        $this->assertSame([[$first->uuid, 0]], $boat->photos()->get()->map(fn (Media $m) => [$m->uuid, $m->sort])->all());
        foreach ($second->paths() as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        $this->assertSame(['boat.photos_added', 'boat.photo_changed', 'boat.photo_changed', 'boat.photo_deleted'], AuditLog::query()->orderBy('id')->pluck('action')->all());

        $other = Boat::factory()->create();
        $this->patch("/admin/boats/{$other->ulid}/photos/{$first->uuid}", ['move' => 'up'])->assertNotFound();
        $this->delete("/admin/boats/{$other->ulid}/photos/{$first->uuid}")->assertNotFound();
        $this->assertNotNull($first->fresh());
    }

    public function test_bad_photos_and_too_many_photos_are_refused(): void
    {
        $boat = Boat::factory()->create();
        $this->actingAs($this->admin);

        $this->postJson("/admin/boats/{$boat->ulid}/photos", ['photos' => [UploadedFile::fake()->createWithContent('boat.jpg', '<?php echo "owned"; ?>')]])
            ->assertUnprocessable()->assertJsonValidationErrors(['photos.0']);
        $this->postJson("/admin/boats/{$boat->ulid}/photos", ['photos' => array_fill(0, 11, UploadedFile::fake()->image('x.jpg', 900, 600))])
            ->assertUnprocessable()->assertJsonValidationErrors(['photos']);

        $media = app(MediaService::class);
        foreach (range(1, 29) as $i) {
            $media->addImage($boat, 'photos', UploadedFile::fake()->image("p{$i}.jpg", 700, 500));
        }
        $this->postJson("/admin/boats/{$boat->ulid}/photos", ['photos' => [UploadedFile::fake()->image('a.jpg', 900, 600), UploadedFile::fake()->image('b.jpg', 900, 600)]])
            ->assertUnprocessable()->assertJsonValidationErrors(['photos']);
        $this->assertSame(29, $boat->photos()->count());
    }

    /* ------------------------------------------------------------------ blocks */

    public function test_blocks_are_entered_in_chicago_time_and_scoped_to_their_boat(): void
    {
        $boat = Boat::factory()->create();
        $this->actingAs($this->admin);

        $this->post("/admin/boats/{$boat->ulid}/blocks", ['starts_at' => '2026-12-24T08:00', 'ends_at' => '2026-12-26T20:00', 'type' => 'maintenance', 'reason' => 'Winter service'])
            ->assertRedirect("/admin/boats/{$boat->ulid}/edit#blocks");
        $block = BoatBlock::query()->sole();
        $this->assertSame(['2026-12-24 14:00:00', '2026-12-27 02:00:00'], [$block->starts_at->utc()->format('Y-m-d H:i:s'), $block->ends_at->utc()->format('Y-m-d H:i:s')]);
        $this->assertSame([$boat->id, $this->admin->id], [$block->boat_id, $block->created_by]);
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $block->ulid);

        $this->postJson("/admin/boats/{$boat->ulid}/blocks", ['starts_at' => '2026-12-24T08:00', 'ends_at' => '2026-12-24T07:00', 'type' => 'owner_block'])
            ->assertUnprocessable()->assertJsonValidationErrors(['ends_at', 'type']);

        $other = Boat::factory()->create();
        $this->delete("/admin/boats/{$other->ulid}/blocks/{$block->ulid}")->assertNotFound();
        $this->delete("/admin/boats/{$boat->ulid}/blocks/{$block->ulid}")->assertRedirect();
        $this->assertSame(0, BoatBlock::query()->count());
        $this->assertSame(['boat.block_added', 'boat.block_removed'], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    /* ------------------------------------------------------------------ trash */

    public function test_a_boat_goes_to_the_trash_and_a_permanent_delete_removes_its_photos_and_links(): void
    {
        $boat = Boat::factory()->create(['title' => 'Old Boat']);
        $boat->experiences()->attach(Experience::factory()->create());
        $boat->amenities()->attach(Amenity::factory()->create());
        $photo = app(MediaService::class)->addImage($boat, 'photos', UploadedFile::fake()->image('a.jpg', 900, 600));
        $this->actingAs($this->admin);

        $this->delete("/admin/boats/{$boat->ulid}", ['reason' => 'Sold to another marina.', 'password' => 'password', 'dialog' => 'trash-record'])->assertRedirect('/admin/boats');
        $this->assertSame(0, Boat::query()->listed()->count());
        $this->get('/admin/boats?view=trash')->assertSee('Old Boat');
        Storage::disk('public')->assertExists($photo->path);

        $this->delete("/admin/boats/{$boat->ulid}/permanent", ['reason' => 'Sold, records not needed.', 'password' => 'password', 'dialog' => 'destroy-record'])
            ->assertRedirect('/admin/boats?view=trash');
        $this->assertNull(Boat::withTrashed()->find($boat->id));
        $this->assertSame(0, Media::query()->count());
        $this->assertDatabaseCount('boat_experience', 0);
        $this->assertDatabaseCount('amenity_boat', 0);
        foreach ($photo->paths() as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }
}
