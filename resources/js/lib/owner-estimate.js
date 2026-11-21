/**
 * Owner earnings ESTIMATE for the wizard's Pricing step (display only — the server calculates the real
 * amounts). Same rules as App\Support\Demo\DemoOwner::charterCents() and PLAN §7.5:
 *
 *   weekend rate = hourly × (1 + markup), rounded to whole dollars (Fri–Sun)
 *   charter      = fixed price for that duration, else rate × hours
 *   you earn     = charter − commission (on the charter only) + fuel & cleaning fee
 */

/** "380" / "380.5" / "1,250.00" → cents, or null when it isn't a valid amount. */
export function dollarsToCents(value) {
    const text = String(value ?? '').trim().replace(/,/g, '');
    if (!/^\d{1,7}(\.\d{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
}

export function weekendRateCents(hourlyCents, markupBp) {
    return Math.round((hourlyCents * (10000 + markupBp)) / 10000 / 100) * 100;
}

/**
 * @param {{ hourlyCents: number, markupBp: number, fixedCents?: Record<string, number> }} pricing
 */
export function charterCents({ hourlyCents, markupBp, fixedCents = {} }, hours, weekend) {
    const fixed = fixedCents[String(hours)];
    if (Number.isInteger(fixed) && fixed > 0) return fixed;
    return (weekend ? weekendRateCents(hourlyCents, markupBp) : hourlyCents) * hours;
}

export function ownerNetCents(charter, cleaningCents, commissionBp) {
    return charter - Math.round((charter * commissionBp) / 10000) + cleaningCents;
}
