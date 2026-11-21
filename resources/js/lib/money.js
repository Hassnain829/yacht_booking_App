/**
 * Money is always handled as integer cents (PLAN §6). Format only for display.
 * Prices shown in the browser always come from the server: never calculate a price here.
 */

const WHOLE = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});
const EXACT = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/**
 * 125000 → "$1,250"   125050 → "$1,250.50"   -5000 → "-$50"
 * @param {number} cents
 * @param {{ cents?: 'auto' | 'always' | 'never' }} [options] 'auto' shows cents only when not .00
 */
export function formatCents(cents, { cents: mode = 'auto' } = {}) {
    const value = Number.isFinite(Number(cents)) ? Math.round(Number(cents)) : 0;
    const showCents = mode === 'always' || (mode === 'auto' && value % 100 !== 0);
    // `value === 0` is also true for -0, so a rounded tiny negative never prints "-$0".
    return (showCents ? EXACT : WHOLE).format(value === 0 ? 0 : value / 100);
}

/** Basis points → percent text. 2000 → "20%", 1250 → "12.5%". */
export function formatBp(bp) {
    const percent = Math.round(Number(bp) || 0) / 100;
    return `${Number.isInteger(percent) ? percent : percent.toFixed(2).replace(/0+$/, '')}%`;
}
