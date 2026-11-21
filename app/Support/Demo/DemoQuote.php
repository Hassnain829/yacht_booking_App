<?php

namespace App\Support\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;

/**
 * Price calculator, ported from the mockup's CYB.quote() (docs/PLAN.md §7.1). All money in cents.
 *
 * Phase 1: used for server-rendered examples (yacht page, pricing page).
 * Phase 4: becomes App\Services\QuoteService (reads the DB, promo codes table, settings).
 * Must stay identical to resources/js/lib/demo-quote.js (tests/Unit/DemoQuoteTest + tests/js).
 */
class DemoQuote
{
    /** @param  array<string, mixed>  $pricing  config('demo.pricing') */
    public function __construct(private array $pricing) {}

    public static function fromConfig(): self
    {
        return new self(config('demo.pricing'));
    }

    /** Durations offered for this boat + experience (never shorter than the boat's minimum). */
    public static function durations(Fluent $yacht, ?Fluent $experience): array
    {
        if (! $experience) {
            return [$yacht->min_hours];
        }
        $durations = array_values(array_filter($experience->durations, fn (int $h) => $h >= $yacht->min_hours));

        return $durations ?: [$yacht->min_hours];
    }

    /** Friday, Saturday and Sunday use the weekend rate. */
    public static function isWeekend(?string $date): bool
    {
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        return in_array(CarbonImmutable::createFromFormat('!Y-m-d', $date)->dayOfWeek, [0, 5, 6], true);
    }

    /**
     * @param  array{date?: ?string, hours?: int, guests?: int, addons?: list<Fluent>, promo?: ?string, gratuity?: int, plan?: 'deposit'|'full'}  $options
     * @return array<string, mixed>
     */
    public function quote(Fluent $yacht, ?Fluent $experience, array $options = []): array
    {
        $hours = (int) ($options['hours'] ?? $yacht->min_hours);
        $guests = (int) ($options['guests'] ?? 2);
        $weekend = self::isWeekend($options['date'] ?? null);

        // Weekend hourly rate is rounded to whole dollars (as in the mockup).
        $rate = $weekend
            ? (int) round($yacht->hourly_rate_cents * $this->pricing['weekend_multiplier_bp'] / 10000 / 100) * 100
            : $yacht->hourly_rate_cents;
        $charter = $rate * $hours;
        $captain = $yacht->captain_rate_cents * $hours;

        $lines = [
            ['key' => 'charter', 'label' => 'Yacht charter', 'sub' => money($rate).'/hr × '.$hours.' hrs'.($weekend ? ' · weekend rate' : ''), 'amount' => $charter],
            ['key' => 'captain', 'label' => $yacht->crew_count > 1 ? 'Captain & crew ('.$yacht->crew_count.')' : 'Licensed USCG captain', 'sub' => money($yacht->captain_rate_cents).'/hr × '.$hours.' hrs · included', 'amount' => $captain],
        ];
        if ($experience && $experience->package_fee_cents > 0) {
            $lines[] = ['key' => 'package', 'label' => $experience->name.' package', 'sub' => 'Experience extras', 'amount' => $experience->package_fee_cents];
        }
        foreach ($options['addons'] ?? [] as $addon) {
            $perGuest = $addon->unit === 'per_guest';
            $lines[] = ['key' => 'addon-'.$addon->slug, 'label' => $addon->name, 'sub' => $perGuest ? money($addon->price_cents).' × '.$guests.' guests' : 'Add-on', 'amount' => $perGuest ? $addon->price_cents * $guests : $addon->price_cents];
        }
        $lines[] = ['key' => 'cleaning', 'label' => 'Fuel & cleaning', 'sub' => 'Flat fee', 'amount' => $yacht->cleaning_fee_cents];

        $subtotal = array_sum(array_column($lines, 'amount'));

        $discount = 0;
        $promo = collect($this->pricing['promos'])->firstWhere('code', strtoupper(trim((string) ($options['promo'] ?? ''))));
        if ($promo) {
            $discount = $promo['type'] === 'percent' ? (int) round($charter * $promo['value'] / 100) : $promo['value'];
        }

        $taxable = max(0, $subtotal - $discount);
        $tax = (int) round($taxable * $this->pricing['tax_rate_bp'] / 10000);
        $gratuityPct = (int) ($options['gratuity'] ?? 0);
        $gratuity = (int) round(($charter + $captain) * $gratuityPct / 100);
        $total = $taxable + $tax + $gratuity;
        $deposit = (int) round($total * $this->pricing['deposit_bp'] / 10000);
        $dueNow = ($options['plan'] ?? 'deposit') === 'full' ? $total : $deposit;

        return compact('hours', 'guests', 'weekend', 'rate', 'charter', 'captain', 'lines', 'subtotal', 'discount', 'promo', 'tax', 'gratuity', 'gratuityPct', 'total', 'deposit', 'dueNow')
            + ['balance' => $total - $dueNow];
    }

    /** Lowest all-in price (weekday, shortest duration) — "All-in from" on the experiences page. */
    public static function startingAt(Fluent $yacht, ?Fluent $experience): int
    {
        $hours = self::durations($yacht, $experience)[0];

        return $yacht->hourly_rate_cents * $hours + $yacht->captain_rate_cents * $hours + ($experience?->package_fee_cents ?? 0);
    }
}
