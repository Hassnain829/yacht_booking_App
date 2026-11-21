/**
 * PHASE 1 DEMO availability (port of the mockup's CYB.availability).
 * Deterministic fake data: the same yacht + date always shows the same sold-out days and taken slots.
 *
 * Phase 4 replaces this module with GET /book/availability (AvailabilityService on the server:
 * weekly hours, blocks, holds/bookings + buffer, advance notice, season).
 */
import { parseIsoDate, todayInChicago, BUSINESS_TIMEZONE } from './dates.js';

/** FNV-1a hash → stable pseudo-random numbers. */
function hash(text) {
    let h = 2166136261;
    for (let i = 0; i < text.length; i++) {
        h ^= text.charCodeAt(i);
        h = Math.imul(h, 16777619);
    }
    return h >>> 0;
}

function chicagoHourNow() {
    return Number(new Intl.DateTimeFormat('en-US', { timeZone: BUSINESS_TIMEZONE, hour: 'numeric', hourCycle: 'h23' }).format(new Date()));
}

/** 'past' | 'unavailable' | 'soldout' | 'limited' | 'available' */
export function dayStatus(yachtSlug, iso, experience) {
    if (iso < todayInChicago()) return 'past';
    if (experience?.allowed_weekdays && !experience.allowed_weekdays.includes(parseIsoDate(iso).getDay())) return 'unavailable';
    const h = hash((yachtSlug || 'any') + iso) % 100;
    if (h < 9) return 'soldout';
    if (h < 32) return 'limited';
    return 'available';
}

export const isOpen = (status) => status === 'available' || status === 'limited';

/** Departure slots for a day: [{ time: '17:30', available: true }, …] */
export function slots(yachtSlug, iso, experience) {
    if (!experience || !iso) return [];
    const status = dayStatus(yachtSlug, iso, experience);
    const open = isOpen(status);
    const isToday = iso === todayInChicago();
    const hourNow = chicagoHourNow();
    const list = experience.time_slots.map((time) => {
        let available = open;
        if (available) {
            const h = hash(yachtSlug + iso + time) % 100;
            available = status === 'limited' ? h >= 60 : h >= 20;
        }
        if (isToday && Number(time.split(':')[0]) <= hourNow + 2) available = false;
        return { time, available };
    });
    if (open && !isToday && !list.some((s) => s.available)) list[list.length - 1].available = true;
    return list;
}
