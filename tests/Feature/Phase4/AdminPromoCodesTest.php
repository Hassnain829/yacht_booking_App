<?php

namespace Tests\Feature\Phase4;

use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\User;
use App\Services\PromoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 4.8 — Admin → Promo codes: the 3.4 record screens (list, add, edit, Trash / restore / delete permanently)
 * for the codes PromoService checks at checkout. Amounts typed as a percent or dollars, dates as Chicago days.
 */
class AdminPromoCodesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-05-03 10:00:00', 'America/Chicago'));
        $this->admin = User::factory()->admin()->create();
    }

    /** @param  array<string, mixed>  $overrides  @return array<string, mixed> */
    private function promo(array $overrides = []): array
    {
        return array_merge([
            'code' => 'summer25', 'type' => 'percent', 'amount' => '12.5', 'applies_to' => 'charter',
            'min_subtotal' => '1000', 'max_discount' => '200', 'starts_on' => '2027-06-01', 'ends_on' => '2027-08-31',
            'max_redemptions' => '100', 'max_per_user' => '1', 'is_active' => '1',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function sensitive(string $dialog): array
    {
        return ['reason' => 'The summer offer has ended.', 'password' => 'password', 'dialog' => $dialog];
    }

    public function test_only_the_admin_manages_promo_codes(): void
    {
        $code = PromoCode::query()->create(['code' => 'WELCOME10', 'type' => PromoType::Percent, 'value' => 1000, 'applies_to' => PromoAppliesTo::Charter, 'is_active' => true]);

        $this->get('/admin/promo-codes')->assertRedirect('/login');
        foreach ([User::factory()->create(), User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->get('/admin/promo-codes')->assertForbidden();
            $this->actingAs($user)->post('/admin/promo-codes', $this->promo())->assertForbidden();
            $this->actingAs($user)->delete("/admin/promo-codes/{$code->ulid}", $this->sensitive('trash-record'))->assertForbidden();
        }
        $this->assertSame(1, PromoCode::query()->count());
    }

    public function test_the_admin_adds_a_code_in_percent_with_chicago_dates_and_limits(): void
    {
        $this->actingAs($this->admin)->post('/admin/promo-codes', $this->promo())->assertRedirect();
        $promo = PromoCode::query()->sole();

        $this->assertSame(['SUMMER25', PromoType::Percent, 1250, PromoAppliesTo::Charter, 100000, 20000, 100, 1], [$promo->code, $promo->type, $promo->value, $promo->applies_to, $promo->min_subtotal_cents, $promo->max_discount_cents, $promo->max_redemptions, $promo->max_per_user]);
        $this->assertSame(['2027-06-01T05:00:00Z', '2027-09-01T04:59:59Z'], [$promo->starts_at->toIso8601ZuluString(), $promo->ends_at->toIso8601ZuluString()], 'the whole Chicago days');
        $this->assertSame('12.5% off yacht charter', $promo->terms()->label());
        $this->assertSame('SUMMER25', AuditLog::query()->where('action', 'promo_code.created')->sole()->after['code']);

        $this->get('/admin/promo-codes')->assertOk()->assertSee('SUMMER25')->assertSee('Scheduled')->assertSee('0 / 100')->assertSee('1 per guest');
        $this->get("/admin/promo-codes/{$promo->ulid}/edit")->assertOk()->assertSee('value="12.5"', false)->assertSee('value="2027-08-31"', false);

        $this->put("/admin/promo-codes/{$promo->ulid}", $this->promo(['type' => 'fixed', 'amount' => '50', 'max_discount' => '', 'applies_to' => 'subtotal']))->assertRedirect();
        $this->assertSame([PromoType::Fixed, 5000, null], [$promo->fresh()->type, $promo->fresh()->value, $promo->fresh()->max_discount_cents]);
    }

    public function test_invalid_promo_input_is_refused(): void
    {
        PromoCode::query()->create(['code' => 'WELCOME10', 'type' => PromoType::Percent, 'value' => 1000, 'applies_to' => PromoAppliesTo::Charter, 'is_active' => true]);
        $this->actingAs($this->admin);

        $this->postJson('/admin/promo-codes', $this->promo(['code' => 'no spaces!', 'type' => 'bogus', 'applies_to' => 'everything', 'max_per_user' => '0']))
            ->assertUnprocessable()->assertJsonValidationErrors(['code', 'type', 'applies_to', 'max_per_user']);
        $this->postJson('/admin/promo-codes', $this->promo(['code' => 'welcome10']))->assertUnprocessable()->assertJsonValidationErrors(['code' => 'already used']);
        $this->postJson('/admin/promo-codes', $this->promo(['amount' => '150']))->assertUnprocessable()->assertJsonValidationErrors(['amount' => 'at most 100']);
        $this->postJson('/admin/promo-codes', $this->promo(['amount' => '0']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/admin/promo-codes', $this->promo(['starts_on' => '2027-09-01', 'ends_on' => '2027-08-01']))->assertUnprocessable()->assertJsonValidationErrors('ends_on');
        $this->postJson('/admin/promo-codes', $this->promo(['type' => 'fixed', 'amount' => '50']))->assertUnprocessable()->assertJsonValidationErrors(['max_discount' => 'only for percentage']);
        $this->postJson('/admin/promo-codes', $this->promo(['starts_on' => '', 'ends_on' => '']))->assertRedirect(); // no dates: always on
        $this->assertSame(2, PromoCode::query()->count());
    }

    public function test_a_code_in_the_trash_stops_working_and_a_used_one_is_never_deleted(): void
    {
        $promo = PromoCode::query()->create(['code' => 'SUNSET50', 'type' => PromoType::Fixed, 'value' => 5000, 'applies_to' => PromoAppliesTo::Subtotal, 'is_active' => true]);
        $guest = User::factory()->create();
        $this->assertSame('SUNSET50', app(PromoService::class)->check('sunset50', $guest)->code);
        $this->actingAs($this->admin);

        $this->delete("/admin/promo-codes/{$promo->ulid}", $this->sensitive('trash-record'))->assertRedirect('/admin/promo-codes');
        $this->assertSoftDeleted($promo);
        try {
            app(PromoService::class)->check('SUNSET50', $guest);
            $this->fail('A code in the Trash must not work.');
        } catch (ValidationException $exception) {
            $this->assertSame(['code' => ['That promo code is not valid.']], $exception->errors());
        }

        $booking = Booking::factory()->create();
        (new PromoRedemption)->forceFill(['promo_code_id' => $promo->id, 'booking_id' => $booking->id, 'discount_cents' => 5000])->save();
        $this->get("/admin/promo-codes/{$promo->ulid}/edit")->assertOk()->assertSee('It was used on 1 booking');
        $this->delete("/admin/promo-codes/{$promo->ulid}/permanent", $this->sensitive('delete-record'))->assertSessionHasErrorsIn('sensitive');
        $this->assertSoftDeleted($promo);

        $this->post("/admin/promo-codes/{$promo->ulid}/restore", $this->sensitive('restore-record'))->assertRedirect();
        $this->assertNotSoftDeleted($promo);
    }
}
