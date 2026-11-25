<?php

namespace App\Support\Demo;

use App\Services\PublicCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Fluent;

/**
 * Price calculator, ported from the mockup's CYB.quote() (docs/PLAN.md §7.1). All money in cents.
 *
 * Phase 1: used for server-rendered examples (yacht page, pricing page). Since 3.5 its rates come from Settings.
 * Since 4.1 the real price is App\Services\QuoteService (each boat's weekend rate, fixed prices, promo limits).
 * Since 4.3 the public pages ask QuoteService; this class only feeds the screens that are still demo — checkout
 * until 4.5, My trips (4.7), admin bookings (4.8) — and goes away with them. Paid in full (D22): no deposit. The
 * experience is free and adds nothing; add-ons only when picked (2026-11-25).
 * Must stay identical to resources/js/lib/demo-quote.js (tests/Unit/DemoQuoteTest + tests/js).
 */
class DemoQuote
{
    /** @param  array<string, mixed>  $pricing  config('demo.pricing') */
    public function __construct(private array $pricing) {}

    /** For the screens still on demo bookings (My trips, owner and admin bookings) until Phase 4. */
    public static function fromConfig(): self
    {
        return new self(config('demo.pricing'));
    }

    /** The public site since 3.5: tax and weekend rate from Admin → Settings; promo codes still demo. */
    public static function fromSettings(): self
    {
        return new self(app(PublicCatalog::class)->pricing());
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
     * @param  array{date?: ?string, hours?: int, guests?: int, addons?: list<Fluent>, promo?: ?string, gratuity?: int}  $options
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
        // The experience is free; add-ons (its suggested extras too) only when the guest picked them.
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

        return compact('hours', 'guests', 'weekend', 'rate', 'charter', 'captain', 'lines', 'subtotal', 'discount', 'promo', 'tax', 'gratuity', 'gratuityPct', 'total');
    }
}
