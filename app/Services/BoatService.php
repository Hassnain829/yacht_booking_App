<?php

namespace App\Services;

use App\Enums\BlockType;
use App\Enums\BoatOwnership;
use App\Enums\BoatStatus;
use App\Models\Amenity;
use App\Models\Boat;
use App\Models\BoatBlock;
use App\Models\Experience;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Boats as the admin manages them (PLAN 3.4.3–3.4.4). Everything is written to the audit log.
 *
 * - save(): the boat's fields + amenities, offered experiences and weekly hours, in one transaction.
 * - setStatus(): the only way the admin publishes or hides a boat. House boats → `published` / `paused`;
 *   an owner's boat is hidden as `suspended` (the owner can't undo that). Boats still in the owner's listing
 *   review (draft, pending review, changes requested, rejected) are published from the review queue (Phase 6).
 * - setBookable(): the "Unavailable" tag (booking_enabled), admin-only (user requirement 2026-11-22).
 * - addBlock() / deleteBlock(): times the boat can't be booked (maintenance, blackout, weather).
 */
class BoatService
{
    public function __construct(private RecordWriter $writer, private AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data  BoatRequest::boatData()
     * @param  array{amenities: list<int>, experiences: array<int, ?int>, hours: list<array<string, mixed>>}  $relations
     */
    public function save(Boat $boat, array $data, array $relations, User $actor, bool $publish = false): Boat
    {
        $isNew = ! $boat->exists;
        if ($isNew) {
            $boat->forceFill(['ownership' => BoatOwnership::House, 'status' => $publish ? BoatStatus::Published : BoatStatus::Draft]);
            if ($publish) {
                $boat->published_at = now();
            }
        }

        return $this->writer->save($boat, $data, $actor, relations: fn (Boat $saved) => $this->syncRelations($saved, $relations));
    }

    public function setStatus(Boat $boat, string $action, User $actor, ?string $note = null): void
    {
        $target = match ($action) {
            'publish' => BoatStatus::Published,
            'hide' => $boat->isHouse() ? BoatStatus::Paused : BoatStatus::Suspended,
            default => throw ValidationException::withMessages(['action' => 'Unknown action.']),
        };
        $allowedFrom = match ($action) {
            'publish' => $boat->isHouse()
                ? [BoatStatus::Draft, BoatStatus::Approved, BoatStatus::Paused, BoatStatus::Suspended, BoatStatus::Archived]
                : [BoatStatus::Approved, BoatStatus::Paused, BoatStatus::Suspended],
            'hide' => [BoatStatus::Published],
        };
        if (! in_array($boat->status, $allowedFrom, true)) {
            throw ValidationException::withMessages(['action' => $boat->isHouse() || $action === 'hide'
                ? "This boat is {$boat->status->label()}, so it can't be changed that way."
                : 'This owner boat is still in the listing review. Approve it from Listing reviews first.']);
        }
        if (! $boat->isHouse() && $target === BoatStatus::Suspended && blank($note)) {
            throw ValidationException::withMessages(['note' => "Please write why the owner's boat is being hidden. The owner will be told."]);
        }

        DB::transaction(function () use ($boat, $target, $actor, $note): void {
            $before = $boat->status;
            $boat->forceFill(['status' => $target]);
            if ($target === BoatStatus::Published && $boat->published_at === null) {
                $boat->published_at = now();
            }
            $boat->save();
            $this->audit->record('boat.status_changed', $boat, ['status' => $before->value], ['status' => $target->value], $note, $actor);
        });
    }

    public function setBookable(Boat $boat, bool $bookable, User $actor, ?string $reason = null): void
    {
        if ($boat->booking_enabled === $bookable && ($bookable || $boat->unavailable_reason === $reason)) {
            return;
        }

        DB::transaction(function () use ($boat, $bookable, $actor, $reason): void {
            $before = $boat->only(['booking_enabled', 'unavailable_reason']);
            $boat->forceFill(['booking_enabled' => $bookable, 'unavailable_reason' => $bookable ? null : $reason])->save();
            $this->audit->record('boat.bookable_changed', $boat, $before, $boat->only(['booking_enabled', 'unavailable_reason']), actor: $actor);
        });
    }

    public function addBlock(Boat $boat, CarbonImmutable $startsAt, CarbonImmutable $endsAt, BlockType $type, ?string $reason, User $actor): BoatBlock
    {
        return DB::transaction(function () use ($boat, $startsAt, $endsAt, $type, $reason, $actor) {
            $block = new BoatBlock(['starts_at' => $startsAt->utc(), 'ends_at' => $endsAt->utc(), 'type' => $type, 'reason' => $reason]);
            $block->boat()->associate($boat);
            $block->creator()->associate($actor);
            $block->save();
            $this->audit->record('boat.block_added', $boat, [], ['block' => $block->ulid, 'type' => $type->value, 'starts_at' => $block->starts_at->toIso8601String(), 'ends_at' => $block->ends_at->toIso8601String(), 'reason' => $reason], actor: $actor);

            return $block;
        });
    }

    public function deleteBlock(Boat $boat, BoatBlock $block, User $actor): void
    {
        DB::transaction(function () use ($boat, $block, $actor): void {
            $before = ['block' => $block->ulid, 'type' => $block->type->value, 'starts_at' => $block->starts_at->toIso8601String(), 'ends_at' => $block->ends_at->toIso8601String()];
            $block->delete();
            $this->audit->record('boat.block_removed', $boat, $before, [], actor: $actor);
        });
    }

    /**
     * @param  array{amenities: list<int>, experiences: array<int, ?int>, hours: list<array<string, mixed>>}  $relations
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [before, after] of what changed
     */
    private function syncRelations(Boat $boat, array $relations): array
    {
        $before = [];
        $after = [];

        $oldAmenities = $boat->amenities()->pluck('amenities.id')->sort()->values()->all();
        $boat->amenities()->sync($relations['amenities']);
        $newAmenities = collect($relations['amenities'])->sort()->values()->all();
        if ($oldAmenities !== $newAmenities) {
            $names = fn (array $ids) => Amenity::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
            [$before['amenities'], $after['amenities']] = [$names($oldAmenities), $names($newAmenities)];
        }

        $oldOffers = $boat->experiences()->get()->mapWithKeys(fn (Experience $e) => [$e->slug => $e->pivot->package_fee_override_cents])->sortKeys()->all();
        $boat->experiences()->sync(collect($relations['experiences'])->map(fn (?int $fee) => ['package_fee_override_cents' => $fee, 'is_active' => true])->all());
        $newOffers = $boat->experiences()->get()->mapWithKeys(fn (Experience $e) => [$e->slug => $e->pivot->package_fee_override_cents])->sortKeys()->all();
        if ($oldOffers !== $newOffers) {
            [$before['experiences'], $after['experiences']] = [$oldOffers, $newOffers];
        }

        $hours = fn () => $boat->availabilityRules()->get()->mapWithKeys(fn ($rule) => [$rule->weekday => $rule->is_open ? substr((string) $rule->opens_at, 0, 5).'–'.substr((string) $rule->closes_at, 0, 5) : 'closed'])->all();
        $oldHours = $hours();
        foreach ($relations['hours'] as $row) {
            $boat->availabilityRules()->updateOrCreate(['weekday' => $row['weekday']], $row);
        }
        $newHours = $hours();
        if ($oldHours !== $newHours) {
            [$before['hours'], $after['hours']] = [$oldHours, $newHours];
        }

        return [$before, $after];
    }
}
