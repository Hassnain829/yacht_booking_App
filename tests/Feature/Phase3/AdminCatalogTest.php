<?php

namespace Tests\Feature\Phase3;

use App\Enums\AddonUnit;
use App\Models\Addon;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\Experience;
use App\Models\User;
use App\Support\IconNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.4.2 — Admin → Experiences and Add-ons: lists, add / edit with list fields (lengths, start times, days,
 * included items), prices in dollars → cents, the Trash pattern, and the §8.2 endpoint set.
 */
class AdminCatalogTest extends TestCase
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
    private function experience(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sunset Skyline Cruise',
            'slug' => '',
            'category' => 'sightseeing',
            'icon' => 'sunset',
            'tagline' => 'Golden hour over Lake Michigan',
            'description' => 'Watch the city light up.',
            'includes_text' => "Golden-hour departure\n\n  Skyline photo stop  \nIce, water & cups",
            'durations' => ['3', '2'],
            'time_slots_text' => '18:30, 17:30;18:00',
            'weekdays' => [],
            'season_starts_on' => '',
            'season_ends_on' => '',
            'package_fee' => '49.5',
            'is_active' => '1',
            'is_featured' => '0',
            'sort' => '1',
            'seo_title' => '',
            'seo_description' => '',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function addon(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Champagne toast',
            'slug' => '',
            'description' => 'Two bottles on ice',
            'icon' => 'wine',
            'price' => '120',
            'unit' => 'flat',
            'max_quantity' => '2',
            'is_active' => '1',
            'sort' => '1',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function sensitive(string $dialog, string $reason = 'Seasonal offer has ended.'): array
    {
        return ['reason' => $reason, 'password' => 'password', 'dialog' => $dialog];
    }

    public function test_only_the_admin_reaches_the_catalog_screens(): void
    {
        $experience = Experience::factory()->create();
        $addon = Addon::factory()->create();

        $this->get('/admin/experiences')->assertRedirect('/login');
        foreach ([User::factory()->create(), User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->get('/admin/experiences')->assertForbidden();
            $this->actingAs($user)->post('/admin/addons', $this->addon())->assertForbidden();
            $this->actingAs($user)->delete("/admin/experiences/{$experience->slug}", $this->sensitive('trash-record'))->assertForbidden();
            $this->actingAs($user)->put("/admin/addons/{$addon->slug}", $this->addon())->assertForbidden();
        }
        $this->actingAs($this->admin)->get('/admin/experiences/no-such-thing/edit')->assertNotFound();
        $this->get('/admin/addons/1/edit')->assertNotFound();
        $this->assertSame([1, 1], [Experience::query()->count(), Addon::query()->count()]);
    }

    /* ------------------------------------------------------------------ experiences */

    public function test_the_admin_adds_an_experience_with_its_lists_cleaned_and_sorted(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/experiences', $this->experience(['image' => UploadedFile::fake()->image('sunset.jpg', 1600, 1000)]))
            ->assertRedirect('/admin/experiences/sunset-skyline-cruise/edit');

        $experience = Experience::query()->sole();
        $this->assertSame(['Golden-hour departure', 'Skyline photo stop', 'Ice, water & cups'], $experience->includes);
        $this->assertSame([2, 3], $experience->durations);
        $this->assertSame(['17:30', '18:00', '18:30'], $experience->time_slots);
        $this->assertNull($experience->allowed_weekdays, 'no day ticked = every day');
        $this->assertSame(4950, $experience->package_fee_cents);
        $this->assertMatchesRegularExpression('#^experiences/[0-9a-f-]{36}\.webp$#', $experience->image_path);
        $this->assertSame(4950, AuditLog::query()->where('action', 'experience.created')->sole()->after['package_fee_cents']);

        $this->get('/admin/experiences')->assertOk()->assertSee('Sunset Skyline Cruise')->assertSee('$49.50');
    }

    public function test_days_season_and_every_day(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/experiences', $this->experience(['name' => 'Weekend Party', 'weekdays' => ['6', '5', '0'], 'season_starts_on' => '2026-05-15', 'season_ends_on' => '2026-09-30']))->assertRedirect();
        $party = Experience::query()->where('slug', 'weekend-party')->sole();
        $this->assertSame([0, 5, 6], $party->allowed_weekdays);
        $this->assertSame(['2026-05-15', '2026-09-30'], [$party->season_starts_on->toDateString(), $party->season_ends_on->toDateString()]);

        $this->put('/admin/experiences/weekend-party', $this->experience(['name' => 'Weekend Party', 'weekdays' => ['0', '1', '2', '3', '4', '5', '6']]))->assertRedirect();
        $this->assertNull($party->fresh()->allowed_weekdays, 'all seven ticked = every day');
    }

    public function test_invalid_experience_input_is_refused(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/admin/experiences', $this->experience([
            'name' => '<img src=x onerror=alert(1)>',
            'category' => 'superyacht-only',
            'icon' => 'skull',
            'durations' => ['7', '2', '2'],
            'time_slots_text' => '25:00, 9am',
            'weekdays' => ['8'],
            'season_starts_on' => '2026-09-30',
            'season_ends_on' => '2026-05-01',
            'package_fee' => '-5',
            'includes_text' => implode("\n", array_fill(0, 13, 'Item')),
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'name', 'category', 'icon', 'durations.0', 'durations.1', 'time_slots.0', 'time_slots.1', 'weekdays.0', 'season_ends_on', 'package_fee', 'includes',
        ]);

        $this->postJson('/admin/experiences', $this->experience(['durations' => [], 'time_slots_text' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['durations', 'time_slots']);
        $this->assertSame(0, Experience::query()->count());

        $this->from('/admin/experiences/create')->post('/admin/experiences', $this->experience(['time_slots_text' => '9am']))->assertRedirect('/admin/experiences/create');
        $this->get('/admin/experiences/create')->assertSee('Start times use the 24-hour format')->assertSee('value="9am"', false);
    }

    public function test_an_experience_moves_through_the_trash_and_its_photo_goes_with_a_permanent_delete(): void
    {
        $experience = Experience::factory()->create(['name' => 'Fireworks', 'slug' => 'fireworks', 'image_path' => 'experiences/fireworks.webp']);
        Storage::disk('public')->put('experiences/fireworks.webp', 'x');
        Boat::factory()->create()->experiences()->attach($experience);
        $this->actingAs($this->admin);

        $this->get('/admin/experiences/fireworks/edit')->assertOk()->assertSee('Offered by 1 boat.');
        $this->delete('/admin/experiences/fireworks', $this->sensitive('trash-record'))->assertRedirect('/admin/experiences');
        $this->assertSame(0, Experience::query()->active()->count());
        $this->assertSame(0, Boat::query()->sole()->experiences()->count(), 'a boat no longer offers an experience in the Trash');

        $this->post('/admin/experiences/fireworks/restore', $this->sensitive('restore-record', 'Back for the summer.'))->assertRedirect('/admin/experiences/fireworks/edit');
        $this->assertSame(1, Boat::query()->sole()->experiences()->count(), 'restoring brings the offer back');

        $experience->delete();
        $this->delete('/admin/experiences/fireworks/permanent', $this->sensitive('destroy-record', 'Not offered any more.'))->assertRedirect('/admin/experiences?view=trash');
        $this->assertNull(Experience::withTrashed()->find($experience->id));
        $this->assertSame(0, Boat::query()->sole()->experiences()->count());
        Storage::disk('public')->assertMissing('experiences/fireworks.webp');
        $this->assertSame(['experience.trashed', 'experience.restored', 'experience.deleted_permanently'], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    /* ------------------------------------------------------------------ add-ons */

    public function test_the_admin_adds_and_edits_an_add_on_with_prices_in_cents(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/addons', $this->addon())->assertRedirect('/admin/addons/champagne-toast/edit');
        $addon = Addon::query()->sole();
        $this->assertSame([12000, AddonUnit::Flat, 2], [$addon->price_cents, $addon->unit, $addon->max_quantity]);

        $this->put('/admin/addons/champagne-toast', $this->addon(['price' => '19.99', 'unit' => 'per_guest']))->assertRedirect();
        $this->assertSame([1999, AddonUnit::PerGuest], [$addon->fresh()->price_cents, $addon->fresh()->unit]);
        $log = AuditLog::query()->where('action', 'addon.updated')->sole();
        $this->assertEquals(['price_cents' => 12000, 'unit' => 'flat'], collect($log->before)->map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v)->all());

        $this->get('/admin/addons')->assertSee('$19.99 / guest');
        $this->postJson('/admin/addons', $this->addon())->assertUnprocessable()->assertJsonValidationErrors(['slug']); // double submit
        $this->postJson('/admin/addons', $this->addon(['name' => 'Other', 'unit' => 'per_hour', 'price' => '1.999', 'max_quantity' => '0', 'icon' => 'bomb']))
            ->assertUnprocessable()->assertJsonValidationErrors(['unit', 'price', 'max_quantity', 'icon']);
        $this->assertSame(1, Addon::query()->count());
    }

    public function test_an_add_on_in_the_trash_is_hidden_and_read_only(): void
    {
        $addon = Addon::factory()->create(['name' => 'Photographer', 'slug' => 'photographer']);
        $this->actingAs($this->admin);

        $this->delete('/admin/addons/photographer', $this->sensitive('trash-record'))->assertRedirect('/admin/addons');
        $this->assertSame(0, Addon::query()->active()->count());
        $this->get('/admin/addons')->assertDontSee('>Photographer</a>', false);
        $this->get('/admin/addons?view=trash')->assertSee('Photographer');
        $this->get('/admin/addons/photographer/edit')->assertOk()->assertSee('Restore the add-on to edit it.');
        $this->put('/admin/addons/photographer', $this->addon(['name' => 'Photographer']))->assertNotFound();
        $this->assertTrue($addon->fresh()->trashed());
    }

    /* ------------------------------------------------------------------ icons */

    public function test_every_icon_the_admin_can_pick_is_registered_for_the_browser(): void
    {
        $registry = file_get_contents(resource_path('js/lib/icons.js'));
        preg_match('/export const icons = \{(.*?)\};/s', $registry, $match);
        $registered = collect(preg_split('/[\s,]+/', $match[1]))->filter()
            ->map(fn (string $export) => strtolower(preg_replace(['/([a-z0-9])([A-Z])/', '/([a-zA-Z])(\d)/'], '$1-$2', $export)))
            ->all();

        $this->assertSame([], array_values(array_diff(IconNames::PICKER, $registered)), 'add these icons to resources/js/lib/icons.js');
        foreach ([Experience::factory()->make()->icon, Addon::factory()->make()->icon] as $icon) {
            $this->assertContains($icon, IconNames::PICKER);
        }
    }
}
