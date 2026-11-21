<?php

namespace App\Support\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * Phase 1 stand-in for the admin's view of everything (config/demo_admin.php + the other demo sources).
 * Phase 3+: Eloquent queries in the admin controllers, with whitelisted filters/sorts and pagination.
 */
class DemoAdmin
{
    public const BOOKING_STATUSES = [
        'confirmed' => ['label' => 'Confirmed', 'tone' => 'success'],
        'completed' => ['label' => 'Completed', 'tone' => 'navy'],
        'cancelled' => ['label' => 'Cancelled', 'tone' => 'danger'],
    ];

    private CarbonImmutable $now;

    public function __construct(private DemoCatalog $catalog, private DemoOwner $owner, private DemoCustomer $customer)
    {
        $this->now = CarbonImmutable::now(config('app.display_timezone'));
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function admin(): Fluent
    {
        return new Fluent(config('demo_admin.admin'));
    }

    private function ago(int $hours): CarbonImmutable
    {
        return $this->now->subHours($hours);
    }

    /* ------------------------------------------------------------------ captains */

    /** @return Collection<int, Fluent> */
    public function captains(): Collection
    {
        $types = config('demo_admin.license_types');

        return collect(config('demo_admin.captains'))->map(function (array $c) use ($types) {
            $expires = $this->now->startOfDay()->addDays($c['license_expires_days']);

            return new Fluent([
                ...$c,
                'license_label' => $types[$c['license_type']]['label'],
                'max_passengers' => $types[$c['license_type']]['max_passengers'],
                'license_expires' => $expires,
                'expiring_soon' => $c['license_expires_days'] <= 30,
                'initials' => collect(explode(' ', str_replace('Capt. ', '', $c['name'])))->map(fn ($p) => $p[0])->implode(''),
            ]);
        });
    }

    /** Checks before a captain can be assigned (PLAN §7.8). @return list<array{0: string, 1: bool}> */
    public function captainChecks(Fluent $booking, Fluent $captain): array
    {
        $overlap = $this->bookings()->first(fn (Fluent $b) => $b->ref !== $booking->ref && $b->status !== 'cancelled'
            && $b->captain_id === $captain->id && $b->starts_at->lt($booking->ends_at) && $b->ends_at->gt($booking->starts_at));

        return [
            ['Captain is active', (bool) $captain->active],
            ['License valid on '.$booking->starts_at->format('M j'), $captain->license_expires->gte($booking->ends_at)],
            $booking->guests <= $captain->max_passengers
                ? [$captain->license_label.' allows '.$booking->guests.' guests', true]
                : [$captain->license_label.': max '.$captain->max_passengers.' guests (this trip has '.$booking->guests.')', false],
            ['No other trip at this time', $overlap === null],
        ];
    }

    /* ------------------------------------------------------------------ bookings */

    /** @return Collection<int, Fluent> newest first */
    public function bookings(): Collection
    {
        $captains = $this->captains()->keyBy('name');
        $users = collect(config('demo_admin.users'));
        $fullName = fn (string $short) => $users->first(fn ($u) => str_starts_with($u['name'], explode(' ', $short)[0].' '.substr(explode(' ', $short)[1] ?? '', 0, 1)))['name'] ?? $short;
        $pricing = $this->catalog->pricing();

        $ownerTrips = $this->owner->bookings()->map(function (Fluent $b) use ($captains, $fullName, $pricing) {
            $captain = $b->status === 'cancelled' ? 0 : 8500 * $b->hours;
            $subtotal = $b->charter_cents + $b->cleaning_cents + $captain;
            $tax = (int) round($subtotal * $pricing['tax_rate_bp'] / 10000);
            $gratuity = (int) round(($b->charter_cents + $captain) * 18 / 100);
            $total = $subtotal + $tax + $gratuity;

            return new Fluent([
                'ref' => $b->ref,
                'kind' => 'owner',
                'boat' => $b->boat->short_title,
                'owner' => 'Marcus Reed',
                'experience' => $b->experience,
                'customer' => $fullName($b->customer),
                'guests' => $b->guests,
                'starts_at' => $b->starts_at,
                'ends_at' => $b->ends_at,
                'harbor' => 'Burnham Harbor',
                'total_cents' => $total,
                'paid_cents' => $b->status === 'completed' || ! $b->is_upcoming ? $total : (int) round($total / 2),
                'status' => $b->status,
                'captain' => $b->captain,
                'captain_id' => $b->captain ? $captains->get($b->captain)?->id : null,
            ]);
        });

        $houseTrips = $this->customer->trips()->map(fn (Fluent $t) => new Fluent([
            'ref' => $t->ref,
            'kind' => 'house',
            'boat' => $t->yacht->title,
            'owner' => null,
            'experience' => $t->experience->name,
            'customer' => 'Alex Morgan',
            'guests' => $t->guests,
            'starts_at' => $t->starts_at,
            'ends_at' => $t->ends_at,
            'harbor' => $t->yacht->harbor->name,
            'total_cents' => $t->quote['total'],
            'paid_cents' => $t->paid_cents - $t->refunded_cents,
            'status' => $t->status,
            'captain' => $t->captain?->name,
            'captain_id' => $t->captain ? $captains->get($t->captain->name)?->id : null,
        ]));

        return $ownerTrips->concat($houseTrips)->map(function (Fluent $b) {
            $b->status_label = self::BOOKING_STATUSES[$b->status]['label'];
            $b->status_tone = self::BOOKING_STATUSES[$b->status]['tone'];
            $b->is_upcoming = $b->status === 'confirmed' && $b->starts_at->gt($this->now);
            $b->balance_cents = $b->status === 'confirmed' ? $b->total_cents - $b->paid_cents : 0;

            return $b;
        })->sortByDesc(fn (Fluent $b) => $b->starts_at->getTimestamp())->values();
    }

    public function booking(string $ref): ?Fluent
    {
        return $this->bookings()->firstWhere('ref', $ref);
    }

    /* ------------------------------------------------------------------ other lists */

    /** @return Collection<int, Fluent> */
    public function users(): Collection
    {
        return collect(config('demo_admin.users'))->map(fn (array $u) => new Fluent([...$u, 'joined_at' => $this->now->startOfDay()->addDays($u['joined_days'])]));
    }

    /** @return Collection<int, Fluent> */
    public function messages(): Collection
    {
        return collect(config('demo_admin.messages'))->map(fn (array $m) => new Fluent([...$m, 'received_at' => $this->ago($m['hours_ago'])]));
    }

    /** @return Collection<int, Fluent> */
    public function withdrawals(): Collection
    {
        return collect(config('demo_admin.withdrawals'))->map(fn (array $w) => new Fluent([...$w, 'requested_at' => $this->ago($w['requested_hours_ago'])]));
    }

    /** @return Collection<int, Fluent> */
    public function payments(): Collection
    {
        return collect(config('demo_admin.payments'))->map(fn (array $p) => new Fluent([...$p, 'at' => $this->ago($p['hours_ago'])]));
    }

    /** @return Collection<int, Fluent> */
    public function audit(): Collection
    {
        return collect(config('demo_admin.audit'))->map(fn (array $a) => new Fluent([...$a, 'at' => $this->ago($a['hours_ago'])]));
    }

    /** Owner listings waiting for review (and drafts, shown greyed). @return Collection<int, Fluent> */
    public function listings(): Collection
    {
        return $this->owner->boats()->map(function (Fluent $boat) {
            $boat->owner = 'Marcus Reed';

            return $boat;
        });
    }

    /** @return Collection<int, Fluent> */
    public function complaints(): Collection
    {
        return $this->owner->complaints();
    }

    /** Numbers for the dashboard. @return array<string, mixed> */
    public function stats(): array
    {
        $bookings = $this->bookings();
        $month = $bookings->filter(fn (Fluent $b) => $b->status !== 'cancelled' && $b->starts_at->isSameMonth($this->now));
        $commissionBp = $this->owner->money()['commission_bp'];
        $ownerCharter = $this->owner->bookings()->filter(fn (Fluent $b) => $b->status !== 'cancelled' && $b->starts_at->isSameMonth($this->now))->sum('charter_cents');

        return [
            'today' => $bookings->filter(fn (Fluent $b) => $b->status !== 'cancelled' && $b->starts_at->isSameDay($this->now))->count(),
            'next7' => $bookings->filter(fn (Fluent $b) => $b->is_upcoming && $b->starts_at->lte($this->now->addDays(7)))->count(),
            'month_gross' => $month->sum('total_cents'),
            'month_commission' => (int) round($ownerCharter * $commissionBp / 10000),
            'by_status' => collect(self::BOOKING_STATUSES)->map(fn ($s, $key) => $bookings->where('status', $key)->count())->all(),
            'actions' => [
                ['clipboard-check', 'Listings to review', $this->listings()->where('status', 'pending')->count(), route('admin.listings.index')],
                ['landmark', 'Withdrawals to approve', $this->withdrawals()->where('status', 'requested')->count(), route('admin.payouts.index')],
                ['message-square-warning', 'Open complaints', $this->complaints()->where('is_open', true)->count(), route('admin.complaints.index')],
                ['ship-wheel', 'Trips without a captain', $bookings->where('is_upcoming', true)->whereNull('captain')->count(), route('admin.bookings.index', ['captain' => 'none'])],
                ['credit-card', 'Failed charges', $this->payments()->where('status', 'failed')->count(), route('admin.payments.index')],
                ['mail', 'New messages', $this->messages()->where('status', 'new')->count(), route('admin.inbox.index')],
            ],
        ];
    }
}
