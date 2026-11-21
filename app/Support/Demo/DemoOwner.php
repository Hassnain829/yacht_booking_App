<?php

namespace App\Support\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * Phase 1 stand-in for one owner's data (reads config/demo_owner.php).
 *
 * Day offsets become real dates relative to "now" in Chicago, and earnings follow PLAN §7.5–7.6:
 *   charter  = hourly (× weekend markup on Fri–Sun, whole dollars) × hours, or the fixed duration price
 *   net      = charter − commission (on the charter only) + fuel & cleaning fee
 *   status   = pending until trip end + 72 h → available; on_hold while a complaint is open;
 *              paid once included in a payout; refunded when the trip was cancelled.
 *
 * Phase 7 replaces this with LedgerService / PayoutService and the ledger_entries table.
 */
class DemoOwner
{
    /** Listing statuses shown to owners (PLAN §7.7). */
    public const BOAT_STATUSES = [
        'draft' => ['label' => 'In progress', 'class' => 'bg-navy-100 text-navy-700'],
        'pending' => ['label' => 'Pending review', 'class' => 'bg-amber-100 text-amber-800'],
        'changes_requested' => ['label' => 'Changes requested', 'class' => 'bg-red-100 text-red-700'],
        'approved' => ['label' => 'Approved', 'class' => 'bg-emerald-100 text-emerald-800'],
    ];

    /** Earning statuses (ledger_entries.status + display-only "refunded"). */
    public const EARNING_STATUSES = [
        'pending' => ['label' => 'Pending', 'class' => 'bg-navy-100 text-navy-700'],
        'available' => ['label' => 'Available', 'class' => 'bg-emerald-100 text-emerald-800'],
        'on_hold' => ['label' => 'On hold', 'class' => 'bg-amber-100 text-amber-800'],
        'paid' => ['label' => 'Paid out', 'class' => 'bg-brass-100 text-brass-800'],
        'refunded' => ['label' => 'Refunded', 'class' => 'bg-red-100 text-red-700'],
        'deducted' => ['label' => 'Deduction', 'class' => 'bg-red-100 text-red-700'],
    ];

    public const BOOKING_STATUSES = [
        'confirmed' => ['label' => 'Confirmed', 'class' => 'bg-emerald-100 text-emerald-800'],
        'completed' => ['label' => 'Completed', 'class' => 'bg-navy-100 text-navy-700'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-red-100 text-red-700'],
    ];

    public const COMPLAINT_CATEGORIES = [
        'boat_condition' => 'Boat condition',
        'cleanliness' => 'Cleanliness',
        'late_start' => 'Late start',
        'safety' => 'Safety',
        'missing_amenities' => 'Missing amenities',
        'captain' => 'Captain',
        'other' => 'Other',
    ];

    public const COMPLAINT_STATUSES = [
        'open' => ['label' => 'Waiting for your response', 'class' => 'bg-amber-100 text-amber-800'],
        'under_review' => ['label' => 'Under review', 'class' => 'bg-navy-100 text-navy-700'],
        'resolved' => ['label' => 'Resolved', 'class' => 'bg-emerald-100 text-emerald-800'],
        'rejected' => ['label' => 'Closed — no action', 'class' => 'bg-navy-100 text-navy-700'],
    ];

    /** Wizard step → the section owners see as "Next up" (mockup my-boats.js). */
    public const NEXT_UP = [
        'intro' => 'Getting started', 'insuranceType' => 'Boat details', 'location' => 'Boat details', 'title' => 'Boat details',
        'cancellation' => 'Boat details', 'rules' => 'Boat details', 'features' => 'Boat details', 'photos' => 'Boat details',
        'specs' => 'Boat details', 'availability' => 'Availability', 'advance' => 'Availability', 'multi' => 'Availability',
        'operators' => 'Operators and durations', 'pricing' => 'Pricing', 'insurance' => 'Insurance', 'insuranceOwner' => 'Insurance',
        'insuranceBoat' => 'Insurance', 'commercial' => 'Insurance', 'review' => 'Review and submit',
    ];

    private CarbonImmutable $now;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?CarbonImmutable $now = null)
    {
        $this->now = ($now ?? CarbonImmutable::now())->setTimezone(config('app.display_timezone'));
        $this->config = config('demo_owner');
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    /** @return array{commission_bp: int, payout_delay_hours: int, complaint_window_hours: int, min_withdrawal_cents: int} */
    public function money(): array
    {
        return $this->config['money'];
    }

    private function day(int $offset): CarbonImmutable
    {
        return $this->now->startOfDay()->addDays($offset);
    }

    /* ------------------------------------------------------------------ profile */

    public function profile(): Fluent
    {
        $p = $this->config['profile'];
        $account = $this->config['payout_account'];
        $paused = $this->config['payouts_paused'];

        return new Fluent([
            ...$p,
            'name' => trim($p['first_name'].' '.$p['last_name']),
            'initials' => strtoupper(substr($p['first_name'], 0, 1).substr($p['last_name'], 0, 1)),
            'member_since' => $this->day($p['member_since_days']),
            'payout_account' => $account ? new Fluent([...$account, 'connected_at' => $this->day($account['connected_days'])]) : null,
            'payouts_paused' => $paused ? new Fluent([...$paused, 'since' => $this->day($paused['days'])]) : null,
        ]);
    }

    /* ------------------------------------------------------------------ boats */

    /** @return Collection<int, Fluent> */
    public function boats(): Collection
    {
        return collect($this->config['boats'])->values()->map(function (array $boat, int $index) {
            $specs = $boat['specs'] ?? [];
            $location = $boat['location'] ?? [];
            $model = trim(implode(' ', array_filter([$specs['year'] ?? '', ($specs['make'] ?? '') !== 'Other' ? ($specs['make'] ?? '') : '', $specs['model'] ?? ''])));

            return new Fluent([
                ...$boat,
                'index' => $index,
                'display_title' => $boat['title'] ?: ($model ?: 'Untitled boat'),
                'short_title' => $boat['title'] ? trim(explode('—', $boat['title'])[0]) : ($model ?: 'Untitled boat'),
                'meta' => implode(' · ', array_filter([
                    $model,
                    ! empty($specs['length']) ? $specs['length'].' ft' : '',
                    implode(', ', array_filter([$location['city'] ?? '', $location['state'] ?? ''])),
                ])),
                'cover' => $boat['photos'][0] ?? null,
                'photos_count' => count($boat['photos'] ?? []),
                'updated_at' => $this->day($boat['updated_days']),
                'status_label' => self::BOAT_STATUSES[$boat['status']]['label'],
                'status_class' => self::BOAT_STATUSES[$boat['status']]['class'],
                'next_up' => self::NEXT_UP[$boat['step']] ?? 'Boat details',
            ]);
        });
    }

    public function boat(string $ulid): ?Fluent
    {
        return $this->boats()->firstWhere('ulid', $ulid);
    }

    /* ------------------------------------------------------------------ bookings + earnings */

    /** Charter price for a trip on this boat (whole-dollar weekend rate, like DemoQuote). */
    public static function charterCents(array $pricing, CarbonImmutable $start, int $hours): int
    {
        if (isset($pricing['fixed'][(string) $hours])) {
            return (int) $pricing['fixed'][(string) $hours];
        }
        $hourly = (int) $pricing['hourly_cents'];
        if (in_array($start->dayOfWeek, [0, 5, 6], true)) {
            $hourly = (int) (round($hourly * (10000 + (int) $pricing['weekend_markup_bp']) / 10000 / 100) * 100);
        }

        return $hourly * $hours;
    }

    /** @return Collection<int, Fluent> newest trip first */
    public function bookings(): Collection
    {
        $boats = $this->boats();
        $openComplaints = collect($this->config['complaints'])->whereIn('status', ['open', 'under_review'])->pluck('booking')->all();
        $commissionBp = $this->money()['commission_bp'];
        $delay = $this->money()['payout_delay_hours'];

        return collect($this->config['bookings'])->map(function (array $b) use ($boats, $openComplaints, $commissionBp, $delay) {
            $boat = $boats[$b['boat']];
            $start = $this->day($b['day'])->setTimeFromTimeString($b['time']);
            $end = $start->addHours($b['hours']);
            $cancelled = $b['status'] === 'cancelled';

            $charter = $cancelled ? 0 : self::charterCents($boat->pricing, $start, $b['hours']);
            $cleaning = $cancelled ? 0 : (int) $boat->pricing['cleaning_fee_cents'];
            $commission = (int) round($charter * $commissionBp / 10000);
            $availableAt = $end->addHours($delay);

            $earning = match (true) {
                $cancelled => 'refunded',
                isset($b['payout']) => 'paid',
                in_array($b['ref'], $openComplaints, true) => 'on_hold',
                $this->now->lt($availableAt) => 'pending',
                default => 'available',
            };

            return new Fluent([
                ...$b,
                'boat' => $boat,
                'starts_at' => $start,
                'ends_at' => $end,
                'is_upcoming' => ! $cancelled && $start->gt($this->now),
                'charter_cents' => $charter,
                'cleaning_cents' => $cleaning,
                'gross_cents' => $charter + $cleaning,
                'commission_cents' => $commission,
                'net_cents' => $charter - $commission + $cleaning,
                'available_at' => $availableAt,
                'earning_status' => $earning,
                'earning_label' => self::EARNING_STATUSES[$earning]['label'],
                'earning_class' => self::EARNING_STATUSES[$earning]['class'],
                'status_label' => self::BOOKING_STATUSES[$b['status']]['label'],
                'status_class' => self::BOOKING_STATUSES[$b['status']]['class'],
                'payout' => $b['payout'] ?? null,
            ]);
        })->sortByDesc(fn (Fluent $b) => $b->starts_at->getTimestamp())->values();
    }

    /** @return Collection<int, Fluent> */
    public function complaints(): Collection
    {
        $bookings = $this->bookings()->keyBy('ref');

        return collect($this->config['complaints'])->map(fn (array $c) => new Fluent([
            ...$c,
            'booking' => $bookings[$c['booking']],
            'category_label' => self::COMPLAINT_CATEGORIES[$c['category']],
            'status_label' => self::COMPLAINT_STATUSES[$c['status']]['label'],
            'status_class' => self::COMPLAINT_STATUSES[$c['status']]['class'],
            'opened_at' => $this->day($c['opened_days'])->setTime(10, 15),
            'resolved_at' => isset($c['resolved_days']) ? $this->day($c['resolved_days'])->setTime(16, 40) : null,
            'is_open' => in_array($c['status'], ['open', 'under_review'], true),
            'deduction_cents' => $c['deduction_cents'] ?? 0,
        ]))->sortByDesc(fn (Fluent $c) => $c->opened_at->getTimestamp())->values();
    }

    /** @return Collection<int, Fluent> */
    public function payouts(): Collection
    {
        $bookings = $this->bookings();
        $account = $this->config['payout_account'];

        return collect($this->config['payouts'])->map(fn (array $p) => new Fluent([
            ...$p,
            'amount_cents' => $bookings->where('payout', $p['ref'])->sum('net_cents'),
            'trips' => $bookings->where('payout', $p['ref'])->count(),
            'requested_at' => $this->day($p['requested_days'])->setTime(9, 30),
            'paid_at' => isset($p['paid_days']) ? $this->day($p['paid_days'])->setTime(14, 5) : null,
            'method' => $account ? $account['bank'].' •••• '.$account['last4'] : 'Bank transfer',
        ]))->sortByDesc(fn (Fluent $p) => $p->requested_at->getTimestamp())->values();
    }

    /** Deductions from resolved complaints (ledger type "deduction"). @return Collection<int, Fluent> */
    public function deductions(): Collection
    {
        return $this->complaints()->filter(fn (Fluent $c) => $c->deduction_cents > 0)->values();
    }

    /**
     * Statement rows, newest first: trip earnings (+), deductions (−) and payouts (−).
     *
     * @return Collection<int, Fluent>
     */
    public function ledger(): Collection
    {
        $rows = $this->bookings()->map(fn (Fluent $b) => new Fluent([
            'date' => $b->starts_at,
            'kind' => 'trip',
            'title' => $b->experience,
            'detail' => $b->status === 'cancelled'
                ? 'Cancelled by the guest · fully refunded'
                : $b->boat->short_title.' · '.$b->hours.' hrs · '.money($b->gross_cents, 'never').' gross − '.money($b->commission_cents, 'never').' commission',
            'ref' => $b->ref,
            'amount_cents' => $b->net_cents,
            'status' => $b->earning_status,
            'status_label' => $b->earning_label,
            'status_class' => $b->earning_class,
            'available_at' => $b->earning_status === 'pending' ? $b->available_at : null,
        ]));

        $rows = $rows->concat($this->deductions()->map(fn (Fluent $c) => new Fluent([
            'date' => $c->resolved_at,
            'kind' => 'deduction',
            'title' => 'Complaint deduction — '.strtolower($c->category_label),
            'detail' => 'Trip '.$c->booking->ref,
            'ref' => $c->booking->ref,
            'amount_cents' => -$c->deduction_cents,
            'status' => 'deducted',
            'status_label' => self::EARNING_STATUSES['deducted']['label'],
            'status_class' => self::EARNING_STATUSES['deducted']['class'],
            'available_at' => null,
        ])));

        $rows = $rows->concat($this->payouts()->map(fn (Fluent $p) => new Fluent([
            'date' => $p->paid_at ?? $p->requested_at,
            'kind' => 'payout',
            'title' => 'Withdrawal to '.$p->method,
            'detail' => $p->trips.' trips',
            'ref' => $p->ref,
            'amount_cents' => -$p->amount_cents,
            'status' => 'paid',
            'status_label' => self::EARNING_STATUSES['paid']['label'],
            'status_class' => self::EARNING_STATUSES['paid']['class'],
            'available_at' => null,
        ])));

        return $rows->sortByDesc(fn (Fluent $r) => $r->date->getTimestamp())->values();
    }

    /** @return array{pending: int, available: int, on_hold: int, paid: int, earned: int, next_release: ?CarbonImmutable} */
    public function balances(): array
    {
        $bookings = $this->bookings();
        $deductions = $this->deductions()->sum('deduction_cents');
        $nextRelease = $bookings->where('earning_status', 'pending')->where('is_upcoming', false)->sortBy(fn (Fluent $b) => $b->available_at->getTimestamp())->first();

        return [
            'pending' => $bookings->where('earning_status', 'pending')->sum('net_cents'),
            'available' => max(0, $bookings->where('earning_status', 'available')->sum('net_cents') - $deductions),
            'on_hold' => $bookings->where('earning_status', 'on_hold')->sum('net_cents'),
            'paid' => $this->payouts()->where('status', 'paid')->sum('amount_cents'),
            'earned' => $bookings->where('status', 'completed')->sum('net_cents') - $deductions,
            'next_release' => $nextRelease?->available_at,
        ];
    }

    /** @return Collection<int, Fluent> */
    public function blocks(): Collection
    {
        return collect($this->config['blocks'])->map(fn (array $block) => new Fluent([
            ...$block,
            'from_date' => $this->day($block['from']),
            'to_date' => $this->day($block['to']),
        ]));
    }
}
