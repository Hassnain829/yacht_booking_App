<?php

namespace App\Support\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * Phase 1 stand-in for one customer's account (reads config/demo_customer.php).
 * Prices come from DemoQuote; refunds from RefundPreview (PLAN §7.4).
 * Phase 2/4 replaces it with the authenticated user, BookingPolicy and the bookings table.
 */
class DemoCustomer
{
    public const STATUSES = [
        'confirmed' => ['label' => 'Confirmed', 'class' => 'bg-emerald-100 text-emerald-800'],
        'completed' => ['label' => 'Completed', 'class' => 'bg-navy-100 text-navy-700'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-red-100 text-red-700'],
    ];

    private CarbonImmutable $now;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(private DemoCatalog $catalog, ?CarbonImmutable $now = null)
    {
        $this->now = ($now ?? CarbonImmutable::now())->setTimezone(config('app.display_timezone'));
        $this->config = config('demo_customer');
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function profile(): Fluent
    {
        $p = $this->config['profile'];

        return new Fluent([
            ...$p,
            'name' => $p['first_name'].' '.$p['last_name'],
            'initials' => strtoupper($p['first_name'][0].$p['last_name'][0]),
            'member_since' => $this->now->startOfDay()->addDays($p['member_since_days']),
            'password_changed_at' => $this->now->startOfDay()->addDays($p['password_changed_days']),
        ]);
    }

    /** @return Collection<int, Fluent> */
    public function cards(): Collection
    {
        return collect($this->config['cards'])->map(fn (array $card) => new Fluent([
            ...$card,
            'expires' => sprintf('%02d/%02d', $card['exp_month'], $card['exp_year'] % 100),
        ]));
    }

    /** @return Collection<int, Fluent> */
    public function sessions(): Collection
    {
        return collect($this->config['sessions'])->map(fn (array $s) => new Fluent([...$s, 'last_active' => $this->now->subMinutes($s['minutes_ago'])]));
    }

    /** @return Collection<int, Fluent> upcoming first (soonest), then past (newest) */
    public function trips(): Collection
    {
        $pricing = $this->catalog->pricing();
        $window = config('demo_owner.money.complaint_window_hours');

        $trips = collect($this->config['trips'])->map(function (array $t) use ($pricing, $window) {
            $yacht = $this->catalog->yacht($t['yacht']);
            $experience = $this->catalog->experience($t['experience']);
            $start = $this->now->startOfDay()->addDays($t['day'])->setTimeFromTimeString($t['time']);
            $end = $start->addHours($t['hours']);
            $addons = $this->catalog->addons()->whereIn('slug', $t['addons'])->values()->all();
            $balanceDue = $start->startOfDay()->subDays($pricing['balance_due_days']);
            $plan = $t['plan'] === 'deposit' && $balanceDue->gt($this->now) ? 'deposit' : 'full';
            $quote = DemoQuote::fromConfig()->quote($yacht, $experience, [
                'date' => $start->toDateString(),
                'hours' => $t['hours'],
                'guests' => $t['guests'],
                'addons' => $addons,
                'gratuity' => $t['gratuity'],
                'plan' => $plan,
            ]);
            $paid = match ($t['status']) {
                'completed' => $quote['total'],
                'cancelled' => $quote['deposit'], // the deposit, refunded in full (cancelled inside the free window)
                default => $quote['dueNow'],
            };
            $captain = $t['captain'] ? new Fluent($this->config['captains'][$t['captain']]) : null;
            $isUpcoming = $t['status'] === 'confirmed' && $start->gt($this->now);
            $complaintUntil = $end->addHours($window);

            return new Fluent([
                ...$t,
                'yacht' => $yacht,
                'experience' => $experience,
                'addons' => $addons,
                'starts_at' => $start,
                'ends_at' => $end,
                'quote' => $quote,
                'paid_cents' => $paid,
                'refunded_cents' => $t['status'] === 'cancelled' ? $paid : 0,
                'balance_cents' => $t['status'] === 'confirmed' ? $quote['total'] - $paid : 0,
                'balance_due' => $plan === 'deposit' ? $balanceDue : null,
                'captain' => $captain,
                'is_upcoming' => $isUpcoming,
                'status_label' => self::STATUSES[$t['status']]['label'],
                'status_class' => self::STATUSES[$t['status']]['class'],
                'can_cancel' => $isUpcoming,
                'refund' => $isUpcoming ? RefundPreview::calculate($t['policy'], $start, $this->now, $quote['total'], $quote['gratuity'], $paid) : null,
                'next_drop' => $isUpcoming ? RefundPreview::nextDrop($t['policy'], $start, $this->now) : null,
                'complaint_until' => $complaintUntil,
                'can_complain' => $t['status'] === 'completed' && $this->now->lt($complaintUntil),
                'cancelled_at' => isset($t['cancelled_days']) ? $this->now->startOfDay()->addDays($t['cancelled_days'])->setTime(11, 20) : null,
            ]);
        });

        return $trips->where('is_upcoming', true)->sortBy(fn (Fluent $t) => $t->starts_at->getTimestamp())->values()
            ->concat($trips->where('is_upcoming', false)->sortByDesc(fn (Fluent $t) => $t->starts_at->getTimestamp())->values());
    }

    public function trip(string $ulid): ?Fluent
    {
        return $this->trips()->firstWhere('ulid', $ulid);
    }
}
