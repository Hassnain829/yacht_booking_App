import { html, setHtml } from '../lib/dom.js';
import { formatCents } from '../lib/money.js';
import { http, HttpError } from '../lib/http.js';
import { addMonths, formatDate, formatMonth, monthGrid, monthOf, parseIsoDate } from '../lib/dates.js';
import { renderIcons } from '../lib/icons.js';
import { toast } from '../lib/toast.js';
import { openLightbox } from '../ui/lightbox.js';

/**
 * Yacht detail page = the booking page (PLAN 4.3, D21: Boatsetter's flow, our design).
 *
 * The booking card picks date → duration → start time (+ an optional experience and the guests). The calendar
 * and the start times come from GET /yachts/{slug}/availability, the price from GET /yachts/{slug}/quote: the
 * browser only SHOWS what the server works out (AvailabilityService, QuoteService). "Book now" goes to checkout
 * with the choices. On phones the card is a full-screen sheet opened from the bottom bar.
 *
 * An experience is free and has no length of its own (the boat is booked by the hour); it departs inside its own
 * window on its own days (a sunset cruise in the evening, the fireworks on Wed & Sat nights). Picking one shows
 * only its start times, and when the date or time already chosen doesn't fit it, the card says why and asks again
 * (user decisions 2026-11-25).
 */
export default function yachtPage() {
    initGallery();
    initShareAndSave();

    const form = document.getElementById('booking-card');
    if (form) bookingCard(form);
}

/** Phone sheet classes (below lg the card is hidden until the bottom bar opens it). */
const SHEET = ['max-lg:fixed', 'max-lg:inset-0', 'max-lg:z-50', 'max-lg:overflow-y-auto', 'max-lg:bg-shell', 'max-lg:p-4', 'max-lg:pb-10'];
const WEEKDAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
const DAY_CLASS = {
    available: 'font-bold text-navy-950 hover:bg-navy-100',
    limited: 'font-bold text-navy-950 hover:bg-navy-100',
    soldout: 'cursor-not-allowed text-navy-300 line-through',
    unavailable: 'cursor-not-allowed text-navy-300',
    past: 'cursor-not-allowed text-navy-200',
};
const DAY_LABEL = { available: 'available', limited: 'few start times left', soldout: 'booked', unavailable: 'not available', past: 'past' };

function bookingCard(form) {
    const config = JSON.parse(form.dataset.bookingCard);
    const state = {
        experience: config.initial.experience ?? '',
        date: config.initial.date ?? '',
        time: '',
        hours: config.initial.hours,
        guests: config.initial.guests,
        addons: config.initial.addons ?? [], // a pricing package's add-ons, carried to checkout
        month: config.initial.date ? monthOf(config.initial.date) : config.months[0],
    };
    const $ = (selector) => form.querySelector(selector);
    const rows = { date: $('[data-row="date"]'), hours: $('[data-row="hours"]'), time: $('[data-row="time"]') };
    const panels = { date: $('[data-panel="date"]'), hours: $('[data-panel="hours"]'), time: $('[data-panel="time"]') };
    const error = $('[data-card="error"]');
    const notice = $('[data-card="notice"]');
    const bar = document.getElementById('mobile-book-bar');
    const calendars = new Map();
    let open = null;
    let quoteTimer = null;
    let quoteAbort = null;

    const baseParams = () => ({ hours: state.hours, guests: state.guests, experience: state.experience || undefined });
    const lengths = () => config.lengths; // the boat's, the same with any experience
    const chosenExperience = () => config.experiences[state.experience] ?? null;

    const showError = (message) => {
        error.textContent = message ?? '';
        error.hidden = !message;
    };
    const showNotice = (message) => {
        if (!notice) return;
        notice.textContent = message ?? '';
        notice.hidden = !message;
    };

    /** Why the chosen date or start time doesn't fit the experience (its days, season, departure window), or null. */
    const conflict = (experience) => {
        if (!experience) return null;
        if (state.date && experience.weekdays && !experience.weekdays.includes(parseIsoDate(state.date).getDay())) {
            return { field: 'date', message: `${experience.name} runs on ${experience.days} only, not on ${formatDate(state.date, { weekday: 'long' })}s. Pick another date.` };
        }
        if (state.date && experience.season && (state.date < experience.season[0] || state.date > experience.season[1])) {
            const day = (iso) => formatDate(iso, { month: 'short', day: 'numeric', year: 'numeric' });
            return { field: 'date', message: `${experience.name} runs from ${day(experience.season[0])} to ${day(experience.season[1])}. Pick another date.` };
        }
        if (state.time && !experience.times.includes(state.time)) {
            return { field: 'time', message: `${experience.name} isn't available at ${formatTimeLabel(state.time)}: it departs ${experience.window}. Pick a start time in that window.` };
        }
        return null;
    };
    const failure = (err) => (err instanceof HttpError ? String(Object.values(err.errors)[0]?.[0] ?? err.message) : 'Something went wrong. Please try again.');

    /* ---------------------------------------------------------------- rows + panels */

    const setOpen = (name) => {
        open = open === name ? null : name;
        paintPanels();
    };
    const showPanel = (name) => {
        open = name;
        paintPanels();
    };
    const paintPanels = () => {
        for (const [key, panel] of Object.entries(panels)) {
            panel.hidden = key !== open;
            rows[key].setAttribute('aria-expanded', String(key === open));
        }
        if (open === 'date') renderCalendar();
        if (open === 'hours') renderHours();
        if (open === 'time') renderTimes();
    };
    for (const [name, row] of Object.entries(rows)) row.addEventListener('click', () => setOpen(name));

    const paintRows = () => {
        $('[data-value="date"]').textContent = state.date ? formatDate(state.date, { weekday: 'short', month: 'short', day: 'numeric' }) : 'Pick a date';
        $('[data-value="hours"]').textContent = `${state.hours} hours`;
        $('[data-value="time"]').textContent = state.time ? formatTimeLabel(state.time) : state.date ? 'Pick a time' : 'Pick a date first';
        form.elements.date.value = state.date;
        form.elements.time.value = state.time;
        form.elements.hours.value = String(state.hours);
        form.elements.addons.value = state.addons.join(',');
        const note = $('[data-card="experience-note"]');
        if (note) {
            const experience = chosenExperience();
            note.textContent = experience
                ? `${experience.days} · departs ${experience.window} · ${experience.extras}`
                : 'Optional and free. Each experience departs in its own time window.';
        }
    };

    /* ---------------------------------------------------------------- calendar */

    const calendarKey = () => `${state.month}|${state.hours}|${state.experience}`;

    async function renderCalendar() {
        const panel = panels.date;
        const key = calendarKey();
        const first = state.month <= config.months[0];
        const last = state.month >= config.months[1];
        const days = calendars.get(key);
        const head = html`<div class="flex items-center justify-between">
                <button type="button" data-month-step="-1" class="grid h-9 w-9 place-items-center rounded-full hover:bg-navy-50 disabled:pointer-events-none disabled:opacity-30" aria-label="Previous month" ${first ? html`disabled` : ''}><i data-lucide="chevron-left" class="h-4 w-4"></i></button>
                <p class="text-sm font-bold text-navy-950">${formatMonth(state.month)}</p>
                <button type="button" data-month-step="1" class="grid h-9 w-9 place-items-center rounded-full hover:bg-navy-50 disabled:pointer-events-none disabled:opacity-30" aria-label="Next month" ${last ? html`disabled` : ''}><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
            </div>
            <div class="mt-2 grid grid-cols-7 text-center text-[10px] font-bold uppercase tracking-wider text-navy-400" aria-hidden="true">${WEEKDAYS.map((d) => html`<span>${d}</span>`)}</div>`;

        if (!days) {
            setHtml(panel, html`${head}<p class="py-10 text-center text-xs text-navy-400">Loading ${formatMonth(state.month)}…</p>`);
            renderIcons(panel);
            try {
                const { data } = await http.get(config.urls.availability, { params: { month: state.month, ...baseParams() } });
                calendars.set(key, data.days);
                if (open === 'date' && calendarKey() === key) renderCalendar();
            } catch (err) {
                setHtml(panel, html`${head}<p class="py-8 text-center text-xs font-semibold text-red-700">${failure(err)}</p>`);
                renderIcons(panel);
            }
            return;
        }

        const cell = (iso) => {
            if (!iso) return html`<span></span>`;
            const status = days[iso] ?? 'unavailable';
            const bookable = status === 'available' || status === 'limited';
            const chosen = iso === state.date;
            const label = `${formatDate(iso, { weekday: 'long', month: 'long', day: 'numeric' })}, ${DAY_LABEL[status] ?? 'not available'}`;
            return html`<button type="button" data-date="${iso}" aria-label="${label}" aria-pressed="${String(chosen)}" ${bookable ? '' : html`disabled`}
                class="relative grid aspect-square place-items-center rounded-lg text-sm transition ${chosen ? 'bg-navy-950 font-bold text-white' : DAY_CLASS[status] ?? DAY_CLASS.unavailable}">${Number(iso.slice(8))}${status === 'limited' && !chosen ? html`<span class="absolute bottom-1 h-1 w-1 rounded-full bg-amber-500" aria-hidden="true"></span>` : ''}</button>`;
        };
        setHtml(
            panel,
            html`${head}<div class="mt-1 grid grid-cols-7 gap-1">${monthGrid(state.month).flat().map(cell)}</div>
                <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-navy-500"><span class="font-bold text-navy-950">Bold: free</span><span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Few times left</span><span class="text-navy-300 line-through">Booked</span></p>`,
        );
        renderIcons(panel);
    }

    panels.date.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const step = target?.closest('[data-month-step]');
        if (step) {
            state.month = addMonths(state.month, Number(step.dataset.monthStep));
            renderCalendar();
            return;
        }
        const day = target?.closest('[data-date]');
        if (!day || day.disabled) return;
        state.date = day.dataset.date;
        state.time = '';
        showError(null);
        showNotice(null);
        paintRows();
        refreshQuote();
        setOpen('time'); // like Boatsetter: the start times open next
    });

    /* ---------------------------------------------------------------- duration */

    function renderHours() {
        setHtml(
            panels.hours,
            html`<p class="text-xs font-semibold text-navy-500">Trip length</p><div class="mt-2 flex flex-wrap gap-2">${lengths().map(
                (h) => html`<button type="button" data-hours="${h}" aria-pressed="${String(h === state.hours)}" class="rounded-full px-4 py-2 text-sm font-bold transition ${h === state.hours ? 'bg-navy-950 text-white' : 'border border-navy-200 text-navy-700 hover:border-navy-950'}">${h} hours</button>`,
            )}</div>`,
        );
    }

    panels.hours.addEventListener('click', (event) => {
        const chip = event.target instanceof Element ? event.target.closest('[data-hours]') : null;
        if (!chip) return;
        state.hours = Number(chip.dataset.hours);
        state.time = ''; // a longer trip can clash with a booking: pick the time again
        paintRows();
        refreshQuote();
        setOpen(state.date ? 'time' : 'hours');
    });

    /* ---------------------------------------------------------------- start times */

    async function renderTimes() {
        const panel = panels.time;
        if (!state.date) {
            setHtml(panel, html`<p class="py-2 text-center text-sm text-navy-500">Pick a date to see the start times.</p>`);
            return;
        }
        const date = state.date;
        const experience = chosenExperience();
        const windowNote = experience
            ? html`<p class="mb-3 rounded-lg bg-brass-50 px-3 py-2 text-xs font-semibold text-navy-800 ring-1 ring-brass-200">${experience.name} departs ${experience.window}: only those start times are shown. For another time, choose “Just the boat”.</p>`
            : '';
        setHtml(panel, html`<p class="py-4 text-center text-xs text-navy-400">Loading start times…</p>`);
        try {
            const { data } = await http.get(config.urls.availability, { params: { date, ...baseParams() } });
            if (open !== 'time' || state.date !== date || chosenExperience() !== experience) return;
            const free = data.slots.filter((slot) => slot.available).length;
            const day = formatDate(date, { weekday: 'long', month: 'short', day: 'numeric' });
            setHtml(
                panel,
                data.slots.length === 0
                    ? html`${windowNote}<p class="py-2 text-center text-sm text-navy-500">${experience ? `No ${experience.name} departures on ${day}.` : `No departures on ${day}.`} Pick another day.</p>`
                    : html`${windowNote}<p class="text-xs font-semibold text-navy-500">${day} · ${free} of ${data.slots.length} free</p>
                        <div class="mt-2 grid grid-cols-3 gap-2">${data.slots.map(
                            (slot) => html`<button type="button" data-time="${slot.time}" ${slot.available ? '' : html`disabled`} aria-pressed="${String(slot.time === state.time)}"
                                class="rounded-xl px-2 py-2.5 text-center text-sm font-bold transition ${slot.time === state.time ? 'bg-navy-950 text-white' : slot.available ? 'border border-navy-200 text-navy-900 hover:border-navy-950' : 'cursor-not-allowed bg-navy-50 text-navy-300'}">${slot.label}${slot.available ? '' : html`<span class="block text-[10px] font-semibold">${slot.reason}</span>`}</button>`,
                        )}</div>`,
            );
        } catch (err) {
            setHtml(panel, html`<p class="py-2 text-center text-xs font-semibold text-red-700">${failure(err)}</p>`);
        }
    }

    panels.time.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-time]') : null;
        if (!button || button.disabled) return;
        state.time = button.dataset.time;
        showError(null);
        showNotice(null);
        paintRows();
        refreshQuote();
        setOpen(null);
    });

    /* ---------------------------------------------------------------- experience + guests */

    // A date or time that the new experience can't take is cleared, and the card says why (sunset ≠ fireworks).
    const checkExperience = () => {
        const problem = conflict(chosenExperience());
        showNotice(problem?.message ?? null);
        if (problem?.field === 'date') state.date = '';
        if (problem) state.time = '';
        return problem;
    };

    form.elements.experience?.addEventListener('change', () => {
        state.experience = form.elements.experience.value;
        const problem = checkExperience();
        paintRows();
        refreshQuote();
        if (problem) showPanel(state.date ? 'time' : 'date');
        else if (open) paintPanels();
    });

    const guestsInput = form.elements.guests;
    const setGuests = (value) => {
        const guests = Math.max(1, Math.min(config.maxGuests, Math.round(Number(value) || 1)));
        guestsInput.value = String(guests);
        if (guests !== state.guests) {
            state.guests = guests;
            refreshQuote();
        }
    };
    form.addEventListener('click', (event) => {
        const step = event.target instanceof Element ? event.target.closest('[data-guests-step]') : null;
        if (step) setGuests(state.guests + Number(step.dataset.guestsStep));
    });
    guestsInput.addEventListener('change', () => setGuests(guestsInput.value));

    /* ---------------------------------------------------------------- price */

    function refreshQuote() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(loadQuote, 200);
    }

    async function loadQuote() {
        quoteAbort?.abort();
        quoteAbort = new AbortController();
        const params = { ...baseParams(), date: state.date || undefined, time: state.date && state.time ? state.time : undefined, addons: state.addons };
        try {
            const { data } = await http.get(config.urls.quote, { params, signal: quoteAbort.signal });
            paintQuote(data);
            showError(null);
        } catch (err) {
            if (quoteAbort.signal.aborted) return;
            showError(failure(err));
        }
    }

    function paintQuote(quote) {
        const lines = [...quote.lines, ...quote.adjustments];
        setHtml(
            $('[data-card="lines"]'),
            html`${lines.map(
                (line) => html`<div class="flex justify-between gap-4"><dt class="min-w-0 text-navy-600">${line.label}<span class="block text-xs text-navy-400">${line.description}</span></dt><dd class="shrink-0 font-semibold text-navy-900">${formatCents(line.amount_cents)}</dd></div>`,
            )}`,
        );
        $('[data-card="total"]').textContent = formatCents(quote.total_cents);
        $('[data-card="note"]').textContent = quote.estimate ? 'Weekday price. Pick a date for yours.' : `Paid in full at checkout${quote.weekend ? ' · weekend rate' : ''}.`;
        const cancellation = $('[data-card="cancellation-text"]');
        cancellation.dataset.default ??= cancellation.textContent;
        if (quote.free_cancellation_label) cancellation.textContent = `Free cancellation until ${quote.free_cancellation_label}`;
        else if (state.date && state.time) cancellation.textContent = `Too close for a free cancellation · ${quote.policy?.name ?? 'cancellation'} policy`;
        else cancellation.textContent = cancellation.dataset.default;
        if (bar) {
            bar.querySelector('[data-bar="summary"]').textContent = `${quote.hours} hrs · ${quote.estimate ? 'weekday price' : state.time ? formatTimeLabel(state.time) : 'total'}`;
            bar.querySelector('[data-bar="total"]').textContent = formatCents(quote.total_cents, { cents: 'never' });
        }
    }

    /* ---------------------------------------------------------------- book */

    form.addEventListener('submit', (event) => {
        if (!state.date || !state.time) {
            event.preventDefault();
            showError(state.date ? 'Pick a start time.' : 'Pick a date.');
            if (open !== (state.date ? 'time' : 'date')) setOpen(state.date ? 'time' : 'date');
            return;
        }
        paintRows();
        const optional = [form.elements.experience, form.elements.addons].filter((field) => field && !field.value);
        optional.forEach((field) => (field.disabled = true)); // a clean URL
        window.addEventListener('pageshow', () => optional.forEach((field) => (field.disabled = false)), { once: true });
    });

    /* ---------------------------------------------------------------- phone sheet */

    const sheet = document.querySelector('[data-booking-sheet]');
    const desktop = window.matchMedia('(min-width: 1024px)');
    const openSheet = () => {
        if (desktop.matches) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        sheet.classList.remove('max-lg:hidden');
        sheet.classList.add(...SHEET);
        document.documentElement.classList.add('max-lg:overflow-hidden');
        sheet.querySelector('[data-sheet-close]')?.focus();
    };
    const closeSheet = () => {
        sheet.classList.remove(...SHEET);
        sheet.classList.add('max-lg:hidden');
        document.documentElement.classList.remove('max-lg:overflow-hidden');
    };
    document.querySelectorAll('[data-sheet-open]').forEach((button) =>
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openSheet();
        }),
    );
    sheet.querySelector('[data-sheet-close]')?.addEventListener('click', closeSheet);
    document.addEventListener('keydown', (event) => event.key === 'Escape' && !desktop.matches && closeSheet());
    desktop.addEventListener('change', () => desktop.matches && closeSheet());

    // "Experiences on this yacht" cards pick the experience in the card instead of reloading the page.
    document.querySelectorAll('[data-pick-experience]').forEach((link) =>
        link.addEventListener('click', (event) => {
            if (!form.elements.experience) return;
            event.preventDefault();
            form.elements.experience.value = link.dataset.pickExperience;
            form.elements.experience.dispatchEvent(new Event('change'));
            openSheet();
        }),
    );

    const startProblem = checkExperience(); // e.g. ?experience=fireworks&date=<a Tuesday>
    paintRows();
    if (state.date || startProblem) loadQuote();
    if (window.location.hash === '#book' && !desktop.matches) openSheet();
}

/** '17:30' → '5:30 PM' (the server sends labels too; this is for the chosen time). */
function formatTimeLabel(time) {
    const [h, m] = time.split(':').map(Number);
    return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
}

function initGallery() {
    document.querySelector('[data-open-gallery]')?.addEventListener('click', (event) => {
        const gallery = event.currentTarget.closest('[data-lightbox-gallery]');
        const photos = Array.from(gallery.querySelectorAll('[data-lightbox]')).map((el) => el.dataset.lightbox);
        openLightbox(photos, 0);
    });
}

/** Share uses the native share sheet or copies the link. Save keeps favourites in this browser only. */
function initShareAndSave() {
    document.getElementById('share-btn')?.addEventListener('click', async () => {
        try {
            if (navigator.share) await navigator.share({ title: document.title, url: window.location.href });
            else {
                await navigator.clipboard.writeText(window.location.href);
                toast('Link copied to clipboard');
            }
        } catch {
            /* the visitor closed the share sheet */
        }
    });

    const save = document.getElementById('save-btn');
    if (!save) return;
    const KEY = 'cyb_saved_yachts';
    const read = () => {
        try {
            const list = JSON.parse(localStorage.getItem(KEY) ?? '[]');
            return Array.isArray(list) ? list.filter((s) => typeof s === 'string' && /^[a-z0-9-]{1,64}$/.test(s)) : [];
        } catch {
            return [];
        }
    };
    const paint = (on) => {
        save.classList.toggle('bg-white', on);
        save.classList.toggle('text-navy-950', on);
        save.setAttribute('aria-pressed', String(on));
    };
    paint(read().includes(save.dataset.slug));
    save.addEventListener('click', () => {
        const list = read();
        const on = !list.includes(save.dataset.slug);
        try {
            localStorage.setItem(KEY, JSON.stringify(on ? [...list, save.dataset.slug] : list.filter((s) => s !== save.dataset.slug)));
        } catch {
            /* storage disabled */
        }
        paint(on);
        toast(on ? `${save.dataset.name} saved to your favorites` : 'Removed from favorites');
    });
}
