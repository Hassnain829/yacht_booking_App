/**
 * Booking wizard — 4 steps: Experience → Yacht & guests → Date & time → Extras → /checkout
 * (port of the mockup's booking.js). Everything is rendered with the auto-escaping html`` template.
 *
 * State lives in the URL (shareable, refresh-safe). The server re-validates every value
 * (App\Support\Demo\BookingSelection) on /book and /checkout, so nothing here is trusted.
 * Phase 4: availability + quote come from JSON endpoints instead of the demo modules.
 */
import { html, setHtml, trusted } from '../lib/dom.js';
import { renderIcons } from '../lib/icons.js';
import { toast } from '../lib/toast.js';
import { formatCents } from '../lib/money.js';
import { quote, durationsFor } from '../lib/demo-quote.js';
import { dayStatus, slots, isOpen } from '../lib/demo-availability.js';
import { addDays, formatDate, formatTime, addHoursToTime, isWeekend, parseIsoDate, todayInChicago } from '../lib/dates.js';

const WHOLE = { cents: 'never' };
const money = (cents) => formatCents(cents, WHOLE);
const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
const STEPS = [
    { n: 1, label: 'Experience' },
    { n: 2, label: 'Yacht & Guests' },
    { n: 3, label: 'Date & Time' },
    { n: 4, label: 'Extras' },
];

export default function booking() {
    const root = document.getElementById('booking-wizard');
    if (!root) return;

    const data = JSON.parse(root.dataset.wizard);
    const yachts = Object.fromEntries(data.yachts.map((y) => [y.slug, y]));
    const experiences = Object.fromEntries(data.experiences.map((e) => [e.slug, e]));
    const addons = Object.fromEntries(data.addons.map((a) => [a.slug, a]));
    const $ = (id) => document.getElementById(id);

    const state = { step: 1, notes: '', ...data.initial };
    const Y = () => yachts[state.yacht] ?? null;
    const E = () => experiences[state.experience] ?? null;
    const startingAt = (y, e) => {
        const hours = durationsFor(y, e)[0];
        return (y.hourly_rate_cents + y.captain_rate_cents) * hours + (e?.package_fee_cents ?? 0);
    };
    const weekendRate = (y) => Math.round((y.hourly_rate_cents * data.pricing.weekend_multiplier_bp) / 10000 / 100) * 100;

    let calendarMonth = (() => {
        const d = parseIsoDate(state.date ?? todayInChicago());
        return new Date(d.getFullYear(), d.getMonth(), 1);
    })();
    let lastStep = 0;

    /* ---------------- Rules (same as the server's BookingSelection) ---------------- */
    function normalize() {
        const y = Y();
        const e = E();
        if (y && (!y.booking_enabled || (e && !y.experiences.includes(e.slug)) || state.guests > y.max_guests)) state.yacht = null;
        const y2 = Y();
        if (y2 && e) {
            const durations = durationsFor(y2, e);
            if (!durations.includes(state.hours)) state.hours = durations[0];
            if (state.date && !isOpen(dayStatus(y2.slug, state.date, e))) {
                state.date = null;
                state.time = null;
            }
            if (state.date && state.time && !slots(y2.slug, state.date, e).find((s) => s.time === state.time)?.available) state.time = null;
        } else {
            state.time = null;
        }
    }
    const done = {
        1: () => Boolean(state.experience),
        2: () => Boolean(state.experience && state.yacht),
        3: () => done[2]() && Boolean(state.date && state.time && state.hours),
        4: () => done[3](),
    };
    const maxStep = () => (!done[1]() ? 1 : !done[2]() ? 2 : !done[3]() ? 3 : 4);

    function goTo(n, scroll = true) {
        state.step = clamp(n, 1, maxStep());
        render();
        if (scroll) $('step-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ---------------- Templates ---------------- */
    function stepper() {
        const max = maxStep();
        return html`${STEPS.map((s) => {
            const current = s.n === state.step;
            const complete = s.n !== state.step && (s.n < state.step || (s.n < 4 && done[s.n]()));
            const reachable = s.n <= max;
            const bar = current ? 'w-1/2' : complete || s.n < state.step ? 'w-full' : 'w-0';
            return html`<li>
                <button type="button" data-goto="${s.n}" ${reachable ? '' : trusted('disabled')} class="group flex w-full flex-col gap-3 text-left disabled:cursor-not-allowed" ${current ? trusted('aria-current="step"') : ''}>
                    <span class="h-1 w-full overflow-hidden rounded-full bg-white/10"><span class="block h-full rounded-full bg-brass-400 transition-all duration-500 ${bar}"></span></span>
                    <span class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-extrabold transition ${current ? 'bg-brass-400 text-navy-950' : complete ? 'bg-white text-navy-950' : 'bg-white/10 text-white/60'}">
                            ${complete && !current ? trusted('<i data-lucide="check" class="h-4 w-4"></i>') : s.n}
                        </span>
                        <span class="hidden truncate text-sm font-bold md:block ${current ? 'text-white' : reachable ? 'text-white/70 group-hover:text-white' : 'text-white/35'}">${s.label}</span>
                    </span>
                </button>
            </li>`;
        })}`;
    }

    function panel(title, subtitle, body, headRight = '') {
        const canNext = done[state.step]();
        return html`<div class="overflow-hidden rounded-[2rem] bg-white shadow-card ring-1 ring-navy-100">
            <div class="flex flex-col justify-between gap-2 border-b border-navy-100 px-6 py-6 md:flex-row md:items-end md:px-8">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-brass-600">Step ${state.step} of 4</p>
                    <h2 class="mt-1 font-display text-3xl text-navy-950">${title}</h2>
                    <p class="mt-1 text-sm text-navy-500">${subtitle}</p>
                </div>
                ${headRight}
            </div>
            <div class="p-6 md:p-8">${body}</div>
            <div class="flex items-center justify-between gap-4 border-t border-navy-100 bg-navy-50/50 px-6 py-5 md:px-8">
                ${state.step > 1
                    ? html`<button type="button" data-back class="inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold text-navy-600 hover:bg-white hover:text-navy-950"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back</button>`
                    : html`<span></span>`}
                ${state.step < 4
                    ? html`<button type="button" data-next ${canNext ? '' : trusted('disabled')} class="inline-flex items-center gap-2 rounded-full bg-navy-950 px-7 py-3.5 text-sm font-bold text-white transition hover:bg-navy-800 disabled:cursor-not-allowed disabled:bg-navy-200">Continue <i data-lucide="arrow-right" class="h-4 w-4"></i></button>`
                    : html`<button type="button" data-checkout class="inline-flex items-center justify-center gap-2 rounded-full bg-brass-400 px-7 py-3.5 text-sm font-bold text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300">Continue to checkout <i data-lucide="arrow-right" class="h-4 w-4"></i></button>`}
            </div>
        </div>`;
    }

    /* ---- Step 1: experience ---- */
    function stepExperience() {
        const y = Y();
        const cards = data.experiences.map((e) => {
            const selected = state.experience === e.slug;
            const offered = !y || y.experiences.includes(e.slug);
            const boats = data.yachts.filter((b) => b.booking_enabled && b.experiences.includes(e.slug));
            const from = y && offered ? startingAt(y, e) : Math.min(...boats.map((b) => startingAt(b, e)));
            return html`<button type="button" data-exp="${e.slug}" aria-pressed="${selected}" class="group relative flex flex-col overflow-hidden rounded-2xl bg-white text-left ring-2 transition ${selected ? 'shadow-card ring-brass-400' : 'ring-navy-100 hover:ring-navy-300'}">
                <span class="relative block h-36 w-full overflow-hidden">
                    <img src="${e.image}" alt="" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                    <span class="absolute inset-0 bg-gradient-to-t from-navy-950/80 to-transparent"></span>
                    <span class="absolute bottom-3 left-4 inline-flex items-center gap-1.5 text-xs font-bold text-white"><i data-lucide="${e.icon}" class="h-4 w-4 text-brass-300"></i>${e.tagline}</span>
                    ${selected ? trusted('<span class="absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-full bg-brass-400 text-navy-950"><i data-lucide="check" class="h-4 w-4"></i></span>') : ''}
                </span>
                <span class="flex flex-1 flex-col p-4">
                    <span class="flex items-start justify-between gap-3"><span class="font-bold text-navy-950">${e.name}</span><span class="whitespace-nowrap text-xs font-bold text-brass-700">from ${Number.isFinite(from) ? money(from) : '—'}</span></span>
                    <span class="mt-1 text-xs text-navy-500">${e.durations[0]}–${e.durations[e.durations.length - 1]} hrs · ${e.allowed_weekdays ? 'Wed & Sat nights' : 'Daily'} · ${e.package_fee_cents ? `+${money(e.package_fee_cents)} package` : 'no package fee'}</span>
                    ${!offered ? html`<span class="mt-2 text-xs font-semibold text-amber-700">Not offered on ${y.title} — you'll choose another yacht next</span>` : ''}
                </span>
            </button>`;
        });
        return panel('Choose your experience', "Each experience sets the route, timing and what's included.", html`<div class="grid gap-4 sm:grid-cols-2">${cards}</div>`);
    }

    /* ---- Step 2: yacht + guests ---- */
    function stepYacht() {
        const e = E();
        const rows = data.yachts
            .map((y) => ({ y, offered: y.experiences.includes(e.slug), fits: y.max_guests >= state.guests }))
            .sort((a, b) => Number(b.y.booking_enabled && b.offered && b.fits) - Number(a.y.booking_enabled && a.offered && a.fits) || a.y.hourly_rate_cents - b.y.hourly_rate_cents)
            .map(({ y, offered, fits }) => {
                const ok = y.booking_enabled && offered && fits;
                const selected = state.yacht === y.slug;
                const hours = durationsFor(y, e)[0];
                const reason = !y.booking_enabled ? 'Unavailable' : !offered ? `Not offered for ${e.name}` : !fits ? `Max ${y.max_guests} guests` : '';
                return html`<div data-yacht-row="${ok ? y.slug : ''}" class="flex flex-col overflow-hidden rounded-2xl bg-white ring-2 transition sm:flex-row ${selected ? 'shadow-card ring-brass-400' : ok ? 'cursor-pointer ring-navy-100 hover:ring-navy-300' : 'opacity-50 ring-navy-100'}">
                    <div class="relative h-44 shrink-0 sm:h-auto sm:w-56">
                        <img src="${y.cover}" alt="${y.title}" class="absolute inset-0 h-full w-full object-cover">
                        ${selected ? trusted('<span class="absolute left-3 top-3 grid h-8 w-8 place-items-center rounded-full bg-brass-400 text-navy-950"><i data-lucide="check" class="h-4 w-4"></i></span>') : ''}
                    </div>
                    <div class="flex flex-1 flex-col justify-between gap-4 p-5 md:flex-row md:items-center">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-brass-600">${y.category} · ${y.length_ft} ft · ${y.harbor}</p>
                            <h3 class="mt-1 font-display text-2xl text-navy-950">${y.title}</h3>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-navy-500">
                                <span class="inline-flex items-center gap-1"><i data-lucide="users" class="h-3.5 w-3.5"></i>Up to ${y.max_guests}</span>
                                <span class="inline-flex items-center gap-1"><i data-lucide="star" class="h-3.5 w-3.5 fill-brass-400 text-brass-400"></i>${y.reviews_count > 0 && y.rating_avg ? `${Number(y.rating_avg).toFixed(1)} (${y.reviews_count})` : 'New'}</span>
                                <span class="inline-flex items-center gap-1"><i data-lucide="ship-wheel" class="h-3.5 w-3.5"></i>${y.crew_count > 1 ? 'Captain + crew' : 'Captain'} included</span>
                            </div>
                            ${reason
                                ? html`<p class="mt-3 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800">${reason}</p>`
                                : html`<a href="${y.url}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-navy-500 hover:text-brass-600">View photos &amp; details <i data-lucide="external-link" class="h-3 w-3"></i></a>`}
                        </div>
                        <div class="flex shrink-0 items-end justify-between gap-4 md:flex-col md:items-end md:text-right">
                            <div>
                                <p class="text-2xl font-extrabold text-navy-950">${money(y.hourly_rate_cents)}<span class="text-sm font-semibold text-navy-400">/hr</span></p>
                                <p class="text-xs text-navy-500">${hours} hrs incl. captain from ${money(startingAt(y, e))}</p>
                            </div>
                            <button type="button" data-yacht="${y.slug}" ${ok ? '' : trusted('disabled')} class="rounded-full px-5 py-2.5 text-xs font-bold transition disabled:cursor-not-allowed ${selected ? 'bg-brass-400 text-navy-950' : 'bg-navy-950 text-white hover:bg-navy-800 disabled:bg-navy-200'}">${selected ? 'Selected' : 'Select'}<span class="sr-only"> ${y.title}</span></button>
                        </div>
                    </div>
                </div>`;
            });

        const quick = [2, 6, 13, 25, 49, 80];
        const guestsBox = html`<div class="mb-6 flex flex-col justify-between gap-4 rounded-2xl bg-navy-50 p-5 md:flex-row md:items-center">
            <div><p class="font-bold text-navy-950">How many guests?</p><p class="text-xs text-navy-500">Everyone on board, including kids. USCG passenger limits apply.</p></div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center rounded-full bg-white p-1 ring-1 ring-navy-200">
                    <button type="button" data-guests="-1" class="grid h-9 w-9 place-items-center rounded-full hover:bg-navy-50" aria-label="Fewer guests"><i data-lucide="minus" class="h-4 w-4"></i></button>
                    <input id="guests-input" type="number" min="1" max="80" value="${state.guests}" inputmode="numeric" class="w-14 bg-transparent text-center text-base font-extrabold text-navy-950 focus:outline-hidden" aria-label="Number of guests">
                    <button type="button" data-guests="1" class="grid h-9 w-9 place-items-center rounded-full hover:bg-navy-50" aria-label="More guests"><i data-lucide="plus" class="h-4 w-4"></i></button>
                </div>
                <div class="hidden gap-1 sm:flex">${quick.map((q) => html`<button type="button" data-guests-set="${q}" class="rounded-full px-3 py-1.5 text-xs font-bold ${state.guests === q ? 'bg-navy-950 text-white' : 'bg-white text-navy-600 ring-1 ring-navy-200 hover:ring-navy-950'}">${q}</button>`)}</div>
            </div>
        </div>`;
        return panel('Choose your yacht', html`Showing yachts for <strong class="text-navy-800">${e.name}</strong> · ${state.guests} guests`, html`${guestsBox}<div class="space-y-4">${rows}</div>`);
    }

    /* ---- Step 3: date, duration, time ---- */
    function calendar(y, e) {
        const m = calendarMonth;
        const startPad = new Date(m.getFullYear(), m.getMonth(), 1).getDay();
        const days = new Date(m.getFullYear(), m.getMonth() + 1, 0).getDate();
        const today = parseIsoDate(todayInChicago());
        const canPrev = m > new Date(today.getFullYear(), today.getMonth(), 1);
        const cells = [];
        for (let i = 0; i < startPad; i++) cells.push(html`<span></span>`);
        for (let d = 1; d <= days; d++) {
            const iso = `${m.getFullYear()}-${String(m.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const status = dayStatus(y.slug, iso, e);
            const selected = state.date === iso;
            const open = isOpen(status);
            const rate = isWeekend(iso) ? weekendRate(y) : y.hourly_rate_cents;
            let cls = 'relative flex aspect-square flex-col items-center justify-center rounded-xl text-sm transition ';
            let sub = '';
            if (selected) {
                cls += 'bg-navy-950 font-extrabold text-white shadow-lg';
                sub = html`<span class="text-[9px] font-semibold text-brass-300">${money(rate)}</span>`;
            } else if (open) {
                cls += 'font-bold text-navy-950 hover:bg-brass-50 hover:ring-1 hover:ring-brass-300';
                sub = html`<span class="text-[9px] font-semibold text-navy-400">${money(rate)}</span>`;
            } else if (status === 'soldout') {
                cls += 'cursor-not-allowed text-navy-300 line-through';
                sub = html`<span class="text-[9px] font-semibold text-red-400 no-underline">Full</span>`;
            } else {
                cls += 'cursor-not-allowed text-navy-200';
            }
            const dot = status === 'limited' && !selected ? trusted('<span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-brass-500"></span>') : '';
            const label = { unavailable: 'not available', soldout: 'sold out', limited: 'few times left', available: 'available', past: 'past' }[status];
            cells.push(html`<button type="button" ${open ? html`data-day="${iso}"` : trusted('disabled')} class="${cls}" aria-label="${formatDate(iso)}, ${label}" ${selected ? trusted('aria-pressed="true"') : ''}>${d}${sub}${dot}</button>`);
        }
        return html`<div>
            <div class="flex items-center justify-between">
                <button type="button" data-cal="-1" ${canPrev ? '' : trusted('disabled')} class="grid h-10 w-10 place-items-center rounded-full ring-1 ring-navy-200 hover:bg-navy-50 disabled:opacity-30" aria-label="Previous month"><i data-lucide="chevron-left" class="h-4 w-4"></i></button>
                <p class="font-display text-xl text-navy-950" aria-live="polite">${m.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}</p>
                <button type="button" data-cal="1" class="grid h-10 w-10 place-items-center rounded-full ring-1 ring-navy-200 hover:bg-navy-50" aria-label="Next month"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
            </div>
            <div class="mt-5 grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase tracking-wider text-navy-400" aria-hidden="true">${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => html`<span>${d}</span>`)}</div>
            <div class="mt-2 grid grid-cols-7 gap-1">${cells}</div>
            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-[11px] font-semibold text-navy-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-navy-950"></span>Selected</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-brass-500"></span>Few left</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-navy-200"></span>Unavailable</span>
                <span class="inline-flex items-center gap-1.5">$ = hourly rate</span>
            </div>
        </div>`;
    }

    function nextAvailable(y, e, n = 4) {
        const out = [];
        let iso = todayInChicago();
        for (let i = 0; i < 90 && out.length < n; i++) {
            if (isOpen(dayStatus(y.slug, iso, e)) && slots(y.slug, iso, e).some((s) => s.available)) out.push(iso);
            iso = addDays(iso, 1);
        }
        return out;
    }

    function stepDate() {
        const y = Y();
        const e = E();
        const durations = durationsFor(y, e);
        const soon = nextAvailable(y, e);
        const daySlots = state.date ? slots(y.slug, state.date, e) : [];
        const rate = state.date && isWeekend(state.date) ? weekendRate(y) : y.hourly_rate_cents;

        const right = html`<div class="space-y-7">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-navy-400">Duration</p>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    ${durations.map((h) => {
                        const selected = state.hours === h;
                        return html`<button type="button" data-hours="${h}" aria-pressed="${selected}" class="rounded-xl px-3 py-3 text-left ring-2 transition ${selected ? 'bg-navy-950 text-white ring-navy-950' : 'bg-white ring-navy-100 hover:ring-navy-300'}">
                            <span class="block text-sm font-extrabold">${h} hours</span>
                            <span class="block text-[11px] ${selected ? 'text-brass-300' : 'text-navy-400'}">${money((rate + y.captain_rate_cents) * h)} w/ captain</span>
                        </button>`;
                    })}
                </div>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-navy-400">Departure time</p>
                ${!state.date
                    ? html`<div class="mt-3 rounded-2xl border-2 border-dashed border-navy-100 p-6 text-center text-sm text-navy-400"><i data-lucide="calendar-days" class="mx-auto mb-2 h-6 w-6"></i>Pick a date to see available times</div>`
                    : html`<p class="mt-1 text-sm font-semibold text-navy-700">${formatDate(state.date, { weekday: 'long', month: 'long', day: 'numeric' })}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            ${daySlots.map((s) => {
                                const selected = state.time === s.time;
                                return html`<button type="button" ${s.available ? html`data-time="${s.time}"` : trusted('disabled')} aria-pressed="${selected}" class="rounded-xl px-3 py-3 text-left ring-2 transition ${selected ? 'bg-brass-400 text-navy-950 ring-brass-400' : s.available ? 'bg-white ring-navy-100 hover:ring-navy-300' : 'cursor-not-allowed bg-navy-50 text-navy-300 ring-transparent'}">
                                    <span class="block text-sm font-extrabold ${s.available ? '' : 'line-through'}">${formatTime(s.time)}</span>
                                    <span class="block text-[11px] ${selected ? 'text-navy-800' : 'text-navy-400'}">${s.available ? `until ${formatTime(addHoursToTime(s.time, state.hours))}` : 'Booked'}</span>
                                </button>`;
                            })}
                        </div>
                        ${isWeekend(state.date) ? html`<p class="mt-3 flex items-center gap-2 text-xs font-semibold text-brass-700"><i data-lucide="info" class="h-4 w-4"></i>Weekend rate: ${money(rate)}/hr</p>` : ''}`}
            </div>
            ${e.allowed_weekdays ? html`<div class="flex gap-3 rounded-2xl bg-brass-50 p-4 text-xs text-navy-700 ring-1 ring-brass-200"><i data-lucide="sparkles" class="h-4 w-4 shrink-0 text-brass-600"></i>${e.name} departs on Wednesday &amp; Saturday nights only.</div>` : ''}
        </div>`;

        const head = soon.length
            ? html`<div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Next available:</span>
                ${soon.map((iso) => html`<button type="button" data-day="${iso}" class="rounded-full px-3 py-1.5 text-xs font-bold ${state.date === iso ? 'bg-navy-950 text-white' : 'bg-navy-50 text-navy-700 hover:bg-navy-100'}">${formatDate(iso, { weekday: 'short', month: 'short', day: 'numeric' })}</button>`)}
            </div>`
            : '';

        return panel(
            'Pick a date & time',
            html`Live availability for <strong class="text-navy-800">${y.title}</strong> · ${e.name}`,
            html`<div class="grid gap-10 xl:grid-cols-[1.15fr_1fr]">${calendar(y, e)}${right}</div>`,
            head,
        );
    }

    /* ---- Step 4: extras ---- */
    function stepExtras() {
        const cards = data.addons.map((a) => {
            const selected = state.addons.includes(a.slug);
            const price = a.unit === 'per_guest' ? a.price_cents * state.guests : a.price_cents;
            return html`<button type="button" data-addon="${a.slug}" aria-pressed="${selected}" class="flex items-start gap-4 rounded-2xl p-4 text-left ring-2 transition ${selected ? 'bg-brass-50 ring-brass-400' : 'bg-white ring-navy-100 hover:ring-navy-300'}">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl ${selected ? 'bg-brass-400 text-navy-950' : 'bg-navy-50 text-navy-600'}"><i data-lucide="${a.icon}" class="h-5 w-5"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold text-navy-950">${a.name}</span>
                    <span class="block text-xs text-navy-500">${a.description}</span>
                    <span class="mt-1.5 block text-sm font-extrabold text-navy-950">${money(price)} <span class="text-[11px] font-semibold text-navy-400">${a.unit === 'per_guest' ? `(${money(a.price_cents)} × ${state.guests})` : ''}</span></span>
                </span>
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md ring-2 ${selected ? 'bg-navy-950 text-white ring-navy-950' : 'ring-navy-200'}">${selected ? trusted('<i data-lucide="check" class="h-4 w-4"></i>') : ''}</span>
            </button>`;
        });
        return panel(
            'Make it yours',
            'Optional extras — add as many as you like, or skip straight to checkout.',
            html`<div class="grid gap-3 md:grid-cols-2">${cards}</div>
            <div class="mt-8 grid gap-5 md:grid-cols-[1fr_2fr]">
                <label class="block"><span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Occasion</span>
                    <select id="occasion" class="mt-2 w-full rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm font-semibold focus:border-navy-950 focus:outline-hidden">
                        <option value="">Select (optional)</option>
                        ${data.occasions.map((o) => html`<option value="${o}" ${state.occasion === o ? trusted('selected') : ''}>${o}</option>`)}
                    </select></label>
                <label class="block"><span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Notes for your captain</span>
                    <textarea id="notes" rows="3" maxlength="1000" placeholder="Dietary needs, surprise plans, accessibility…" class="mt-2 w-full rounded-xl border border-navy-200 px-4 py-3 text-sm focus:border-navy-950 focus:outline-hidden">${state.notes}</textarea></label>
            </div>`,
        );
    }

    /* ---- Summary (desktop sidebar) ---- */
    const currentQuote = () => (Y() ? quote(Y(), E(), data.pricing, { date: state.date, hours: state.hours, guests: state.guests, addons: state.addons.map((s) => addons[s]) }) : null);

    function summary() {
        const y = Y();
        const e = E();
        const q = currentQuote();
        const row = (icon, label, value, step) => html`<div class="flex items-center gap-3 py-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-navy-50 text-navy-500"><i data-lucide="${icon}" class="h-4 w-4"></i></span>
            <div class="min-w-0 flex-1"><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">${label}</p><p class="truncate text-sm font-bold ${value ? 'text-navy-950' : 'text-navy-300'}">${value || 'Not selected'}</p></div>
            ${value && step <= maxStep() && step !== state.step ? html`<button type="button" data-goto="${step}" class="text-xs font-bold text-brass-600 hover:underline">Edit<span class="sr-only"> ${label}</span></button>` : ''}
        </div>`;
        const timeLabel = state.time ? `${formatTime(state.time)} – ${formatTime(addHoursToTime(state.time, state.hours))}` : '';
        const canGo = done[state.step === 4 ? 4 : state.step]();
        return html`<div class="overflow-hidden rounded-[2rem] bg-white shadow-card ring-1 ring-navy-100">
            <div class="relative h-36 overflow-hidden bg-navy-950">
                ${y ? html`<img src="${y.cover}" alt="" class="h-full w-full object-cover opacity-80">` : trusted('<div class="grid h-full place-items-center text-brass-300"><i data-lucide="anchor" class="h-10 w-10"></i></div>')}
                <div class="absolute inset-0 bg-gradient-to-t from-navy-950 to-transparent"></div>
                <div class="absolute bottom-4 left-5 text-white"><p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brass-300">Your charter</p><p class="font-display text-2xl">${y ? y.title : 'Start building'}</p></div>
            </div>
            <div class="divide-y divide-navy-100 px-5">
                ${row('compass', 'Experience', e?.name, 1)}
                ${row('sailboat', 'Yacht · guests', y && `${y.title} · ${state.guests} guests`, 2)}
                ${row('calendar-days', 'Date', state.date && formatDate(state.date), 3)}
                ${row('clock', 'Time · duration', state.time && `${timeLabel} · ${state.hours}h`, 3)}
            </div>
            ${q
                ? html`<div class="border-t border-navy-100 bg-navy-50/60 px-5 py-5">
                    <dl class="space-y-2 text-sm">
                        ${q.lines.map((l) => html`<div class="flex justify-between gap-3"><dt class="text-navy-600">${l.label}</dt><dd class="font-semibold text-navy-900">${money(l.amount)}</dd></div>`)}
                        <div class="flex justify-between gap-3"><dt class="text-navy-600">Taxes (est.)</dt><dd class="font-semibold text-navy-900">${money(q.tax)}</dd></div>
                    </dl>
                    <div class="mt-4 flex items-end justify-between border-t border-navy-200 pt-4">
                        <div><p class="text-sm font-bold text-navy-950">Total</p>${!state.date ? trusted('<p class="text-[11px] text-navy-400">Weekday estimate</p>') : ''}</div>
                        <p class="text-2xl font-extrabold text-navy-950">${money(q.total)}</p>
                    </div>
                    <p class="mt-1 text-right text-xs font-semibold text-brass-700">${money(q.deposit)} due today (${data.pricing.deposit_bp / 100}% deposit)</p>
                </div>`
                : html`<div class="border-t border-navy-100 px-5 py-5 text-sm text-navy-400">Choose a yacht to see your live price.</div>`}
            <div class="p-5 pt-0">
                <button type="button" ${state.step < 4 ? trusted('data-next') : trusted('data-checkout')} ${canGo ? '' : trusted('disabled')} class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-brass-400 px-6 py-4 text-sm font-bold text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300 disabled:cursor-not-allowed disabled:bg-navy-100 disabled:text-navy-400 disabled:shadow-none disabled:hover:translate-y-0">${state.step < 4 ? 'Continue' : 'Continue to checkout'} <i data-lucide="arrow-right" class="h-4 w-4"></i></button>
                <ul class="mt-4 space-y-2 text-xs text-navy-500">
                    <li class="flex items-center gap-2"><i data-lucide="cloud-sun" class="h-4 w-4 text-brass-500"></i>Free weather rescheduling</li>
                    <li class="flex items-center gap-2"><i data-lucide="shield-check" class="h-4 w-4 text-brass-500"></i>Licensed &amp; insured captains</li>
                    <li class="flex items-center gap-2"><i data-lucide="lock" class="h-4 w-4 text-brass-500"></i>No charge until checkout</li>
                </ul>
            </div>
        </div>`;
    }

    /* ---- Mobile bottom bar ---- */
    function mobileBar() {
        const y = Y();
        const q = currentQuote();
        const canGo = done[state.step === 4 ? 4 : state.step]();
        return html`<div class="border-t border-navy-100 bg-white/95 p-4 shadow-[0_-10px_30px_-15px_rgba(7,17,30,.3)] backdrop-blur">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="truncate text-xs text-navy-400">${y ? `${y.title} · ${state.guests} guests${state.hours ? ` · ${state.hours}h` : ''}` : `Step ${state.step} of 4`}</p>
                    <p class="text-xl font-extrabold text-navy-950">${q ? money(q.total) : '—'}</p>
                </div>
                <button type="button" ${state.step < 4 ? trusted('data-next') : trusted('data-checkout')} ${canGo ? '' : trusted('disabled')} class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-brass-400 px-6 py-3 text-sm font-bold text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300 disabled:bg-navy-100 disabled:text-navy-400 disabled:shadow-none">${state.step < 4 ? 'Continue' : 'Checkout'} <i data-lucide="arrow-right" class="h-4 w-4"></i></button>
            </div>
        </div>`;
    }

    /* ---------------- Render ---------------- */
    function selectionQuery() {
        const params = new URLSearchParams();
        for (const key of ['experience', 'yacht', 'guests', 'date', 'time', 'hours']) if (state[key]) params.set(key, String(state[key]));
        if (state.addons.length) params.set('addons', state.addons.join(','));
        if (state.occasion) params.set('occasion', state.occasion);
        return params;
    }

    function render() {
        normalize();
        if (state.step > maxStep()) state.step = maxStep();
        setHtml($('stepper'), stepper());
        const container = $('step-container');
        setHtml(container, [null, stepExperience, stepYacht, stepDate, stepExtras][state.step]());
        if (lastStep !== state.step) {
            container.firstElementChild?.classList.add('step-panel');
            lastStep = state.step;
        }
        setHtml($('summary'), summary());
        setHtml($('mobile-summary'), mobileBar());
        history.replaceState(null, '', `?${selectionQuery()}`);
        renderIcons();
    }

    function checkout() {
        if (!done[4]()) {
            toast('Please complete all steps first', 'error');
            return;
        }
        // Notes are free text: they travel to checkout in this tab only (never in the URL), then are deleted.
        try {
            if (state.notes.trim()) sessionStorage.setItem('cyb_booking_notes', state.notes.slice(0, 1000));
            else sessionStorage.removeItem('cyb_booking_notes');
        } catch {
            /* storage disabled: the guest can type notes again at checkout */
        }
        window.location.href = `${data.checkoutUrl}?${selectionQuery()}`;
    }

    /* ---------------- Events ---------------- */
    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        const q = (selector) => target.closest(selector);
        let el;

        if ((el = q('[data-goto]'))) return goTo(Number(el.dataset.goto));
        if (q('[data-back]')) return goTo(state.step - 1);
        if (q('[data-next]')) return done[state.step]() && goTo(state.step + 1);
        if (q('[data-checkout]')) return checkout();

        if ((el = q('[data-exp]'))) {
            state.experience = el.dataset.exp;
            render();
            setTimeout(() => goTo(maxStep()), 220); // jump to the next incomplete step
            return;
        }
        if (q('a[href]')) return; // detail links inside yacht rows
        if ((el = q('[data-yacht]')) || (el = q('[data-yacht-row]'))) {
            const slug = el.dataset.yacht || el.dataset.yachtRow;
            if (!slug || !yachts[slug]?.booking_enabled) return;
            state.yacht = slug;
            render();
            setTimeout(() => goTo(maxStep()), 220);
            return;
        }
        if ((el = q('[data-guests]'))) {
            state.guests = clamp(state.guests + Number(el.dataset.guests), 1, 80);
            return render();
        }
        if ((el = q('[data-guests-set]'))) {
            state.guests = clamp(Number(el.dataset.guestsSet), 1, 80);
            return render();
        }
        if ((el = q('[data-cal]'))) {
            calendarMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + Number(el.dataset.cal), 1);
            return render();
        }
        if ((el = q('[data-day]'))) {
            state.date = el.dataset.day;
            const d = parseIsoDate(state.date);
            calendarMonth = new Date(d.getFullYear(), d.getMonth(), 1);
            return render();
        }
        if ((el = q('[data-hours]'))) {
            state.hours = Number(el.dataset.hours);
            return render();
        }
        if ((el = q('[data-time]'))) {
            state.time = el.dataset.time;
            return render();
        }
        if ((el = q('[data-addon]'))) {
            const slug = el.dataset.addon;
            if (!addons[slug]) return;
            state.addons = state.addons.includes(slug) ? state.addons.filter((s) => s !== slug) : [...state.addons, slug];
            render();
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target.id === 'guests-input') {
            state.guests = clamp(parseInt(event.target.value, 10) || 1, 1, 80);
            render();
        }
        if (event.target.id === 'occasion') {
            state.occasion = data.occasions.includes(event.target.value) ? event.target.value : '';
            history.replaceState(null, '', `?${selectionQuery()}`);
        }
    });
    document.addEventListener('input', (event) => {
        if (event.target.id === 'notes') state.notes = event.target.value;
    });

    normalize();
    state.step = maxStep();
    render();
}
