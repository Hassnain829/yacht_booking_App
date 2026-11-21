/**
 * Owner calendar: month grid with trips and blocked days, plus a day panel to block / unblock.
 * PHASE 1 DEMO: blocks the owner adds or removes are kept in this browser (localStorage).
 * Phase 3: POST/DELETE /owner/boats/{ulid}/blocks (BoatPolicy, FormRequest, named rate limiter).
 */
import { html, setHtml, trusted, safeUrl } from '../lib/dom.js';
import { renderIcons } from '../lib/icons.js';
import { toast } from '../lib/toast.js';
import { formatDate, formatTime, parseIsoDate } from '../lib/dates.js';

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const iso = (y, m, d) => `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

export default function ownerCalendar() {
    const root = document.getElementById('owner-calendar');
    if (!root) return;

    const data = JSON.parse(root.dataset.calendar);
    const monthEl = root.querySelector('[data-cal-month]');
    const dayEl = root.querySelector('[data-cal-day]');
    const storageKey = `cyb_blocks_${data.boat}`;

    /* ---------------- Blocks = server blocks + local changes (demo) ---------------- */
    const loadLocal = () => {
        try {
            const v = JSON.parse(localStorage.getItem(storageKey) ?? '{}');
            return {
                added: v && typeof v.added === 'object' && !Array.isArray(v.added) ? v.added : {},
                removed: Array.isArray(v?.removed) ? v.removed.filter((d) => typeof d === 'string') : [],
            };
        } catch {
            return { added: {}, removed: [] };
        }
    };
    let local = loadLocal();
    const saveLocal = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(local));
        } catch {
            /* storage disabled */
        }
    };
    const blocks = () => {
        const map = new Map(data.blocks.filter((b) => !local.removed.includes(b.date)).map((b) => [b.date, b.reason]));
        for (const [date, reason] of Object.entries(local.added)) {
            if (/^\d{4}-\d{2}-\d{2}$/.test(date) && typeof reason === 'string') map.set(date, reason.slice(0, 60));
        }
        return map;
    };

    const trips = new Map();
    for (const trip of data.bookings) {
        if (!trips.has(trip.date)) trips.set(trip.date, []);
        trips.get(trip.date).push(trip);
    }

    const today = parseIsoDate(data.today);
    let month = new Date(today.getFullYear(), today.getMonth(), 1);
    let selected = data.today;
    const minMonth = new Date(today.getFullYear(), today.getMonth() - 3, 1);
    const maxMonth = new Date(today.getFullYear(), today.getMonth() + 18, 1);

    /* ---------------- Month grid ---------------- */
    function renderMonth() {
        const y = month.getFullYear();
        const m = month.getMonth();
        const pad = new Date(y, m, 1).getDay();
        const days = new Date(y, m + 1, 0).getDate();
        const blocked = blocks();
        const cells = [];
        for (let i = 0; i < pad; i++) cells.push(trusted('<span aria-hidden="true"></span>'));
        for (let d = 1; d <= days; d++) {
            const date = iso(y, m, d);
            const dayTrips = trips.get(date) ?? [];
            const isBlocked = blocked.has(date);
            const isPast = date < data.today;
            const isToday = date === data.today;
            const isSelected = date === selected;
            const state = dayTrips.length ? `${dayTrips.length} ${dayTrips.length === 1 ? 'trip' : 'trips'}` : isBlocked ? 'blocked' : isPast ? 'past' : 'available';
            const cls = [
                'relative flex min-h-[3.25rem] flex-col items-start rounded-xl p-1.5 text-left text-xs transition sm:min-h-[5.5rem] sm:p-2',
                isBlocked ? 'bg-navy-100 text-navy-400' : dayTrips.length ? 'bg-brass-50 text-navy-950' : isPast ? 'text-navy-300' : 'text-navy-950 hover:bg-navy-50',
                isSelected ? 'ring-2 ring-navy-950' : isToday ? 'ring-1 ring-brass-400' : '',
            ].join(' ');
            cells.push(html`<button type="button" data-date="${date}" class="${cls}" aria-pressed="${isSelected}" aria-label="${formatDate(date)}, ${state}">
                <span class="font-bold">${d}</span>
                ${dayTrips.map((t) => html`<span class="mt-1 hidden w-full truncate rounded-md bg-navy-950 px-1.5 py-0.5 text-[10px] font-bold text-white sm:block">${formatTime(t.start)}</span>`)}
                ${isBlocked ? trusted('<span class="mt-1 hidden text-[10px] font-bold uppercase tracking-wider sm:block">Blocked</span>') : ''}
                ${dayTrips.length ? trusted('<span class="absolute bottom-1.5 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-navy-950 sm:hidden"></span>') : ''}
                ${isBlocked ? trusted('<span class="absolute bottom-1.5 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-navy-400 sm:hidden"></span>') : ''}
            </button>`);
        }

        setHtml(monthEl, html`
            <div class="flex items-center justify-between">
                <button type="button" data-month="-1" ${month <= minMonth ? trusted('disabled') : ''} class="grid h-10 w-10 place-items-center rounded-full ring-1 ring-navy-200 hover:bg-navy-50 disabled:opacity-30" aria-label="Previous month"><i data-lucide="chevron-left" class="h-4 w-4"></i></button>
                <p class="font-display text-xl text-navy-950" aria-live="polite">${month.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}</p>
                <button type="button" data-month="1" ${month >= maxMonth ? trusted('disabled') : ''} class="grid h-10 w-10 place-items-center rounded-full ring-1 ring-navy-200 hover:bg-navy-50 disabled:opacity-30" aria-label="Next month"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
            </div>
            <div class="mt-5 grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase tracking-wider text-navy-400" aria-hidden="true">${WEEKDAYS.map((w) => html`<span>${w}</span>`)}</div>
            <div class="mt-2 grid grid-cols-7 gap-1">${cells}</div>
            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-[11px] font-semibold text-navy-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-navy-950"></span>Trip</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-navy-200"></span>Blocked</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full ring-1 ring-brass-400"></span>Today</span>
            </div>`);
        renderIcons(monthEl);
    }

    /* ---------------- Day panel ---------------- */
    function renderDay() {
        const dayTrips = trips.get(selected) ?? [];
        const reason = blocks().get(selected);
        const isPast = selected < data.today;
        let action;
        if (isPast) {
            action = trusted('<p class="mt-5 text-sm text-navy-400">This date has passed.</p>');
        } else if (dayTrips.length) {
            action = trusted('<p class="mt-5 rounded-2xl bg-navy-50 p-4 text-xs leading-relaxed text-navy-600">This day has a trip, so it can\'t be blocked. To cancel a confirmed trip, contact our team — the guest gets a full refund.</p>');
        } else if (reason !== undefined) {
            action = html`
                <div class="mt-5 rounded-2xl bg-navy-100/70 p-4">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-navy-500">Blocked</p>
                    <p class="mt-1 text-sm font-semibold text-navy-900">${reason || 'No reason given'}</p>
                </div>
                <button type="button" data-unblock class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border border-navy-200 px-6 py-3 text-sm font-bold text-navy-900 transition hover:border-navy-950 hover:bg-navy-950 hover:text-white"><i data-lucide="calendar-check" class="h-4 w-4"></i>Unblock this day</button>`;
        } else {
            action = html`
                <form class="mt-5" data-block-form novalidate>
                    <label for="block-reason" class="text-sm font-bold text-navy-800">Block this day</label>
                    <input id="block-reason" type="text" maxlength="60" autocomplete="off" placeholder="Reason (optional), e.g. maintenance" class="mt-2 w-full rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm text-navy-950 placeholder:text-navy-300 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100">
                    <button type="submit" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full bg-navy-950 px-6 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-navy-800"><i data-lucide="calendar-x" class="h-4 w-4"></i>Block this day</button>
                    <p class="mt-2 text-xs text-navy-400">Guests can't book a blocked day.</p>
                </form>`;
        }

        setHtml(dayEl, html`
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brass-600">${selected === data.today ? 'Today' : 'Selected day'}</p>
            <h2 class="mt-1 font-display text-2xl text-navy-950">${formatDate(selected, { weekday: 'long', month: 'long', day: 'numeric' })}</h2>
            ${dayTrips.length ? html`<ul class="mt-5 space-y-3">${dayTrips.map((t) => html`
                <li class="rounded-2xl bg-navy-950 p-4 text-white">
                    <p class="text-sm font-bold">${formatTime(t.start)} – ${formatTime(t.end)}</p>
                    <p class="mt-1 text-sm text-white/80">${t.experience}</p>
                    <p class="mt-2 text-xs text-white/60">${t.guests} guests · ${t.customer} · ${t.ref}</p>
                    <p class="mt-1 text-xs ${t.captain ? 'text-brass-300' : 'text-amber-300'}">${t.captain ? `Captain: ${t.captain}` : 'Captain to be assigned'}</p>
                </li>`)}</ul>
                <a href="${safeUrl(data.bookingsUrl)}" class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-brass-600 hover:text-brass-700">All bookings <i data-lucide="arrow-right" class="h-4 w-4"></i></a>` : trusted('<p class="mt-3 text-sm text-navy-500">No trips on this day.</p>')}
            ${action}`);
        renderIcons(dayEl);
    }

    const render = () => {
        renderMonth();
        renderDay();
    };

    /* ---------------- Events ---------------- */
    root.addEventListener('click', (event) => {
        const t = event.target instanceof Element ? event.target : null;
        const day = t?.closest('[data-date]');
        if (day) {
            selected = day.dataset.date;
            render();
            root.querySelector(`[data-date="${selected}"]`)?.focus({ preventScroll: true });
            if (window.matchMedia('(max-width: 1023px)').matches) dayEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }
        const nav = t?.closest('[data-month]');
        if (nav && !nav.disabled) {
            month = new Date(month.getFullYear(), month.getMonth() + Number(nav.dataset.month), 1);
            renderMonth();
            return;
        }
        if (t?.closest('[data-unblock]')) {
            delete local.added[selected];
            if (data.blocks.some((b) => b.date === selected) && !local.removed.includes(selected)) local.removed.push(selected);
            saveLocal();
            render();
            toast('Day unblocked (saved on this device — demo)');
        }
    });

    root.addEventListener('submit', (event) => {
        if (!event.target.matches('[data-block-form]')) return;
        event.preventDefault();
        const reason = event.target.querySelector('#block-reason').value.trim().slice(0, 60);
        local.added[selected] = reason;
        local.removed = local.removed.filter((d) => d !== selected);
        saveLocal();
        render();
        toast('Day blocked (saved on this device — demo)');
    });

    window.addEventListener('storage', (event) => {
        if (event.key === storageKey) {
            local = loadLocal();
            render();
        }
    });

    render();
}
