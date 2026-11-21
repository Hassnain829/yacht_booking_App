/**
 * PHASE 1 DEMO price estimate (port of the mockup's CYB.quote, in cents).
 * Must match app/Support/Demo/DemoQuote.php exactly (tests/js/demo-quote.test.js).
 *
 * Only for showing an instant estimate in the browser. Phase 4 replaces this with the server
 * endpoint POST /book/quote, and the server ALWAYS recalculates at checkout.
 */
import { formatCents } from './money.js';
import { isIsoDate, isWeekend } from './dates.js';

/** Durations offered for a boat + experience (never shorter than the boat minimum). */
export function durationsFor(yacht, experience) {
    if (!experience) return [yacht.min_hours];
    const list = experience.durations.filter((h) => h >= yacht.min_hours);
    return list.length ? list : [yacht.min_hours];
}

/**
 * @param {object} yacht   { hourly_rate_cents, captain_rate_cents, crew_count, cleaning_fee_cents, min_hours }
 * @param {object|null} experience { name, package_fee_cents }
 * @param {object} pricing { weekend_multiplier_bp, tax_rate_bp, deposit_bp, promos: [{code,type,value}] }
 * @param {object} options { date, hours, guests, addons: [{slug,name,price_cents,unit}], promo, gratuity, plan }
 */
export function quote(yacht, experience, pricing, options = {}) {
    const hours = Number(options.hours) || yacht.min_hours;
    const guests = Number(options.guests) || 2;
    const weekend = isIsoDate(options.date) ? isWeekend(options.date) : false;

    const rate = weekend ? Math.round((yacht.hourly_rate_cents * pricing.weekend_multiplier_bp) / 10000 / 100) * 100 : yacht.hourly_rate_cents;
    const charter = rate * hours;
    const captain = yacht.captain_rate_cents * hours;

    const lines = [
        { key: 'charter', label: 'Yacht charter', sub: `${formatCents(rate)}/hr × ${hours} hrs${weekend ? ' · weekend rate' : ''}`, amount: charter },
        {
            key: 'captain',
            label: yacht.crew_count > 1 ? `Captain & crew (${yacht.crew_count})` : 'Licensed USCG captain',
            sub: `${formatCents(yacht.captain_rate_cents)}/hr × ${hours} hrs · included`,
            amount: captain,
        },
    ];
    if (experience && experience.package_fee_cents > 0) {
        lines.push({ key: 'package', label: `${experience.name} package`, sub: 'Experience extras', amount: experience.package_fee_cents });
    }
    for (const addon of options.addons ?? []) {
        const perGuest = addon.unit === 'per_guest';
        lines.push({
            key: `addon-${addon.slug}`,
            label: addon.name,
            sub: perGuest ? `${formatCents(addon.price_cents)} × ${guests} guests` : 'Add-on',
            amount: perGuest ? addon.price_cents * guests : addon.price_cents,
        });
    }
    lines.push({ key: 'cleaning', label: 'Fuel & cleaning', sub: 'Flat fee', amount: yacht.cleaning_fee_cents });

    const subtotal = lines.reduce((sum, line) => sum + line.amount, 0);

    const code = String(options.promo ?? '').trim().toUpperCase();
    const promo = code ? (pricing.promos ?? []).find((p) => p.code === code) ?? null : null;
    const discount = promo ? (promo.type === 'percent' ? Math.round((charter * promo.value) / 100) : promo.value) : 0;

    const taxable = Math.max(0, subtotal - discount);
    const tax = Math.round((taxable * pricing.tax_rate_bp) / 10000);
    const gratuityPct = Number(options.gratuity) || 0;
    const gratuity = Math.round(((charter + captain) * gratuityPct) / 100);
    const total = taxable + tax + gratuity;
    const deposit = Math.round((total * pricing.deposit_bp) / 10000);
    const dueNow = options.plan === 'full' ? total : deposit;

    return { hours, guests, weekend, rate, charter, captain, lines, subtotal, discount, promo, tax, gratuity, gratuityPct, total, deposit, dueNow, balance: total - dueNow };
}
