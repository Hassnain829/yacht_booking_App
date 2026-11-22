<?php

namespace Tests\Feature\Phase3;

use App\Enums\AddonUnit;
use App\Enums\BoatOwnership;
use App\Enums\BoatStatus;
use App\Enums\LicenseType;
use App\Models\Addon;
use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\BoatAvailabilityRule;
use App\Models\BoatBlock;
use App\Models\CancellationPolicy;
use App\Models\Captain;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3.1 — the catalog tables and models (PLAN §6.2–6.4, §6.7): protected fields, public ids,
 * relations, the listed / bookable rules, captains, refund rules, blocks, morph names, constraints.
 */
class CatalogModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_can_never_set_a_boats_status_owner_or_id(): void
    {
        $owner = User::factory()->owner()->create();
        $boat = new Boat(['title' => 'Blue Note', 'slug' => 'blue-note', 'status' => 'published', 'ownership' => 'owner', 'owner_id' => $owner->id, 'ulid' => 'hacked', 'published_at' => now()]);
        $boat->save();

        $this->assertSame(BoatStatus::Draft, $boat->status);
        $this->assertSame(BoatOwnership::House, $boat->ownership);
        $this->assertNull($boat->owner_id);
        $this->assertNull($boat->published_at);
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $boat->ulid);
        $this->assertSame('ulid', $boat->getRouteKeyName());
        $this->assertArrayNotHasKey('id', $boat->toArray(), 'the numeric id never leaves the server');
    }

    public function test_boat_relations(): void
    {
        $harbor = Harbor::factory()->create();
        $policy = CancellationPolicy::factory()->flexible()->create();
        $boat = Boat::factory()->create(['harbor_id' => $harbor->id, 'cancellation_policy_id' => $policy->id]);
        $sunset = Experience::factory()->create();
        $boat->experiences()->attach($sunset, ['package_fee_override_cents' => 5000]);
        $boat->amenities()->attach(Amenity::factory()->highlight()->create(['name' => 'Flybridge sun lounge']));
        $boat->availabilityRules()->create(['weekday' => 6, 'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '21:00']);
        $boat->photos()->create(['collection' => 'photos', 'disk' => 'public', 'path' => 'boats/a.jpg', 'mime' => 'image/jpeg', 'size_bytes' => 1000, 'sort' => 1]);
        $boat->photos()->create(['collection' => 'photos', 'disk' => 'public', 'path' => 'boats/cover.jpg', 'mime' => 'image/jpeg', 'size_bytes' => 1000, 'sort' => 0]);

        $boat->refresh();
        $this->assertTrue($boat->harbor->is($harbor));
        $this->assertSame(100, $boat->cancellationPolicy->refundPercent(48));
        $this->assertSame(5000, $boat->experiences->first()->pivot->package_fee_override_cents);
        $this->assertTrue($sunset->boats->first()->is($boat));
        $this->assertSame(['Flybridge sun lounge'], $boat->amenities->pluck('name')->all());
        $this->assertSame(6, $boat->availabilityRules->first()->weekday);
        $this->assertSame(['boats/cover.jpg', 'boats/a.jpg'], $boat->photos->pluck('path')->all(), 'cover first');
        $this->assertSame('boat', DB::table('media')->value('model_type'), 'a short morph name, never a class name');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $boat->photos->first()->uuid);
    }

    public function test_only_published_boats_are_listed_and_the_unavailable_tag_stops_bookings(): void
    {
        $open = Boat::factory()->create();
        $tagged = Boat::factory()->unavailable()->create();
        Boat::factory()->draft()->create();

        $this->assertEqualsCanonicalizing([$open->id, $tagged->id], Boat::query()->listed()->pluck('id')->all());
        $this->assertSame([$open->id], Boat::query()->bookable()->pluck('id')->all());
        $this->assertTrue($open->isBookable());
        $this->assertFalse($tagged->isBookable());
        $this->assertSame('Not taking bookings this season', $tagged->unavailable_reason);
    }

    public function test_owner_boats_belong_to_their_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $boat = Boat::factory()->ownedBy($owner)->create();
        Boat::factory()->create(); // a house boat

        $this->assertSame([$boat->id], $owner->boats()->pluck('id')->all());
        $this->assertFalse($boat->isHouse());
        $this->assertTrue($boat->owner->is($owner));
    }

    public function test_the_captains_license_number_is_encrypted_and_private_fields_are_hidden(): void
    {
        $captain = Captain::factory()->create(['license_number' => 'ML-4471902', 'pay_rate_cents' => 4500]);

        $this->assertSame('ML-4471902', $captain->fresh()->license_number);
        $this->assertStringNotContainsString('4471902', (string) DB::table('captains')->value('license_number'), 'encrypted at rest');
        $this->assertArrayNotHasKey('license_number', $captain->toArray());
        $this->assertArrayNotHasKey('pay_rate_cents', $captain->toArray());
        $this->assertSame('ulid', $captain->getRouteKeyName());
    }

    public function test_a_captain_takes_only_the_guests_and_dates_their_license_allows(): void
    {
        $sixPack = Captain::factory()->oupv()->create(['license_expires_on' => now()->addMonth()->toDateString()]);

        $this->assertSame(LicenseType::Oupv, $sixPack->license_type);
        $this->assertTrue($sixPack->canTake(6, now()->addWeek()));
        $this->assertFalse($sixPack->canTake(7, now()->addWeek()), 'an OUPV captain carries at most 6');
        $this->assertFalse($sixPack->canTake(4, now()->addMonths(2)), 'license expired by the trip day');
    }

    public function test_refund_percent_follows_the_policy_rules(): void
    {
        $flexible = CancellationPolicy::factory()->flexible()->make();
        $moderate = CancellationPolicy::factory()->make();
        $strict = CancellationPolicy::factory()->strict()->make();

        $this->assertSame([100, 0], [$flexible->refundPercent(24), $flexible->refundPercent(23.9)]);
        $this->assertSame([100, 50, 0], [$moderate->refundPercent(120), $moderate->refundPercent(100), $moderate->refundPercent(71)]);
        $this->assertSame([100, 50, 0], [$strict->refundPercent(800), $strict->refundPercent(400), $strict->refundPercent(359)]);
    }

    public function test_blocks_overlap_for_the_boat_and_for_every_boat(): void
    {
        $boat = Boat::factory()->create();
        $start = now()->addDays(3)->setTime(12, 0);
        BoatBlock::factory()->create(['boat_id' => $boat->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHours(2)]);
        BoatBlock::factory()->everyBoat()->create(['starts_at' => $start->copy()->addHour(), 'ends_at' => $start->copy()->addHours(5)]);
        BoatBlock::factory()->create(['starts_at' => $start, 'ends_at' => $start->copy()->addHours(2)]); // another boat
        BoatBlock::factory()->create(['boat_id' => $boat->id, 'starts_at' => $start->copy()->addDay(), 'ends_at' => $start->copy()->addDay()->addHour()]); // another day

        $this->assertSame(2, BoatBlock::query()->overlapping($boat, $start->copy()->addMinutes(30), $start->copy()->addHours(3))->count());
        $this->assertSame(0, BoatBlock::query()->overlapping($boat, $start->copy()->subHours(2), $start)->count(), 'ending exactly at the start does not overlap');
    }

    public function test_catalog_basics_slugs_soft_deletes_settings_and_constraints(): void
    {
        $experience = Experience::factory()->create(['slug' => 'sunset']);
        $addon = Addon::factory()->perGuest(1500)->create();
        $boat = Boat::factory()->create();
        $captain = Captain::factory()->create();

        $this->assertSame('slug', $experience->getRouteKeyName());
        $this->assertSame(AddonUnit::PerGuest, $addon->unit);
        $this->assertSame([2, 3, 4], $experience->durations);

        foreach ([$experience, $boat, $captain] as $model) {
            $model->delete();
            $this->assertSoftDeleted($model);
        }

        Setting::create(['key' => 'tax_rate_bp', 'value' => 1025, 'group' => 'booking']);
        Setting::create(['key' => 'gratuity_options', 'value' => [0, 15, 18, 20], 'group' => 'booking']);
        $this->assertSame(1025, Setting::where('key', 'tax_rate_bp')->value('value'));
        $this->assertSame([0, 15, 18, 20], Setting::where('key', 'gratuity_options')->first()->value);

        app(AuditService::class)->record('captain.updated', $captain);
        $this->assertSame('captain', AuditLog::query()->value('subject_type'));

        $this->expectException(QueryException::class);
        Captain::factory()->create(['email' => $captain->email]); // one captain per email
    }

    public function test_availability_rules_are_one_per_weekday(): void
    {
        $boat = Boat::factory()->create();
        BoatAvailabilityRule::factory()->create(['boat_id' => $boat->id, 'weekday' => 1]);

        $this->expectException(QueryException::class);
        BoatAvailabilityRule::factory()->create(['boat_id' => $boat->id, 'weekday' => 1]);
    }
}
