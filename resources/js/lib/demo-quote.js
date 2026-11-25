/**
 * PHASE 1 DEMO price estimate (port of the mockup's CYB.quote, in cents).
 * Must match app/Support/Demo/DemoQuote.php exactly (tests/js/demo-quote.test.js).
 *
 * Only for the demo checkout's instant totals until 4.5 (the boat page and the pricing page ask the quote
 * endpoint since 4.3). The server ALWAYS recalculates at checkout. Paid in full (D22): no deposit. The experience
 * is free and adds nothing; add-ons only when picked (2026-11-25).
 */
import { formatCents } from './money.js';
import { isIsoDate, isWeekend } from './dates.js';

/**
 * @param {object} yacht   { hourly_rate_cents, captain_rate_cents, crew_count, cleaning_fee_cents, min_hours }
 * @param {object|null} experience { slug, name } (free: it adds no line)
 * @param {object} pricing { weekend_multiplier_bp, tax_rate_bp, promos: [{code,type,value}] }
 * @param {object} options { date, hours, guests, addons: [{slug,name,price_cents,unit}], promo, gratuity }
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

    return { hours, guests, weekend, rate, charter, captain, lines, subtotal, discount, promo, tax, gratuity, gratuityPct, total };
}
