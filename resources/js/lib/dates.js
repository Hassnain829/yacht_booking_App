/**
 * Date helpers (ported from the mockup's CYB.date / CYB.fmt).
 *
 * - Calendar dates are 'YYYY-MM-DD' strings: no timezone, no surprises.
 * - Trip times are 'HH:MM' in Chicago time.
 * - Server timestamps are UTC ISO strings and are shown in Chicago time.
 */

export const BUSINESS_TIMEZONE = 'America/Chicago';

const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const TIME = /^([01]\d|2[0-3]):([0-5]\d)$/;

export function isIsoDate(value) {
    const match = ISO_DATE.exec(String(value ?? ''));
    if (!match) return false;
    const [, y, m, d] = match.map(Number);
    const date = new Date(y, m - 1, d);
    return date.getFullYear() === y && date.getMonth() === m - 1 && date.getDate() === d;
}

/** 'YYYY-MM-DD' → local Date at midnight (for calendar maths only). Throws on bad input. */
export function parseIsoDate(iso) {
    if (!isIsoDate(iso)) throw new RangeError(`Invalid date: ${iso}`);
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d);
}

/** Date → 'YYYY-MM-DD' (local calendar date). */
export function toIsoDate(date) {
    return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
}

export function addDays(iso, days) {
    const date = parseIsoDate(iso);
    date.setDate(date.getDate() + days);
    return toIsoDate(date);
}

/** Weekend pricing days: Friday, Saturday, Sunday. */
export function isWeekend(iso) {
    const day = parseIsoDate(iso).getDay();
    return day === 0 || day === 5 || day === 6;
}

/** Today's date in Chicago as 'YYYY-MM-DD', whatever the visitor's own timezone is. */
export function todayInChicago(now = new Date()) {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: BUSINESS_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(now);
    const get = (type) => parts.find((p) => p.type === type).value;
    return `${get('year')}-${get('month')}-${get('day')}`;
}

/** 'YYYY-MM-DD' → "Sat, Jun 14, 2026" (or custom Intl options). */
export function formatDate(iso, options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) {
    return parseIsoDate(iso).toLocaleDateString('en-US', options);
}

/** '17:30' → '5:30 PM' */
export function formatTime(time) {
    const match = TIME.exec(String(time ?? ''));
    if (!match) throw new RangeError(`Invalid time: ${time}`);
    const hours = Number(match[1]);
    return `${((hours + 11) % 12) + 1}:${match[2]} ${hours >= 12 ? 'PM' : 'AM'}`;
}

/** '17:30' + 3 → '20:30' (wraps past midnight: '22:00' + 3 → '01:00'). */
export function addHoursToTime(time, hours) {
    const match = TIME.exec(String(time ?? ''));
    if (!match) throw new RangeError(`Invalid time: ${time}`);
    const total = (Number(match[1]) * 60 + Number(match[2]) + Math.round(hours * 60)) % (24 * 60);
    const minutes = (total + 24 * 60) % (24 * 60);
    return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

/** UTC ISO timestamp from the server → "Jun 14, 2026, 5:30 PM" in Chicago time. */
export function formatDateTimeInChicago(isoTimestamp, options = { dateStyle: 'medium', timeStyle: 'short' }) {
    const date = new Date(isoTimestamp);
    if (Number.isNaN(date.getTime())) throw new RangeError(`Invalid timestamp: ${isoTimestamp}`);
    return new Intl.DateTimeFormat('en-US', { ...options, timeZone: BUSINESS_TIMEZONE }).format(date);
}

const MONTH = /^(\d{4})-(\d{2})$/;

/** '2027-06-04' → '2027-06' */
export function monthOf(iso) {
    parseIsoDate(iso);
    return iso.slice(0, 7);
}

/** '2027-12' + 1 → '2028-01' (any whole number of months, also negative). */
export function addMonths(month, count) {
    const match = MONTH.exec(String(month ?? ''));
    if (!match) throw new RangeError(`Invalid month: ${month}`);
    const index = Number(match[1]) * 12 + Number(match[2]) - 1 + count;
    return `${Math.floor(index / 12)}-${String((index % 12) + 1).padStart(2, '0')}`;
}

/** '2027-06' → "June 2027" */
export function formatMonth(month) {
    return parseIsoDate(`${addMonths(month, 0)}-01`).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

/**
 * A month as calendar weeks (Sunday first): '2027-06' → [[null, null, '2027-06-01', …], …, ['2027-06-27', …, null]].
 * Days outside the month are null, so every week has 7 cells.
 */
export function monthGrid(month) {
    const first = parseIsoDate(`${addMonths(month, 0)}-01`);
    const cells = Array(first.getDay()).fill(null);
    for (let day = first; day.getMonth() === first.getMonth(); day = new Date(day.getFullYear(), day.getMonth(), day.getDate() + 1)) {
        cells.push(toIsoDate(day));
    }
    while (cells.length % 7 !== 0) cells.push(null);
    return Array.from({ length: cells.length / 7 }, (_, week) => cells.slice(week * 7, week * 7 + 7));
}
