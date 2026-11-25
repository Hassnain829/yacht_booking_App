/**
 * "List your boat" — owner onboarding wizard (port of the mockup's list-boat.js + the new Pricing step).
 *
 * Sections: Boat details → Availability → Operators and pricing (+ insurance) → Review and submit.
 * Rules kept from the mockup (PLAN §7.7): 4 photos minimum, > 32 ft can't be renter-operated,
 * > 33 ft must be captained, commercial insurance has its own step.
 *
 * PHASE 1 DEMO: new drafts are saved in this browser (lib/listing-store.js). Phase 3: one JSON PATCH
 * per step to the server (ListingRequest validates every value against config/listing.php),
 * photos uploaded through MediaService. Everything here is rendered with the escaping html`` template.
 */
import { html, setHtml, trusted, safeUrl, safeImageSrc } from '../lib/dom.js';
import { renderIcons } from '../lib/icons.js';
import { toast } from '../lib/toast.js';
import { formatCents, formatBp } from '../lib/money.js';
import { formatDate, formatTime } from '../lib/dates.js';
import { listingStore, listingTitle } from '../lib/listing-store.js';
import { dollarsToCents, charterCents, ownerNetCents, weekendRateCents } from '../lib/owner-estimate.js';

const DAYS = [['mon', 'Monday'], ['tue', 'Tuesday'], ['wed', 'Wednesday'], ['thu', 'Thursday'], ['fri', 'Friday'], ['sat', 'Saturday'], ['sun', 'Sunday']];
const TIMES = Array.from({ length: 35 }, (_, i) => {
    const m = 5 * 60 + i * 30;
    return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}); // 05:00 → 22:00
const YEARS = Array.from({ length: 77 }, (_, i) => String(new Date().getFullYear() + 1 - i));
const SECTIONS = ['Boat details', 'Availability', 'Operators and pricing', 'Review and submit'];
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const MAX_PHOTOS = 20;
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

const WHOLE = { cents: 'never' };
const isObj = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);

export default function listBoat() {
    const root = document.getElementById('wizard');
    if (!root) return;

    const data = JSON.parse(root.dataset.listing);
    const O = data.options;
    const R = O.rules_engine;
    const main = document.getElementById('main');
    const $ = (id) => document.getElementById(id);

    /* ------------------------------------------------------------------ */
    /* State                                                               */
    /* ------------------------------------------------------------------ */
    const blank = () => ({
        id: `BL-${Date.now().toString(36).toUpperCase()}`,
        status: 'draft',
        step: 'intro',
        created_at: new Date().toISOString(),
        updated_at: '',
        submitted_at: '',
        original_status: '',
        insurance_type: '',
        location: { type: '', country: 'United States', street: '', city: 'Chicago', state: 'IL', zip: '' },
        title: '',
        description: '',
        cancellation: 'flexible',
        rules: [],
        features: [],
        extras: [],
        navigation: [],
        photos: [],
        photos_skipped: false,
        specs: { registration: '', year: '', make: '', model: '', type: '', length: '', category: '', engines: '', horsepower: '', engine_year: '', engine_type: '', staterooms: '', sleeps: '', beds: '', passengers: '' },
        availability: { mode: 'default', days: Object.fromEntries(DAYS.map(([k]) => [k, { on: true, start: '08:00', end: '18:00' }])) },
        advance_notice: '1h',
        multi_booking: { allow: '', buffer: '1h' },
        operators: { renter: false, captain: true, captain_network: 'yes', fishing_charter: 'no' },
        durations: [2, 3, 4, 6, 8],
        fuel: 'renter',
        pricing: { hourly: '', min_hours: 2, weekend_markup_bp: O.pricing.default_weekend_markup_bp, cleaning_fee: '', fixed: {} },
        insurance_owner: { years: '', company: '', claims: 'no', criminal: 'no', over_capacity: 'no', bankruptcy: 'no', business: 'no', other_policy: 'no' },
        insurance_boat: { value: '', customized: 'no', underlying: 'no', attachments: 'no', seaworthy: 'yes', racing: 'no', special: 'no', captained: 'yes' },
        commercial: { carrier: '', policy: '', expires: '', liability: '', coi: '' },
        owner: { first_name: '', last_name: '', email: '', phone: '', ...data.owner },
    });

    /** Copy only known keys with the expected type (saved data may be old or tampered with). */
    function mergeShape(base, saved) {
        if (!isObj(saved)) return base;
        const out = { ...base };
        for (const key of Object.keys(base)) {
            if (!(key in saved)) continue;
            const b = base[key];
            const v = saved[key];
            if (key === 'fixed') {
                out.fixed = isObj(v) ? Object.fromEntries(Object.entries(v).filter(([h, p]) => /^\d{1,2}$/.test(h) && typeof p === 'string')) : {};
            } else if (isObj(b)) {
                out[key] = mergeShape(b, v);
            } else if (Array.isArray(b)) {
                out[key] = Array.isArray(v) ? v.filter((x) => typeof x === 'string' || Number.isInteger(x)) : b;
            } else if (typeof v === typeof b) {
                out[key] = v;
            }
        }
        return out;
    }

    let s;
    let restored = false;
    const draftId = new URLSearchParams(window.location.search).get('draft') ?? '';
    if (data.listing) {
        const local = listingStore.get(data.listing.id);
        s = mergeShape(mergeShape(blank(), data.listing), local ?? {});
        s.original_status = data.listing.status;
        restored = Boolean(local);
    } else if (/^BL-[A-Z0-9]{1,20}$/.test(draftId) && listingStore.get(draftId)) {
        s = mergeShape(blank(), listingStore.get(draftId));
        restored = true;
    } else if (!data.isNew) {
        const draft = listingStore.all().find((l) => l.status === 'draft' && String(l.id).startsWith('BL-'));
        if (draft) {
            s = mergeShape(blank(), draft);
            restored = s.step !== 'intro';
        }
    }
    if (!s) s = blank();
    if (data.step) s.step = data.step;
    if (s.step === 'done') s.step = 'review';
    let returnToReview = false;
    let lastStep = null;

    const getP = (path) => path.split('.').reduce((o, k) => (o == null ? o : o[k]), s);
    const setP = (path, value) => {
        const keys = path.split('.');
        const last = keys.pop();
        const target = keys.reduce((o, k) => (o == null ? o : o[k]), s);
        if (isObj(target)) target[last] = value;
    };
    const len = () => Number(s.specs.length) || 0;
    const rec = () => s.insurance_type !== 'commercial';
    const sortedDurations = () => [...s.durations].sort((a, b) => a - b);

    /* ------------------------------------------------------------------ */
    /* Pricing helpers (estimate only — the server calculates the real amounts) */
    /* ------------------------------------------------------------------ */
    const pricing = () => {
        const p = s.pricing;
        const fixedCents = {};
        for (const [h, v] of Object.entries(p.fixed)) {
            const c = dollarsToCents(v);
            if (c) fixedCents[h] = c;
        }
        return {
            hourlyCents: dollarsToCents(p.hourly),
            markupBp: Number(p.weekend_markup_bp) || 0,
            cleaningCents: p.cleaning_fee === '' ? 0 : dollarsToCents(p.cleaning_fee),
            fixedCents,
        };
    };
    const pricingErrors = () => {
        const p = s.pricing;
        const P = O.pricing;
        const errors = {};
        const hourly = dollarsToCents(p.hourly);
        if (p.hourly !== '' && (hourly === null || hourly < P.hourly_min_cents || hourly > P.hourly_max_cents)) {
            errors.hourly = `Enter an hourly rate between ${formatCents(P.hourly_min_cents, WHOLE)} and ${formatCents(P.hourly_max_cents, WHOLE)}.`;
        }
        const cleaning = dollarsToCents(p.cleaning_fee);
        if (p.cleaning_fee !== '' && (cleaning === null || cleaning > P.cleaning_fee_max_cents)) {
            errors.cleaning_fee = `Enter an amount up to ${formatCents(P.cleaning_fee_max_cents, WHOLE)}.`;
        }
        for (const [h, v] of Object.entries(p.fixed)) {
            const c = dollarsToCents(v);
            if (v !== '' && (c === null || c < P.hourly_min_cents || c > P.fixed_price_max_cents)) {
                errors[`fixed.${h}`] = `Enter a price between ${formatCents(P.hourly_min_cents, WHOLE)} and ${formatCents(P.fixed_price_max_cents, WHOLE)}.`;
            }
        }
        return errors;
    };

    /* ------------------------------------------------------------------ */
    /* UI building blocks (same look as the rest of the site)              */
    /* ------------------------------------------------------------------ */
    const inputCls = 'mt-2 w-full rounded-xl border border-navy-200 bg-white px-4 py-3.5 text-sm text-navy-950 placeholder:text-navy-300 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100 disabled:bg-navy-50';
    const badge = (t) => html`<span class="rounded-md bg-brass-400 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-navy-950">${t}</span>`;
    const radioDot = (on) => html`<span class="grid h-5 w-5 shrink-0 place-items-center rounded-full ring-2 ${on ? 'ring-brass-400' : 'ring-navy-300'}">${on ? trusted('<span class="h-2.5 w-2.5 rounded-full bg-brass-400"></span>') : ''}</span>`;
    const checkBox = (on) => html`<span class="grid h-5 w-5 shrink-0 place-items-center rounded-md ${on ? 'bg-brass-400 text-navy-950' : 'bg-white ring-2 ring-navy-200'}">${on ? trusted('<i data-lucide="check" class="h-3.5 w-3.5"></i>') : ''}</span>`;
    const h3 = (t, extra = '') => html`<h3 class="text-lg font-extrabold text-navy-950 ${extra}">${t}</h3>`;

    function field({ label, bind, type = 'text', placeholder = '', options, required, hint, attrs = '', span = '', rerender, error }) {
        const v = getP(bind) ?? '';
        const common = html`data-bind="${bind}" ${rerender ? trusted('data-rerender') : ''} ${trusted(attrs)} ${error ? trusted('aria-invalid="true"') : ''}`;
        const cls = `${inputCls} ${error ? 'border-red-400' : ''}`;
        let control;
        if (options) {
            control = html`<select ${common} class="${cls}"><option value="">Select…</option>${options.map((o) => {
                const [val, lbl] = Array.isArray(o) ? o : [o, o];
                return html`<option value="${val}" ${String(v) === String(val) ? trusted('selected') : ''}>${lbl}</option>`;
            })}</select>`;
        } else if (type === 'textarea') {
            control = html`<textarea ${common} rows="9" placeholder="${placeholder}" class="${cls}">${v}</textarea>`;
        } else {
            control = html`<input ${common} type="${type}" value="${v}" placeholder="${placeholder}" class="${cls}">`;
        }
        return html`<label class="block ${span}">
            <span class="text-sm font-bold text-navy-800">${label}${required ? trusted(' <span class="text-brass-600">*</span>') : ''}</span>
            ${control}
            ${error ? html`<span class="mt-1.5 block text-xs font-semibold text-red-600" data-error-for="${bind}">${error}</span>` : hint ? html`<span class="mt-1.5 block text-xs text-navy-400">${hint}</span>` : ''}
        </label>`;
    }

    function radioCard({ group, value, title, sub, tag, body = '', selected }) {
        return html`
        <div class="overflow-hidden rounded-2xl ring-2 transition ${selected ? 'shadow-card ring-navy-950' : 'ring-navy-100 hover:ring-navy-300'}">
            <button type="button" data-radio="${group}" data-value="${value}" aria-pressed="${selected}" class="flex w-full items-center gap-4 p-5 text-left md:p-6 ${selected ? 'bg-navy-950 text-white' : 'bg-white text-navy-950'}">
                ${radioDot(selected)}
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2"><span class="text-base font-bold md:text-lg">${title}</span>${tag ? badge(tag) : ''}</span>
                    ${sub ? html`<span class="mt-0.5 block text-sm ${selected ? 'text-white/70' : 'text-navy-500'}">${sub}</span>` : ''}
                </span>
            </button>
            ${selected && body ? html`<div class="border-t border-navy-100 bg-white p-5 text-sm leading-relaxed text-navy-600 md:p-6">${body}</div>` : ''}
        </div>`;
    }

    /** small radio tiles in a grid: items = [[value, label], …] */
    function radioTiles(group, items, cols = 'sm:grid-cols-3') {
        const cur = String(getP(group));
        return html`<div class="grid grid-cols-2 gap-3 ${cols}">${items.map(([val, lbl]) => {
            const on = cur === String(val);
            return html`<button type="button" data-radio="${group}" data-value="${val}" aria-pressed="${on}" class="flex items-center gap-3 rounded-2xl px-4 py-4 text-left text-sm font-bold ring-2 transition ${on ? 'bg-navy-950 text-white ring-navy-950' : 'bg-white text-navy-800 ring-navy-100 hover:ring-navy-300'}">${radioDot(on)}${lbl}</button>`;
        })}</div>`;
    }

    /** checkbox tiles: items = strings or [value, label] */
    function checkTiles(path, items, cols = 'sm:grid-cols-2 xl:grid-cols-3') {
        const arr = getP(path);
        return html`<div class="grid gap-3 ${cols}">${items.map((it) => {
            const [val, lbl] = Array.isArray(it) ? it : [it, it];
            const on = arr.includes(val);
            return html`<button type="button" data-check="${path}" data-value="${val}" aria-pressed="${on}" class="flex items-center gap-3 rounded-2xl px-4 py-4 text-left text-sm font-semibold ring-2 transition ${on ? 'bg-navy-950 text-white ring-navy-950' : 'bg-white text-navy-800 ring-navy-100 hover:ring-navy-300'}">${checkBox(on)}${lbl}</button>`;
        })}</div>`;
    }

    function yesNo(path, question, opts = {}) {
        const v = getP(path);
        const btn = (val, label) => {
            const on = v === val;
            const dis = opts.lock && opts.lock !== val;
            return html`<button type="button" data-radio="${path}" data-value="${val}" aria-pressed="${on}" ${dis ? trusted('disabled') : ''} class="flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-bold ring-2 transition disabled:cursor-not-allowed disabled:opacity-40 ${on ? 'bg-navy-950 text-white ring-navy-950' : 'bg-white text-navy-800 ring-navy-100 hover:ring-navy-300'}">${radioDot(on)}${label}</button>`;
        };
        return html`
            <div class="border-b border-navy-100 py-6 last:border-0">
                <p class="text-sm font-semibold leading-relaxed text-navy-800">${question}</p>
                ${opts.note ? html`<p class="mt-1 text-xs text-brass-700">${opts.note}</p>` : ''}
                <div class="mt-3 grid grid-cols-2 gap-3">${btn('no', 'No')}${btn('yes', 'Yes')}</div>
            </div>`;
    }

    const tipsCard = (title, inner) => html`
        <div class="mt-10 hidden rounded-3xl border border-white/10 bg-white/[0.06] p-6 backdrop-blur md:block">
            <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                <i data-lucide="lightbulb" class="h-7 w-7 shrink-0 text-brass-300"></i>
                <p class="text-sm font-semibold text-white">${title}</p>
            </div>
            <div class="pt-4 text-sm leading-relaxed text-white/70">${inner}</div>
        </div>`;
    const bullets = (items, cls = '') => html`<ul class="list-disc space-y-2 pl-5 ${cls}">${items.map((i) => html`<li>${i}</li>`)}</ul>`;

    /* ------------------------------------------------------------------ */
    /* Pricing step (new — D7)                                             */
    /* ------------------------------------------------------------------ */
    function estimateHTML() {
        const p = pricing();
        const commission = formatBp(data.commissionBp);
        if (!p.hourlyCents || pricingErrors().hourly) {
            return html`<p class="mt-3 text-sm text-white/70">Enter your hourly rate to see what you would earn per trip.</p>`;
        }
        const rows = [];
        for (const hours of sortedDurations().filter((h) => h >= Number(s.pricing.min_hours))) {
            const fixed = Boolean(p.fixedCents[String(hours)]);
            const variants = fixed || !p.markupBp ? [[false, fixed ? 'Fixed price' : 'Any day']] : [[false, 'Mon–Thu'], [true, 'Fri–Sun']];
            for (const [weekend, when] of variants) {
                const charter = charterCents(p, hours, weekend);
                rows.push(html`<tr>
                    <td class="py-3 pr-3"><span class="font-bold text-white">${hours} hours</span> <span class="text-white/50">· ${when}</span></td>
                    <td class="py-3 pr-3 text-right text-white/80">${formatCents(charter + p.cleaningCents, WHOLE)}</td>
                    <td class="py-3 text-right font-extrabold text-brass-300">${formatCents(ownerNetCents(charter, p.cleaningCents, data.commissionBp), WHOLE)}</td>
                </tr>`);
            }
        }
        return html`
            <div class="no-scrollbar relative mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-[11px] uppercase tracking-wider text-white/50"><tr><th scope="col" class="pb-2 font-bold">Trip</th><th scope="col" class="pb-2 text-right font-bold">Your price</th><th scope="col" class="pb-2 text-right font-bold">You earn</th></tr></thead>
                    <tbody class="divide-y divide-white/10">${rows}</tbody>
                </table>
            </div>
            <p class="mt-4 text-xs leading-relaxed text-white/60">Your price includes your fuel &amp; cleaning fee${p.cleaningCents ? html` (${formatCents(p.cleaningCents, WHOLE)})` : ''}, which is paid to you in full. Our commission is ${commission} of the charter price. The licensed captain fee, taxes and crew gratuity are added to the guest's total on top of your price.</p>`;
    }

    function pricingBody() {
        const errors = pricingErrors();
        const p = pricing();
        const durations = sortedDurations();
        return html`
            ${h3('Your rates')}
            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                ${field({ label: 'Hourly rate (USD)', bind: 'pricing.hourly', type: 'text', placeholder: 'e.g. 380', required: true, span: 'sm:col-span-2', attrs: 'inputmode="decimal" maxlength="10" autocomplete="off"', hint: `Guests pay this per hour, Monday to Thursday. Between ${formatCents(O.pricing.hourly_min_cents, WHOLE)} and ${formatCents(O.pricing.hourly_max_cents, WHOLE)}.`, error: errors.hourly })}
                ${field({ label: 'Minimum hours', bind: 'pricing.min_hours', options: durations.map((h) => [h, `${h} hours`]), required: true, rerender: true, hint: 'The shortest trip guests can book.' })}
                ${field({ label: 'Fuel & cleaning fee (USD)', bind: 'pricing.cleaning_fee', type: 'text', placeholder: '0', attrs: 'inputmode="decimal" maxlength="10" autocomplete="off"', hint: 'Charged once per trip. Paid to you in full.', error: errors.cleaning_fee })}
            </div>
            ${h3('Weekend markup', 'mt-10')}
            <p class="mt-1 text-sm text-navy-500">Applies to trips on Friday, Saturday and Sunday.</p>
            <div class="mt-4">${radioTiles('pricing.weekend_markup_bp', O.pricing.weekend_markups_bp.map((bp) => [bp, bp ? `+${formatBp(bp)}` : 'None']), 'sm:grid-cols-5')}</div>
            ${h3('Fixed price per duration', 'mt-10')}
            <p class="mt-1 text-sm text-navy-500">Optional. Leave empty to charge the hourly rate × hours.</p>
            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                ${durations.filter((h) => h >= Number(s.pricing.min_hours)).map((h) => field({
                    label: `${h} hours (USD)`,
                    bind: `pricing.fixed.${h}`,
                    type: 'text',
                    placeholder: p.hourlyCents ? String((p.hourlyCents * h) / 100) : '',
                    attrs: 'inputmode="decimal" maxlength="10" autocomplete="off"',
                    error: errors[`fixed.${h}`],
                }))}
            </div>
            <div class="mt-10 rounded-[2rem] bg-navy-950 p-6 text-white md:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brass-300">Estimated earnings after commission</p>
                <div id="pricing-estimate">${estimateHTML()}</div>
            </div>`;
    }

    /* ------------------------------------------------------------------ */
    /* Steps                                                               */
    /* ------------------------------------------------------------------ */
    const STEPS = [
        {
            id: 'intro', section: 0,
            left: () => ({ title: "Let's get started", text: "You'll be done in less than 15 minutes." }),
            body: () => html`
                <div class="rounded-[2rem] bg-white p-8 shadow-card ring-1 ring-navy-100 md:p-10">
                    <p class="font-display text-3xl text-navy-950">List your boat in 5 quick steps</p>
                    <ol class="mt-8 space-y-0">
                        ${O.steps.map((t, i) => html`
                            <li class="relative flex gap-4 pb-7 last:pb-0">
                                ${i < O.steps.length - 1 ? trusted('<span class="absolute left-[13px] top-8 h-[calc(100%-1.5rem)] w-0.5 bg-navy-100"></span>') : ''}
                                <span class="relative grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brass-400 text-xs font-extrabold text-navy-950">${i + 1}</span>
                                <p class="pt-0.5 font-bold text-navy-900">${t}</p>
                            </li>`)}
                    </ol>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    ${[['ship-wheel', 'Vetted USCG captains'], ['shield-check', 'Secure payouts'], ['calendar-check', 'You control the calendar']].map(([ic, t]) => html`
                        <div class="flex items-center gap-3 rounded-2xl bg-navy-50 px-4 py-3 text-xs font-bold text-navy-700"><i data-lucide="${ic}" class="h-5 w-5 shrink-0 text-brass-600"></i>${t}</div>`)}
                </div>`,
        },

        /* ---------------- Boat details ---------------- */
        {
            id: 'insuranceType', section: 1,
            left: () => ({ title: 'What type of insurance do you have?', text: 'You must have existing insurance in order for your boat to be approved. This can be recreational boat insurance or commercial charter operator insurance.' }),
            valid: () => Boolean(s.insurance_type),
            body: () => html`<div class="space-y-5">
                ${radioCard({
                    group: 'insurance_type', value: 'recreational', title: 'I have recreational boat insurance', sub: 'Select this if you own a personal boat.', tag: 'Most common', selected: s.insurance_type === 'recreational',
                    body: trusted(`<p>Chicago Yacht Booking partners with a trip-based marine insurer to offer an insurance plan for owners listing their personal boats. This rental policy covers the boat during each booking. It's added on top of your recreational policy for a fee, so you can rent your boat peer-to-peer.</p>
                        <p class="mt-4 inline-flex items-center gap-2 font-bold text-navy-900"><i data-lucide="shield-check" class="h-5 w-5 text-brass-500"></i>Coverage is arranged later in this flow</p>`),
                })}
                ${radioCard({
                    group: 'insurance_type', value: 'commercial', title: 'I have commercial charter insurance', sub: 'Select this if you run a boat rental or charter business.', selected: s.insurance_type === 'commercial',
                    body: trusted("<p>You'll add your carrier and policy details later in this flow. Your policy must list Chicago Yacht Booking as an additional insured.</p>"),
                })}
            </div>`,
        },
        {
            id: 'location', section: 1,
            left: () => ({ title: 'Where is your boat?', text: "To protect your privacy, we only show your boat's exact location to guests once you've confirmed their trip." }),
            valid: () => Boolean(s.location.type && s.location.street.trim() && s.location.city.trim() && s.location.state && s.location.zip.trim()),
            body: () => html`
                ${field({ label: 'Location type', bind: 'location.type', options: O.location_types, required: true, rerender: true })}
                ${s.location.type ? html`
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        ${field({ label: 'Country / Territory', bind: 'location.country', options: O.countries, required: true, span: 'sm:col-span-2' })}
                        ${field({ label: 'Street address', bind: 'location.street', placeholder: 'e.g. 111 N Lake Shore Dr', required: true, span: 'sm:col-span-2', attrs: 'autocomplete="address-line1" maxlength="120"' })}
                        ${field({ label: 'City', bind: 'location.city', required: true, attrs: 'autocomplete="address-level2" maxlength="60"' })}
                        ${field({ label: 'State / Province', bind: 'location.state', options: O.states, required: true })}
                        ${field({ label: 'Zip code', bind: 'location.zip', placeholder: '60601', required: true, attrs: 'inputmode="numeric" maxlength="10" autocomplete="postal-code"' })}
                    </div>
                    <p class="mt-5 flex items-center gap-2 text-xs text-navy-500"><i data-lucide="lock" class="h-4 w-4 text-brass-500"></i>Your exact address is only shared with confirmed guests.</p>` : ''}`,
        },
        {
            id: 'title', section: 1,
            left: () => ({
                title: 'Your boat listing',
                tips: tipsCard('This is what renters will see when looking for boats in Chicago. You can always edit this later.', html`
                    <p class="font-semibold text-white">Boat title</p><p class="mt-1">A great title helps your boat stand out in search results.</p>
                    <div class="my-4 h-px bg-white/10"></div>
                    <p class="font-semibold text-white">Boat description</p><p class="mb-3 mt-1">A detailed description helps renters know what to expect when renting your boat. Some important details you should include:</p>
                    ${bullets(['Identify where your boat can go on the water', 'Provide itinerary suggestions of places to see or swim', 'List any extra perks/toys that come with your boat', 'Be transparent if there are any additional costs not included in the boat price (like fuel)', 'Specify delivery areas for your boat (if applicable)'])}`),
            }),
            valid: () => Boolean(s.title.trim() && s.description.trim()),
            body: () => html`
                ${h3('Boat title and description')}
                <div class="mt-6">
                    ${field({ label: 'Listing title', bind: 'title', placeholder: 'e.g. Sunset-ready 42 ft cruiser in DuSable Harbor', required: true, attrs: `maxlength="${R.title_max}"` })}
                    <p class="mt-1.5 text-right text-xs text-navy-400"><span data-count-for="title">${R.title_max - s.title.length}</span> characters remaining</p>
                </div>
                <div class="my-8 h-px bg-navy-100"></div>
                ${field({ label: 'Description', bind: 'description', type: 'textarea', placeholder: 'Where can your boat go? What makes it special? Any toys, perks or extra costs renters should know about?', required: true, attrs: `maxlength="${R.description_max}"` })}`,
        },
        {
            id: 'cancellation', section: 1,
            left: () => ({ title: 'Cancellation policy', text: html`Select how you want to handle trip cancellations. <a href="${safeUrl(data.urls.policies)}" target="_blank" rel="noopener" class="font-semibold text-brass-300 hover:underline">Learn more</a> about our cancellation options.` }),
            valid: () => Boolean(s.cancellation),
            body: () => html`<div class="space-y-4">${O.cancellation.map((c) => {
                const on = s.cancellation === c.id;
                return html`
                <button type="button" data-radio="cancellation" data-value="${c.id}" aria-pressed="${on}" class="flex w-full flex-col gap-4 rounded-2xl p-5 text-left ring-2 transition sm:flex-row sm:items-center md:p-6 ${on ? 'bg-navy-950 text-white shadow-card ring-navy-950' : 'bg-white text-navy-950 ring-navy-100 hover:ring-navy-300'}">
                    <span class="flex items-center gap-4 sm:w-48 sm:shrink-0">${radioDot(on)}<span><span class="block text-lg font-bold">${c.name}</span>${c.badge ? html`<span class="mt-1 inline-block">${badge(c.badge)}</span>` : ''}</span></span>
                    <ul class="list-disc space-y-1.5 pl-5 text-sm ${on ? 'text-white/80' : 'text-navy-600'}">${c.points.map((p) => html`<li>${p}</li>`)}</ul>
                </button>`;
            })}</div>`,
        },
        {
            id: 'rules', section: 1,
            left: () => ({ title: 'What is allowed on your boat?', text: 'Select everything guests are allowed to bring or do on board. Anything you leave unchecked is not allowed.' }),
            body: () => checkTiles('rules', O.rules),
        },
        {
            id: 'features', section: 1,
            left: () => ({ title: 'Boat features', text: 'Show off what you offer on your boat! Renters are more likely to choose boats with lots of features that enable them to have a fun time on the water.' }),
            body: () => html`
                ${h3('Features')}<div class="mt-4">${checkTiles('features', O.features)}</div>
                ${h3('Extras', 'mt-10')}<div class="mt-4">${checkTiles('extras', O.extras)}</div>
                ${h3('Navigation', 'mt-10')}<div class="mt-4">${checkTiles('navigation', O.navigation)}</div>`,
        },
        {
            id: 'photos', section: 1,
            left: () => ({
                title: 'Boat photos',
                text: trusted('It\'s important for renters to see your boat before they request it.<br><button type="button" data-skip-photos class="mt-2 font-semibold text-brass-300 hover:underline">Skip for now</button>'),
                tips: tipsCard('Tips to get great photos of your boat', bullets(['Use your camera or smartphone.', 'Avoid using flash. Boats look best in natural light.', 'Tidy up before taking interior shots.', 'Take photos in landscape mode and avoid extreme close-ups.'])),
            }),
            valid: () => s.photos.length >= R.min_photos,
            body: () => {
                const n = s.photos.length;
                const count = Math.max(R.min_photos, n + 1);
                const tiles = Array.from({ length: count }, (_, i) => {
                    if (i < n) {
                        return html`
                        <div class="group relative aspect-[4/3] overflow-hidden rounded-2xl bg-navy-50 ring-1 ring-navy-100">
                            <img src="${safeImageSrc(s.photos[i])}" alt="Boat photo ${i + 1}" class="h-full w-full object-cover">
                            ${i === 0 ? html`<span class="absolute left-2 top-2">${badge('Cover photo')}</span>` : html`<button type="button" data-cover-photo="${i}" class="absolute left-2 top-2 rounded-md bg-white/90 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-navy-900 opacity-0 transition group-hover:opacity-100 focus:opacity-100">Make cover</button>`}
                            <button type="button" data-remove-photo="${i}" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-navy-950/80 text-white hover:bg-red-600" aria-label="Remove photo ${i + 1}"><i data-lucide="x" class="h-4 w-4"></i></button>
                        </div>`;
                    }
                    if (i === n) {
                        return html`
                        <button type="button" data-pick-photos ${n >= MAX_PHOTOS ? trusted('disabled') : ''} class="flex aspect-[4/3] flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-brass-400 bg-brass-50/60 text-brass-700 transition hover:bg-brass-50 disabled:opacity-40">
                            <i data-lucide="upload" class="h-6 w-6"></i><span class="text-xs font-bold">${n === 0 ? 'Upload cover photo' : 'Add photo'}</span>
                        </button>`;
                    }
                    return html`<div class="flex aspect-[4/3] flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-navy-100 text-navy-300"><i data-lucide="camera" class="h-6 w-6"></i><span class="text-xs font-semibold">Photo ${i + 1}</span></div>`;
                });
                return html`
                    <p class="text-sm leading-relaxed text-navy-600">Once you have a good photo that shows the whole boat, add more displaying the boat's details and interior, especially any cool or unique features.</p>
                    <div id="dropzone" class="mt-6 rounded-3xl border-2 border-dashed border-navy-200 bg-navy-50/50 px-6 py-8 text-center transition">
                        <i data-lucide="image-plus" class="mx-auto h-8 w-8 text-navy-400"></i>
                        <p class="mt-3 font-bold text-navy-900">Drag and drop or <button type="button" data-pick-photos class="text-brass-600 underline underline-offset-2 hover:text-brass-700">click here</button> to choose photos (${R.min_photos} minimum)</p>
                        <p class="mx-auto mt-2 max-w-md text-xs text-navy-400">Avoid photos with company logos and phone numbers. Photos at least 960px wide display best. Accepted formats: JPG, PNG, GIF, WEBP. Max file size: ${R.max_photo_mb} MB.</p>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">${tiles}</div>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span class="font-bold ${n >= R.min_photos ? 'text-emerald-700' : 'text-navy-500'}">${n} of ${R.min_photos} minimum photos added</span>
                        <button type="button" data-sample-photos class="font-bold text-navy-400 underline underline-offset-2 hover:text-navy-700">Demo: use sample photos</button>
                    </div>`;
            },
        },
        {
            id: 'specs', section: 1,
            left: () => ({ title: 'Boat specifications', text: "Please add your boat's specifications. These help renters (and our insurance partner) understand your boat." }),
            valid: () => {
                const p = s.specs;
                return Boolean(p.year && p.make && p.model.trim() && p.type && Number(p.length) > 0 && p.category && Number(p.passengers) > 0);
            },
            body: () => html`
                ${h3('General information')}
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    ${field({ label: 'Boat registration number / HIN', bind: 'specs.registration', placeholder: 'AA1234SS', attrs: 'maxlength="20"' })}
                    ${field({ label: 'Year', bind: 'specs.year', options: YEARS, required: true })}
                    ${field({ label: 'Make', bind: 'specs.make', options: O.makes, required: true })}
                    ${field({ label: 'Model', bind: 'specs.model', placeholder: 'e.g. Antares 10.80', required: true, attrs: 'maxlength="60"' })}
                    ${field({ label: 'Type', bind: 'specs.type', options: O.types, required: true })}
                    ${field({ label: 'Length (ft)', bind: 'specs.length', type: 'number', placeholder: '38', required: true, attrs: 'min="8" max="400" inputmode="numeric"' })}
                    ${field({ label: 'Category', bind: 'specs.category', options: O.categories, required: true })}
                </div>
                ${h3('Engine', 'mt-10')}
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    ${field({ label: 'Number of engines', bind: 'specs.engines', type: 'number', attrs: 'min="0" max="6"' })}
                    ${field({ label: 'Total horsepower', bind: 'specs.horsepower', type: 'number', attrs: 'min="0" max="20000"' })}
                    ${field({ label: 'Engine year', bind: 'specs.engine_year', options: YEARS })}
                    ${field({ label: 'Engine type', bind: 'specs.engine_type', options: O.engine_types })}
                </div>
                ${h3('Capacity', 'mt-10')}
                <div class="mt-4 grid gap-5 sm:grid-cols-3">
                    ${field({ label: 'Staterooms', bind: 'specs.staterooms', type: 'number', attrs: 'min="0" max="30"' })}
                    ${field({ label: 'Sleeps', bind: 'specs.sleeps', type: 'number', attrs: 'min="0" max="60"' })}
                    ${field({ label: 'Bed count', bind: 'specs.beds', type: 'number', attrs: 'min="0" max="60"' })}
                    ${field({ label: 'Passenger capacity', bind: 'specs.passengers', type: 'number', required: true, hint: 'Maximum guests allowed by the USCG capacity plate (excluding crew).', attrs: 'min="1" max="150"', span: 'sm:col-span-2' })}
                </div>`,
        },

        /* ---------------- Availability ---------------- */
        {
            id: 'availability', section: 2,
            left: () => ({
                title: 'Select the available days and start times',
                text: trusted(`<p>You can use the default options we selected or customize the available days and start times yourself.</p>
                    <p class="mt-3">Example: if you use our default start times, this boat will be available to depart between 8:00 AM – 6:00 PM. The return time depends on the booking durations you offer, which you will choose later.</p>
                    <p class="mt-3">We may limit the available start times a renter can select to comply with sunrise and sunset insurance restrictions.</p>`),
            }),
            valid: () => s.availability.mode === 'default'
                || (DAYS.some(([k]) => s.availability.days[k].on) && DAYS.every(([k]) => {
                    const d = s.availability.days[k];
                    return !d.on || d.start < d.end;
                })),
            body: () => {
                const custom = html`
                    <div class="space-y-3">
                        ${DAYS.map(([k, name]) => {
                            const d = s.availability.days[k];
                            const bad = d.on && d.start >= d.end;
                            return html`
                            <div class="grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 rounded-xl bg-navy-50/70 px-4 py-3 sm:grid-cols-[auto_7rem_1fr]">
                                <button type="button" data-day-toggle="${k}" role="switch" aria-checked="${d.on}" aria-label="${name} available" class="relative h-6 w-11 rounded-full transition ${d.on ? 'bg-navy-950' : 'bg-navy-200'}"><span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all ${d.on ? 'left-[22px]' : 'left-0.5'}"></span></button>
                                <span class="text-sm font-bold ${d.on ? 'text-navy-950' : 'text-navy-400'}">${name}</span>
                                <div class="col-span-2 flex items-center gap-2 sm:col-span-1">
                                    ${['start', 'end'].map((which, i) => html`${i ? trusted('<span class="text-xs font-semibold text-navy-400">to</span>') : ''}<select data-bind="availability.days.${k}.${which}" data-rerender ${d.on ? '' : trusted('disabled')} class="w-full rounded-lg border ${bad ? 'border-red-400' : 'border-navy-200'} bg-white px-2 py-2 text-xs font-semibold focus:border-navy-950 focus:outline-hidden disabled:opacity-40" aria-label="${name} ${which} time">${TIMES.map((t) => html`<option value="${t}" ${d[which] === t ? trusted('selected') : ''}>${formatTime(t)}</option>`)}</select>`)}
                                </div>
                            </div>`;
                        })}
                    </div>`;
                return html`<div class="space-y-5">
                    ${radioCard({ group: 'availability.mode', value: 'default', title: 'Default availability', selected: s.availability.mode === 'default', body: trusted('<p class="text-navy-500">Your boat will be available</p><p class="mt-2 text-lg font-bold text-navy-950">Monday to Sunday, from 8:00 AM to 6:00 PM</p>') })}
                    ${radioCard({ group: 'availability.mode', value: 'custom', title: 'Custom availability', sub: 'Customize start times for each day of the week.', selected: s.availability.mode === 'custom', body: custom })}
                    <p class="text-sm text-navy-500">You can always update this later.</p>
                </div>`;
            },
        },
        {
            id: 'advance', section: 2,
            left: () => ({ title: 'Select your advance notice', text: 'How much time do you need to prepare for potential last-minute requests? For example, if you select 7 days, this boat will only be available for bookings that start at least 7 days later. We recommend selecting 6 hours or less to capture last-minute bookings.' }),
            valid: () => Boolean(s.advance_notice),
            body: () => radioTiles('advance_notice', O.advance_notice),
        },
        {
            id: 'multi', section: 2,
            left: () => ({ title: 'Do you want to allow multiple bookings in one day?', text: 'Earn more from your boat by allowing multiple bookings on the same day. You have control over the start times and durations you offer.' }),
            valid: () => Boolean(s.multi_booking.allow),
            body: () => html`<div class="space-y-5">
                ${radioCard({ group: 'multi_booking.allow', value: 'no', title: 'No', sub: 'This boat can only be booked once a day', selected: s.multi_booking.allow === 'no' })}
                ${radioCard({
                    group: 'multi_booking.allow', value: 'yes', title: 'Yes', tag: 'Most common', selected: s.multi_booking.allow === 'yes',
                    body: html`<p class="text-base font-bold text-navy-950">How much time do you need in between bookings?</p>
                        <p class="mt-1 text-xs text-navy-500">Example: if your boat returns at 3:00 PM and you can have it ready for your next rental by 3:30 PM, select 30 minutes.</p>
                        <div class="mt-5">${radioTiles('multi_booking.buffer', O.buffers, 'sm:grid-cols-2')}</div>`,
                })}
            </div>`,
        },

        /* ---------------- Operators, durations and pricing (+ insurance) ---------------- */
        {
            id: 'operators', section: 3,
            left: () => ({
                title: 'Choose who operates your boat and desired booking durations.',
                text: 'You can let renters who you approve and/or a captain operate your boat.',
                tips: tipsCard('We offer flexibility for you and captains', 'Chicago Yacht Booking will provide a list of vetted USCG captains you can approve. If you or a captain you know wish to operate the boat, joining our captain network is easy.'),
            }),
            valid: () => (s.operators.renter || s.operators.captain) && s.durations.length > 0 && Boolean(s.fuel),
            body: () => {
                const tooLong = len() > R.renter_max_length_ft;
                const opt = (key, title, sub, disabled) => {
                    const on = s.operators[key];
                    return html`<button type="button" data-op="${key}" ${disabled ? trusted('disabled') : ''} aria-pressed="${on}" class="flex w-full items-start gap-3 p-5 text-left transition disabled:cursor-not-allowed disabled:opacity-50">
                        <span class="mt-0.5">${checkBox(on)}</span>
                        <span><span class="block text-sm font-bold text-navy-950">${title}</span><span class="mt-0.5 block text-xs text-navy-500">${sub}</span></span>
                    </button>`;
                };
                return html`
                    ${h3('Who can operate your boat? Select one or both options.')}
                    <div class="mt-4 divide-y divide-navy-100 rounded-2xl bg-white ring-2 ring-navy-100">
                        ${opt('renter', 'Renter operates my boat', tooLong ? `Our insurance partner requires boats over ${R.renter_max_length_ft} feet long to be captained.` : 'Approved renters with boating experience can drive your boat.', tooLong)}
                        ${opt('captain', 'USCG-certified captain operates my boat', `A licensed captain runs every trip — required for boats over ${R.renter_max_length_ft} ft.`)}
                        ${s.operators.captain ? html`<div class="px-5">
                            ${yesNo('operators.captain_network', 'Will you use the Chicago Yacht Booking captain network?')}
                            ${yesNo('operators.fishing_charter', 'Is this a captained fishing charter?')}
                        </div>` : ''}
                    </div>
                    ${h3("Select which durations you'd like to offer.", 'mt-10')}
                    <div class="mt-4">${checkTiles('durations', O.durations.map((h) => [h, `${h} hours`]), 'grid-cols-2 sm:grid-cols-3')}</div>
                    ${h3('Who pays for fuel?', 'mt-10')}
                    <div class="mt-4">${radioTiles('fuel', [['renter', 'Renter pays'], ['owner', 'I pay']], 'sm:grid-cols-2')}</div>
                    <p class="mt-3 text-xs text-navy-500">${s.fuel === 'renter' ? 'Fuel used is calculated and charged to the renter after each trip.' : 'Fuel cost is included in your listing price.'}</p>`;
            },
        },
        {
            id: 'pricing', section: 3,
            left: () => ({
                title: 'Set your price',
                text: 'You decide what guests pay for your boat. Our team reviews your rates before the boat goes live and may suggest changes for the Chicago market.',
                tips: tipsCard('How pricing works', bullets([
                    'Guests see your hourly rate × hours. The weekend markup applies Friday to Sunday.',
                    `Our commission is ${formatBp(data.commissionBp)} of the charter price. Your fuel & cleaning fee is paid to you in full.`,
                    'A licensed captain fee, taxes and crew gratuity are added on top for the guest.',
                    'Earnings become available to withdraw 72 hours after each trip.',
                ])),
            }),
            valid: () => {
                const p = pricing();
                return Boolean(p.hourlyCents) && Object.keys(pricingErrors()).length === 0 && s.durations.includes(Number(s.pricing.min_hours));
            },
            body: pricingBody,
        },
        {
            id: 'insurance', section: 3, when: rec,
            left: () => ({ eyebrow: `Great progress${s.owner.first_name ? `, ${s.owner.first_name}` : ''}!`, title: "Let's get you insured" }),
            body: () => html`
                <div class="rounded-[2rem] bg-white p-8 shadow-card ring-1 ring-navy-100 md:p-10">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-brass-600">Insurance provided by</p>
                    <p class="mt-2 flex items-center gap-3 font-display text-3xl text-navy-950"><i data-lucide="shield-check" class="h-8 w-8 text-brass-500"></i>Our marine insurance partner</p>
                    <p class="mt-6 text-sm leading-relaxed text-navy-600">We want everyone to feel secure while your boat is being rented. Chicago Yacht Booking partners with a trip-based marine insurer to offer an exclusive insurance plan required for owners listing personal boats. A small percentage of the listing price helps protect you, your boat, and your guests in case something happens on the water.</p>
                    <p class="mt-4 text-sm leading-relaxed text-navy-600">To get started, we'll need a few more details about you and your boat.</p>
                </div>`,
        },
        {
            id: 'insuranceOwner', section: 3, when: rec,
            left: () => ({
                title: "Let's get you and your boat peer-to-peer insurance",
                text: trusted(`<p>Chicago Yacht Booking requires owners listing their personal boats to secure peer-to-peer insurance.</p>
                    <p class="mt-3">Through our insurance partner, a trip-based boat insurance provider, your bookings on our platform are covered by peer-to-peer insurance.</p>
                    <p class="mt-3">Our partner needs these additional details to review your boat for insurance approval.</p>`),
            }),
            valid: () => String(s.insurance_owner.years).trim() !== '' && Number(s.insurance_owner.years) >= 0 && Number(s.insurance_owner.years) <= 80,
            body: () => html`
                <div class="rounded-[2rem] bg-white p-6 ring-1 ring-navy-100 md:p-8">
                    ${field({ label: 'How many years have you been operating a boat rental business?', bind: 'insurance_owner.years', type: 'number', placeholder: 'Enter answer', required: true, attrs: 'min="0" max="80"' })}
                    <div class="mt-5">${field({ label: 'Company name (optional)', bind: 'insurance_owner.company', placeholder: 'Company name', attrs: 'maxlength="120"' })}</div>
                    <div class="mt-4">
                        ${yesNo('insurance_owner.claims', 'Have you had combined boat insurance claim(s) exceeding $50,000, 2+ losses, or any constructive total vessel losses in the last 3 years?')}
                        ${yesNo('insurance_owner.criminal', 'Have you had any criminal convictions in the last 10 years or any felony convictions ever?')}
                        ${yesNo('insurance_owner.over_capacity', 'Are you offering bookings where the number of passengers exceeds the U.S. Coast Guard Capacity Plate including maximum persons, the number of persons permitted under applicable law, maximum gross load, or maximum horsepower?')}
                        ${yesNo('insurance_owner.bankruptcy', 'Have you had any bankruptcies in the last 5 years?')}
                        ${yesNo('insurance_owner.business', 'Do you operate your rentals as a registered business entity?')}
                        ${yesNo('insurance_owner.other_policy', 'Do you currently have a commercial marine policy with another carrier?')}
                    </div>
                    <p class="mt-2 border-t border-navy-100 pt-5 text-xs text-navy-500">By clicking continue, I certify that the above statements are true and correct.</p>
                </div>`,
        },
        {
            id: 'insuranceBoat', section: 3, when: rec,
            left: () => ({ title: 'A few details about your boat', text: 'These answers help our insurance partner price and approve coverage for your boat. Answer as accurately as you can — you can update them before submitting.' }),
            valid: () => Number(s.insurance_boat.value) > 0,
            body: () => {
                const captainOver = R.captain_required_over_ft;
                return html`
                <div class="rounded-[2rem] bg-white p-6 ring-1 ring-navy-100 md:p-8">
                    ${field({ label: 'Boat value (USD)', bind: 'insurance_boat.value', type: 'number', placeholder: 'e.g. 250000', required: true, hint: 'Current market value of the boat.', attrs: 'min="0" max="100000000" inputmode="numeric"' })}
                    <div class="mt-4">
                        ${yesNo('insurance_boat.customized', "Has the boat been customized, modified, or altered from the original manufacturer's specifications?")}
                        ${yesNo('insurance_boat.underlying', "In addition to our partner's coverage, do you have and will you maintain an underlying insurance policy?")}
                        ${yesNo('insurance_boat.attachments', 'Are there any crafts or apparatuses attached to the boat? (Examples are flyboard, parasail etc.)')}
                        ${yesNo('insurance_boat.seaworthy', 'Will the boat be seaworthy during all charters?')}
                        ${yesNo('insurance_boat.racing', 'Is the boat designed for racing or experimental purposes?')}
                        ${yesNo('insurance_boat.special', 'Is the boat classified as any of the following: a submersible, conversion, hovercraft, hydrofoil, hydroplane, shuttle craft, repurposed military vessel, homemade vessel, personal watercraft such as jet-skis, or similar?')}
                        ${yesNo('insurance_boat.captained', 'Will the boat be driven by a United States Coast Guard licensed captain?', { note: len() > captainOver ? `Your boat is ${len()} ft — boats over ${captainOver} feet are required to be captained.` : `Note: Boats over ${captainOver} feet are required to be captained.`, lock: len() > captainOver ? 'yes' : null })}
                    </div>
                </div>`;
            },
        },
        {
            id: 'commercial', section: 3, when: () => !rec(),
            left: () => ({ title: 'Your commercial charter insurance', text: 'Commercial operators keep their own coverage. Add your policy details so our team can verify it before your boat goes live.' }),
            valid: () => Boolean(s.commercial.carrier.trim() && s.commercial.policy.trim() && s.commercial.expires && s.commercial.liability),
            body: () => html`
                <div class="grid gap-5 sm:grid-cols-2">
                    ${field({ label: 'Insurance carrier', bind: 'commercial.carrier', placeholder: 'Carrier name', required: true, span: 'sm:col-span-2', attrs: 'maxlength="120"' })}
                    ${field({ label: 'Policy number', bind: 'commercial.policy', placeholder: 'Policy #', required: true, attrs: 'maxlength="60"' })}
                    ${field({ label: 'Policy expiration date', bind: 'commercial.expires', type: 'date', required: true })}
                    ${field({ label: 'Liability coverage', bind: 'commercial.liability', options: O.liability, required: true, span: 'sm:col-span-2' })}
                </div>
                <div class="mt-6">
                    <span class="text-sm font-bold text-navy-800">Certificate of insurance</span>
                    <label class="mt-2 flex cursor-pointer items-center gap-4 rounded-2xl border-2 border-dashed border-navy-200 bg-navy-50/50 p-5 transition hover:border-navy-400">
                        <i data-lucide="file-up" class="h-7 w-7 shrink-0 text-navy-400"></i>
                        <span class="text-sm"><span class="block font-bold text-navy-900">${s.commercial.coi || 'Upload PDF or image'}</span><span class="text-xs text-navy-400">Must list Chicago Yacht Booking as additional insured</span></span>
                        <input type="file" id="coi-input" accept="application/pdf,image/jpeg,image/png" class="hidden">
                    </label>
                </div>`,
        },

        /* ---------------- Review & submit ---------------- */
        {
            id: 'review', section: 4, layout: 'full',
            valid: () => Boolean(s.owner.first_name.trim() && s.owner.last_name.trim() && EMAIL.test(s.owner.email.trim()) && s.owner.phone.replace(/\D/g, '').length >= 10),
            body: () => reviewHTML(),
        },
        { id: 'done', section: 5, layout: 'full', body: () => doneHTML() },
    ];

    const active = () => STEPS.filter((st) => !st.when || st.when());
    const cur = () => active().find((st) => st.id === s.step) || active()[0];

    /* ------------------------------------------------------------------ */
    /* Review & done screens                                               */
    /* ------------------------------------------------------------------ */
    const dash = trusted('<span class="text-navy-300">—</span>');
    const val = (v) => (v === '' || v == null || (Array.isArray(v) && !v.length) ? dash : String(v));
    const yn = (v) => (v === 'yes' ? 'Yes' : v === 'no' ? 'No' : '—');
    const labelOf = (list, id) => (list.find(([k]) => String(k) === String(id)) || [null, id])[1];
    const rows = (pairs) => html`<dl class="divide-y divide-navy-100/70 text-sm">${pairs.map(([k, v]) => html`<div class="grid grid-cols-[1fr_auto] gap-4 py-2.5 sm:grid-cols-[1.4fr_1fr]"><dt class="text-navy-500">${k}</dt><dd class="text-right font-semibold text-navy-950 sm:text-left">${v}</dd></div>`)}</dl>`;
    const chips = (items) => (items.length ? html`<ul class="flex flex-wrap gap-2">${items.map((i) => html`<li class="rounded-full bg-navy-50 px-3 py-1 text-xs font-semibold text-navy-700">${i}</li>`)}</ul>` : trusted('<p class="text-sm text-navy-300">None selected</p>'));
    const card = (title, inner, step) => html`
        <div class="rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7">
            <div class="flex items-center justify-between gap-4"><h3 class="text-lg font-extrabold text-navy-950">${title}</h3>${step ? html`<button type="button" data-edit="${step}" class="inline-flex items-center gap-1 text-sm font-bold text-brass-600 hover:text-brass-700"><i data-lucide="pencil" class="h-3.5 w-3.5"></i>Edit</button>` : ''}</div>
            <div class="mt-4">${inner}</div>
        </div>`;
    const block = (title, cards) => html`
        <div class="grid gap-6 border-t border-navy-100 py-12 first:border-0 first:pt-0 lg:grid-cols-[1fr_1.6fr] lg:gap-12">
            <h2 class="font-display text-3xl text-navy-950 md:text-4xl">${title}</h2>
            <div class="space-y-6">${cards}</div>
        </div>`;
    const eyebrowLabel = (text) => html`<p class="text-xs font-bold uppercase tracking-[0.18em] text-navy-400">${text}</p>`;

    function pricingRows() {
        const p = pricing();
        if (!p.hourlyCents) return rows([['Hourly rate', dash]]);
        const fixed = Object.entries(p.fixedCents).sort(([a], [b]) => a - b).map(([h, c]) => `${h} hours: ${formatCents(c)}`);
        return rows([
            ['Hourly rate (Mon–Thu)', `${formatCents(p.hourlyCents)} / hour`],
            ['Weekend markup (Fri–Sun)', p.markupBp ? `+${formatBp(p.markupBp)} · ${formatCents(weekendRateCents(p.hourlyCents, p.markupBp))} / hour` : 'None'],
            ['Minimum hours', `${s.pricing.min_hours} hours`],
            ['Fuel & cleaning fee', p.cleaningCents ? `${formatCents(p.cleaningCents)} per trip` : 'None'],
            ['Fixed prices', fixed.length ? fixed.join(' · ') : 'None'],
        ]);
    }

    function reviewHTML() {
        const p = s.specs;
        const l = s.location;
        const c = O.cancellation.find((x) => x.id === s.cancellation);
        const av = s.availability.mode === 'default'
            ? 'Monday to Sunday, from 8:00 AM – 6:00 PM'
            : DAYS.filter(([k]) => s.availability.days[k].on).map(([k, n]) => `${n}: ${formatTime(s.availability.days[k].start)} – ${formatTime(s.availability.days[k].end)}`);
        const photoWarn = s.photos.length < R.min_photos;
        return html`
        <section class="relative isolate overflow-hidden bg-navy-950 py-16 text-white md:py-20">
            <img src="${safeUrl(data.images.wake)}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-25">
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-navy-950 via-navy-950/80 to-navy-900/40"></div>
            <div class="container max-w-6xl">
                <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.3em] text-brass-300"><span class="h-px w-10 bg-brass-400"></span>Review and submit</p>
                <h1 class="mt-4 max-w-2xl font-display text-4xl leading-tight md:text-5xl md:leading-none">Please review your boat listing before submitting.</h1>
            </div>
        </section>
        <div class="container max-w-6xl py-14">
            ${photoWarn ? html`<div class="mb-10 flex flex-col gap-3 rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200 sm:flex-row sm:items-center sm:justify-between"><p class="flex items-center gap-2"><i data-lucide="triangle-alert" class="h-5 w-5 shrink-0"></i>Add at least ${R.min_photos} photos before your boat can be approved. You can still submit now and add them later.</p><button type="button" data-edit="photos" class="shrink-0 font-bold underline">Add photos</button></div>` : ''}
            ${block('Boat details', [
                card('Specifications', html`
                    ${eyebrowLabel('General information')}
                    ${rows([['Boat registration number / HIN', val(p.registration)], ['Year', val(p.year)], ['Make', val(p.make)], ['Model', val(p.model)], ['Type', val(p.type)], ['Length (ft)', val(p.length)], ['Category', val(p.category)]])}
                    <div class="mt-5">${eyebrowLabel('Engine')}</div>
                    ${rows([['Number of engines', val(p.engines)], ['Engine type', val(p.engine_type)], ['Engine year', val(p.engine_year)], ['Total horsepower', val(p.horsepower)]])}
                    <div class="mt-5">${eyebrowLabel('Capacity')}</div>
                    ${rows([['Staterooms', val(p.staterooms)], ['Sleeps', val(p.sleeps)], ['Bed count', val(p.beds)], ['Passenger capacity', val(p.passengers)]])}`, 'specs'),
                card('Location', rows([['Location type', val(l.type)], ['Country / Territory', val(l.country)], ['Street address', val(l.street)], ['City', val(l.city)], ['State / Province', val(l.state)], ['Zip code', val(l.zip)]]), 'location'),
                card('Title & description', html`<p class="font-display text-xl text-navy-950">${val(s.title)}</p><p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-navy-600">${val(s.description)}</p>`, 'title'),
                card('Cancellation policy', c ? html`<p class="font-bold text-navy-950">${c.name}</p><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-navy-600">${c.points.map((x) => html`<li>${x}</li>`)}</ul>` : dash, 'cancellation'),
                card('Preferences', html`<p class="mb-3 text-sm text-navy-500">What is allowed on your boat?</p>${chips(s.rules)}`, 'rules'),
                card('Features', html`<div class="mb-2">${eyebrowLabel('Features')}</div>${chips(s.features)}<div class="mb-2 mt-5">${eyebrowLabel('Extras')}</div>${chips(s.extras)}<div class="mb-2 mt-5">${eyebrowLabel('Navigation')}</div>${chips(s.navigation)}`, 'features'),
                card('Photos', s.photos.length ? html`<div class="grid grid-cols-3 gap-3 sm:grid-cols-4">${s.photos.map((src, i) => html`<div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-navy-50"><img src="${safeImageSrc(src)}" alt="" class="h-full w-full object-cover">${i === 0 ? html`<span class="absolute left-1.5 top-1.5">${badge('Cover')}</span>` : ''}</div>`)}</div>` : trusted('<p class="text-sm text-navy-300">No photos added yet</p>'), 'photos'),
            ])}
            ${block('Availability', [
                card('Start times', html`<p class="text-sm font-semibold leading-relaxed text-navy-950">${Array.isArray(av) ? (av.length ? av.map((line, i) => html`${i ? trusted('<br>') : ''}${line}`) : '—') : av}</p>`, 'availability'),
                card('Advance notice', rows([['Advance notice', labelOf(O.advance_notice, s.advance_notice)]]), 'advance'),
                card('Multiple-booking days', rows([['Allow multiple bookings on the same day', yn(s.multi_booking.allow)], ...(s.multi_booking.allow === 'yes' ? [['Buffer time', labelOf(O.buffers, s.multi_booking.buffer)]] : [])]), 'multi'),
            ])}
            ${block('Operators and pricing', [
                card('Booking options', html`
                    ${rows([['Is this a captained fishing charter?', s.operators.captain ? yn(s.operators.fishing_charter) : 'No'], ['Renter operates my boat', s.operators.renter ? 'Yes' : 'No'], ['USCG-certified captain operates my boat', s.operators.captain ? 'Yes' : 'No'], ['Will you use the Chicago Yacht Booking captain network?', s.operators.captain ? yn(s.operators.captain_network) : 'No']])}
                    <div class="mb-2 mt-5">${eyebrowLabel('Booking durations')}</div>${chips(sortedDurations().map((h) => `${h} hours`))}
                    <div class="mb-1 mt-5">${eyebrowLabel('Fuel policy')}</div>${rows([['Who pays for fuel?', s.fuel === 'owner' ? 'I pay' : 'Renter pays']])}`, 'operators'),
                card('Pricing', pricingRows(), 'pricing'),
            ])}
            ${block('Insurance', rec() ? [
                card('Boat owner', rows([
                    ['Insurance type', 'Recreational boat insurance'],
                    ['How many years have you been operating a boat rental business?', val(s.insurance_owner.years)],
                    ['Company name', val(s.insurance_owner.company)],
                    ['Combined claims over $50,000, 2+ losses, or total vessel losses in the last 3 years?', yn(s.insurance_owner.claims)],
                    ['Criminal convictions in the last 10 years or any felony ever?', yn(s.insurance_owner.criminal)],
                    ['Bookings exceeding the U.S. Coast Guard Capacity Plate?', yn(s.insurance_owner.over_capacity)],
                    ['Bankruptcies in the last 5 years?', yn(s.insurance_owner.bankruptcy)],
                    ['Rentals operated as a registered business entity?', yn(s.insurance_owner.business)],
                    ['Commercial marine policy with another carrier?', yn(s.insurance_owner.other_policy)],
                ]), 'insuranceOwner'),
                card('Boat', rows([
                    ['Boat value', Number(s.insurance_boat.value) > 0 ? formatCents(Math.round(Number(s.insurance_boat.value) * 100)) : dash],
                    ["Customized, modified, or altered from the manufacturer's specifications?", yn(s.insurance_boat.customized)],
                    ['Underlying insurance policy maintained?', yn(s.insurance_boat.underlying)],
                    ['Crafts or apparatuses attached (flyboard, parasail…)?', yn(s.insurance_boat.attachments)],
                    ['Seaworthy during all charters?', yn(s.insurance_boat.seaworthy)],
                    ['Designed for racing or experimental purposes?', yn(s.insurance_boat.racing)],
                    ['Special vessel classification (submersible, hovercraft, PWC…)?', yn(s.insurance_boat.special)],
                    ['Driven by a USCG licensed captain?', yn(s.insurance_boat.captained)],
                ]), 'insuranceBoat'),
            ] : [
                card('Commercial charter insurance', rows([['Insurance type', 'Commercial charter insurance'], ['Carrier', val(s.commercial.carrier)], ['Policy number', val(s.commercial.policy)], ['Expires', s.commercial.expires ? formatDate(s.commercial.expires) : dash], ['Liability coverage', val(s.commercial.liability)], ['Certificate of insurance', val(s.commercial.coi)]]), 'commercial'),
            ])}
            ${block('Your details', [html`
                <div class="rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7">
                    <p class="text-sm text-navy-500">We'll use these to contact you about your listing. <span class="text-navy-400">(In the live site this comes from your owner account.)</span></p>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        ${field({ label: 'First name', bind: 'owner.first_name', required: true, attrs: 'autocomplete="given-name" maxlength="60"' })}
                        ${field({ label: 'Last name', bind: 'owner.last_name', required: true, attrs: 'autocomplete="family-name" maxlength="60"' })}
                        ${field({ label: 'Email', bind: 'owner.email', type: 'email', required: true, attrs: 'autocomplete="email" maxlength="254"' })}
                        ${field({ label: 'Mobile phone', bind: 'owner.phone', type: 'tel', placeholder: '(312) 000-0000', required: true, attrs: 'autocomplete="tel" maxlength="30"' })}
                    </div>
                </div>`])}
            <div class="grid gap-6 border-t border-navy-100 pt-12 lg:grid-cols-[1fr_1.6fr] lg:gap-12">
                <h2 class="font-display text-3xl text-navy-950 md:text-4xl">Chicago Yacht Booking standards</h2>
                <div>
                    ${bullets(['Keep your boat well-maintained and make sure all regulatory and safety requirements have been met so your renters are safe on the water.', 'Clean and refuel your boat before every trip so guests have an enjoyable experience.'], 'text-sm leading-relaxed text-navy-700')}
                    <p class="mt-5 text-xs text-navy-400">These standards keep our boating community safe and reliable. By clicking "Submit", you're agreeing to our standards.</p>
                </div>
            </div>
        </div>`;
    }

    function doneHTML() {
        const name = s.owner.first_name ? `, ${s.owner.first_name}` : '';
        const isEdit = s.original_status === 'approved';
        return html`
        <section class="relative isolate grid min-h-[calc(100svh-4rem)] place-items-center overflow-hidden bg-navy-950 px-4 py-16 text-white">
            <img src="${safeUrl(data.images.hero)}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-25">
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-navy-950/70 to-navy-950"></div>
            <div class="w-full max-w-2xl text-center">
                <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-brass-400 text-navy-950 shadow-glow"><i data-lucide="check" class="h-10 w-10"></i></span>
                <h1 class="mt-6 font-display text-4xl md:text-6xl">${isEdit ? `Your changes are submitted${name}!` : `Your boat is submitted${name}!`}</h1>
                <p class="mx-auto mt-4 max-w-lg text-white/70">${isEdit
                    ? 'Text and photo changes go live right away. Changes to price, specs, location or insurance go live after our team re-approves them.'
                    : html`Our fleet team reviews new listings within 2 business days. We'll email <strong class="text-white">${s.owner.email}</strong> once it's approved — or if we need anything else.`}</p>
                <div class="mx-auto mt-8 flex max-w-md items-center gap-4 rounded-2xl bg-white p-3 pr-5 text-left text-navy-950 shadow-2xl">
                    ${s.photos[0] ? html`<img src="${safeImageSrc(s.photos[0])}" alt="" class="h-16 w-20 shrink-0 rounded-xl object-cover">` : trusted('<span class="grid h-16 w-20 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-300"><i data-lucide="sailboat" class="h-6 w-6"></i></span>')}
                    <div class="min-w-0 flex-1"><p class="truncate font-bold">${listingTitle(s)}</p><p class="text-xs text-navy-500">${s.location.city}${s.location.state ? `, ${s.location.state}` : ''}</p></div>
                    <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800">Pending review</span>
                </div>
                <ol class="mx-auto mt-10 grid max-w-xl gap-3 text-left sm:grid-cols-2">
                    ${[['clipboard-check', 'Listing review', 'We check details, photos & insurance.'], ['landmark', 'Add payout info', 'So we can pay you after each trip.'], ['badge-dollar-sign', 'Pricing review', 'We confirm your rates for Chicago.'], ['sailboat', 'Go live', 'Your boat appears in our fleet.']].map(([ic, t, d], i) => html`
                        <li class="flex gap-3 rounded-2xl border border-white/10 bg-white/5 p-4"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full ${i === 0 ? 'bg-brass-400 text-navy-950' : 'bg-white/10 text-brass-300'}"><i data-lucide="${ic}" class="h-4 w-4"></i></span><span><span class="block text-sm font-bold">${t}</span><span class="text-xs text-white/60">${d}</span></span></li>`)}
                </ol>
                <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="${safeUrl(data.urls.dashboard)}" class="inline-flex items-center justify-center gap-2 rounded-full bg-brass-400 px-8 py-4 text-sm font-bold text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300">Go to owner dashboard <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                    <a href="${safeUrl(data.urls.boats)}" class="inline-flex items-center gap-2 rounded-full border border-white/25 px-8 py-4 text-sm font-bold hover:bg-white hover:text-navy-950">View my boats</a>
                </div>
            </div>
        </section>`;
    }

    /* ------------------------------------------------------------------ */
    /* Layout, bottom bar, render                                          */
    /* ------------------------------------------------------------------ */
    function splitHTML(st) {
        const L = st.left();
        const secSteps = active().filter((x) => x.section === st.section);
        const eyebrow = L.eyebrow || (st.section >= 1 && st.section <= 3 ? `${SECTIONS[st.section - 1]} · ${secSteps.indexOf(st) + 1} of ${secSteps.length}` : '');
        return html`
        <div class="grid lg:min-h-[calc(100vh-9rem)] lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <section class="relative isolate overflow-hidden bg-navy-950 text-white lg:sticky lg:top-16 lg:h-[calc(100vh-9rem)]">
                <img src="${safeUrl(data.images.wake)}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-15">
                <div class="absolute inset-0 -z-10 bg-gradient-to-br from-navy-950 via-navy-950/95 to-navy-800/90"></div>
                <div class="pointer-events-none absolute -bottom-32 -right-32 -z-10 h-96 w-96 rounded-full bg-brass-400/15 blur-3xl"></div>
                <div class="no-scrollbar flex h-full flex-col justify-center overflow-y-auto px-6 py-10 sm:px-10 md:py-14 xl:px-20">
                    ${eyebrow ? html`<p class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.25em] text-brass-300"><span class="h-px w-8 bg-brass-400"></span>${eyebrow}</p>` : ''}
                    <h1 class="mt-4 font-display text-3xl leading-tight md:text-5xl md:leading-none">${L.title}</h1>
                    ${L.text ? html`<div class="mt-5 max-w-lg text-sm leading-relaxed text-white/70 md:text-base md:leading-6">${L.text}</div>` : ''}
                    ${L.tips || ''}
                </div>
            </section>
            <section class="flex bg-white px-5 py-10 sm:px-10 md:py-14 lg:items-center xl:px-16">
                <div class="mx-auto w-full max-w-2xl">${st.body()}</div>
            </section>
        </div>`;
    }

    function barHTML(st) {
        const act = active();
        const valid = st.valid ? st.valid() : true;
        const segs = SECTIONS.map((name, i) => {
            const n = i + 1;
            const inSec = act.filter((x) => x.section === n);
            const done = st.section > n;
            const current = st.section === n;
            const fill = done ? 100 : current ? (n === 4 ? 100 : Math.round(((inSec.indexOf(st) + 1) / inSec.length) * 100)) : 0;
            return html`
                <div class="min-w-0 flex-1">
                    <p class="mb-2 hidden truncate text-xs font-bold md:block ${current ? 'text-navy-950' : done ? 'text-navy-600' : 'text-navy-400'}">${name}</p>
                    <div class="relative h-1.5 rounded-full bg-navy-100">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-brass-400 transition-all duration-500" data-fill="${fill}"></div>
                        <span class="absolute -top-[3px] left-0 h-3 w-3 rounded-full ring-2 ring-white ${done || current ? 'bg-brass-500' : 'bg-navy-200'}"></span>
                    </div>
                </div>`;
        });
        return html`
            <div class="flex h-20 items-center gap-3 px-3 sm:gap-6 sm:px-6 md:px-8">
                <button type="button" data-back class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border-2 border-navy-200 text-navy-700 transition hover:border-navy-950 hover:text-navy-950" aria-label="Back"><i data-lucide="chevron-left" class="h-5 w-5"></i></button>
                <div class="min-w-0 flex-1 ${st.section === 0 ? 'invisible' : ''}">
                    <p class="mb-1.5 truncate text-[11px] font-bold text-navy-950 md:hidden">${SECTIONS[Math.max(0, st.section - 1)]}</p>
                    <div class="flex gap-2 md:gap-4" role="progressbar" aria-label="Listing progress" aria-valuemin="1" aria-valuemax="4" aria-valuenow="${Math.max(1, Math.min(4, st.section))}">${segs}</div>
                </div>
                <button type="button" data-next ${valid ? '' : trusted('disabled')} class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-brass-400 px-6 py-3.5 text-sm font-bold uppercase tracking-wider text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300 disabled:cursor-not-allowed disabled:bg-navy-100 disabled:text-navy-400 disabled:shadow-none disabled:hover:translate-y-0 sm:px-8">${st.id === 'review' ? 'Submit' : 'Continue'}</button>
            </div>`;
    }

    function normalize() {
        if (len() > R.renter_max_length_ft) s.operators.renter = false;
        if (len() > R.captain_required_over_ft) s.insurance_boat.captained = 'yes';
        s.durations = s.durations.filter((h) => O.durations.includes(h));
        const durations = sortedDurations();
        if (!durations.includes(Number(s.pricing.min_hours))) s.pricing.min_hours = durations[0] ?? '';
        s.pricing.min_hours = s.pricing.min_hours === '' ? '' : Number(s.pricing.min_hours);
        s.pricing.weekend_markup_bp = O.pricing.weekend_markups_bp.includes(Number(s.pricing.weekend_markup_bp)) ? Number(s.pricing.weekend_markup_bp) : O.pricing.default_weekend_markup_bp;
        for (const h of Object.keys(s.pricing.fixed)) {
            if (!durations.includes(Number(h))) delete s.pricing.fixed[h];
        }
        if (!active().some((x) => x.id === s.step)) s.step = active()[0].id;
    }

    function updateEarnings() {
        const L = len();
        $('earnings').textContent = L > 0 ? formatCents(Math.round(L * 86.4) * 100, WHOLE) : '—';
    }

    function paintBar() {
        for (const el of document.querySelectorAll('[data-fill]')) el.style.width = `${Number(el.dataset.fill) || 0}%`;
    }

    function updateBar() {
        const st = cur();
        const bar = $('wizard-bar');
        if (st.id === 'done') {
            bar.replaceChildren();
            return;
        }
        setHtml(bar, barHTML(st));
        paintBar();
        renderIcons(bar);
    }

    /** Keep keyboard focus on the same control after a re-render. */
    let refocus = null;

    function render() {
        normalize();
        const st = cur();
        setHtml(root, st.layout === 'full' ? st.body() : splitHTML(st));
        main.classList.toggle('pb-20', st.id !== 'done');
        $('wizard-bar').classList.toggle('hidden', st.id === 'done');
        $('save-exit').textContent = st.id === 'done' ? 'Exit' : 'Save and exit';
        updateBar();
        updateEarnings();
        if (lastStep !== st.id) {
            root.firstElementChild?.classList.add('step-panel');
            window.scrollTo({ top: 0 });
            lastStep = st.id;
        } else if (refocus) {
            document.querySelector(refocus)?.focus({ preventScroll: true });
        }
        refocus = null;
        document.title = `${st.id === 'review' ? 'Review your listing' : st.id === 'done' ? 'Listing submitted' : 'List Your Boat'} — Chicago Yacht Booking`;
        renderIcons(root);
    }

    /** Pricing inputs update the estimate and errors without re-rendering (keeps the cursor in place). */
    function updatePricing() {
        const box = $('pricing-estimate');
        if (box) {
            setHtml(box, estimateHTML());
        }
        const errors = pricingErrors();
        for (const input of root.querySelectorAll('[data-bind^="pricing."]')) {
            const key = input.dataset.bind.replace('pricing.', '');
            const message = errors[key];
            input.classList.toggle('border-red-400', Boolean(message));
            if (message) input.setAttribute('aria-invalid', 'true');
            else input.removeAttribute('aria-invalid');
            const label = input.closest('label');
            let note = label?.querySelector('[data-error-for]');
            if (message && label && !note) {
                note = document.createElement('span');
                note.className = 'mt-1.5 block text-xs font-semibold text-red-600';
                note.dataset.errorFor = input.dataset.bind;
                label.querySelector('.text-navy-400:last-child')?.classList.add('hidden');
                label.appendChild(note);
            }
            if (note) {
                note.textContent = message ?? '';
                note.classList.toggle('hidden', !message);
                if (!message) label.querySelector('.text-navy-400.hidden')?.classList.remove('hidden');
            }
        }
        // Fixed-price placeholders follow the hourly rate.
        const hourly = pricing().hourlyCents;
        for (const input of root.querySelectorAll('[data-bind^="pricing.fixed."]')) {
            const h = Number(input.dataset.bind.split('.').pop());
            input.placeholder = hourly ? String((hourly * h) / 100) : '';
        }
    }

    /* ------------------------------------------------------------------ */
    /* Persistence & navigation                                            */
    /* ------------------------------------------------------------------ */
    let warned = false;
    function persist() {
        if (s.step === 'intro' && !listingStore.get(s.id)) return; // don't save untouched drafts
        if (!listingStore.save(s) && !warned) {
            warned = true;
            toast('Browser storage is full — your photos are too large to save in this demo. Everything else is saved.', 'error');
            listingStore.save({ ...s, photos: s.photos.filter((p) => !String(p).startsWith('data:')) });
        }
    }
    let saveTimer;
    const persistSoon = () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(persist, 400);
    };

    function go(id) {
        s.step = id;
        if (id === 'review') returnToReview = false;
        persist();
        render();
    }
    function next() {
        const st = cur();
        if (st.valid && !st.valid()) return;
        if (st.id === 'review') return submit();
        const act = active();
        go(returnToReview ? 'review' : act[act.indexOf(st) + 1].id);
    }
    function back() {
        if (returnToReview) {
            go('review');
            return;
        }
        const act = active();
        const i = act.indexOf(cur());
        if (i <= 0) {
            window.location.href = safeUrl(data.urls.home);
            return;
        }
        go(act[i - 1].id);
    }
    function submit() {
        s.status = s.original_status === 'approved' ? 'approved' : 'pending';
        s.submitted_at = new Date().toISOString();
        go('done');
    }

    /* ------------------------------------------------------------------ */
    /* Photos (Phase 1: resized in the browser; Phase 3: uploaded to the server) */
    /* ------------------------------------------------------------------ */
    function shrink(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                const k = Math.min(1, 1000 / img.width);
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * k);
                canvas.height = Math.round(img.height * k);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                resolve(canvas.toDataURL('image/jpeg', 0.72));
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('unreadable'));
            };
            img.src = url;
        });
    }
    async function addFiles(files) {
        const images = [...files].filter((f) => IMAGE_TYPES.includes(f.type));
        if (!images.length) {
            toast('Please choose image files (JPG, PNG, GIF or WEBP)', 'error');
            return;
        }
        for (const f of images) {
            if (s.photos.length >= MAX_PHOTOS) {
                toast(`You can add up to ${MAX_PHOTOS} photos`, 'error');
                break;
            }
            if (f.size > R.max_photo_mb * 1024 * 1024) {
                toast(`${f.name} is larger than ${R.max_photo_mb} MB`, 'error');
                continue;
            }
            try {
                s.photos.push(await shrink(f));
            } catch {
                toast(`Couldn't read ${f.name}`, 'error');
            }
        }
        s.photos_skipped = false;
        persist();
        render();
    }

    /* ------------------------------------------------------------------ */
    /* Events                                                              */
    /* ------------------------------------------------------------------ */
    document.addEventListener('click', (e) => {
        const t = e.target instanceof Element ? e.target : null;
        if (!t) return;
        let b;
        if (t.closest('[data-next]')) return next();
        if (t.closest('[data-back]')) return back();
        if ((b = t.closest('[data-edit]'))) {
            returnToReview = true;
            s.step = b.dataset.edit;
            persist();
            render();
            return;
        }
        if ((b = t.closest('[data-radio]'))) {
            const path = b.dataset.radio;
            setP(path, path === 'pricing.weekend_markup_bp' ? Number(b.dataset.value) : b.dataset.value);
            refocus = `[data-radio="${CSS.escape(path)}"][data-value="${CSS.escape(b.dataset.value)}"]`;
            persistSoon();
            render();
            return;
        }
        if ((b = t.closest('[data-check]'))) {
            const path = b.dataset.check;
            const v = path === 'durations' ? Number(b.dataset.value) : b.dataset.value;
            const arr = getP(path);
            setP(path, arr.includes(v) ? arr.filter((x) => x !== v) : [...arr, v]);
            refocus = `[data-check="${CSS.escape(path)}"][data-value="${CSS.escape(b.dataset.value)}"]`;
            persistSoon();
            render();
            return;
        }
        if ((b = t.closest('[data-op]'))) {
            const k = b.dataset.op;
            if (k in s.operators) s.operators[k] = !s.operators[k];
            refocus = `[data-op="${CSS.escape(k)}"]`;
            persistSoon();
            render();
            return;
        }
        if ((b = t.closest('[data-day-toggle]'))) {
            const d = s.availability.days[b.dataset.dayToggle];
            if (d) d.on = !d.on;
            refocus = `[data-day-toggle="${CSS.escape(b.dataset.dayToggle)}"]`;
            persistSoon();
            render();
            return;
        }
        if (t.closest('[data-pick-photos]')) {
            $('photo-input').click();
            return;
        }
        if ((b = t.closest('[data-remove-photo]'))) {
            s.photos.splice(Number(b.dataset.removePhoto), 1);
            persist();
            render();
            return;
        }
        if ((b = t.closest('[data-cover-photo]'))) {
            const i = Number(b.dataset.coverPhoto);
            s.photos.unshift(s.photos.splice(i, 1)[0]);
            persist();
            render();
            return;
        }
        if (t.closest('[data-sample-photos]')) {
            for (const p of data.samplePhotos) if (!s.photos.includes(p)) s.photos.push(p);
            persist();
            render();
            return;
        }
        if (t.closest('[data-skip-photos]')) {
            s.photos_skipped = true;
            const act = active();
            go(returnToReview ? 'review' : act[act.indexOf(cur()) + 1].id);
            return;
        }
        if (t.closest('#save-exit')) {
            if (s.step === 'intro' && !listingStore.get(s.id)) {
                window.location.href = safeUrl(data.urls.boats);
                return;
            }
            persist();
            toast(s.step === 'done' ? 'Saved' : 'Draft saved');
            setTimeout(() => {
                window.location.href = safeUrl(data.urls.boats);
            }, 500);
        }
    });

    const onField = (e) => {
        const el = e.target;
        if (!(el instanceof Element) || !el.matches('[data-bind]')) return;
        const bind = el.dataset.bind;
        setP(bind, bind === 'pricing.min_hours' ? Number(el.value) : el.value);
        if (bind === 'title') {
            const counter = root.querySelector('[data-count-for="title"]');
            if (counter) counter.textContent = String(R.title_max - el.value.length);
        }
        if (bind === 'specs.length') updateEarnings();
        if (bind.startsWith('pricing.')) updatePricing();
        persistSoon();
        if (e.type === 'change' && el.hasAttribute('data-rerender')) {
            refocus = `[data-bind="${CSS.escape(bind)}"]`;
            render();
        } else {
            updateBar();
        }
    };
    document.addEventListener('input', onField);
    document.addEventListener('change', (e) => {
        if (e.target.id === 'photo-input') {
            addFiles(e.target.files);
            e.target.value = '';
            return;
        }
        if (e.target.id === 'coi-input') {
            const f = e.target.files[0];
            if (f) {
                if (!['application/pdf', 'image/jpeg', 'image/png'].includes(f.type) || f.size > 10 * 1024 * 1024) {
                    toast('Please choose a PDF, JPG or PNG up to 10 MB', 'error');
                } else {
                    s.commercial.coi = f.name.slice(0, 120);
                    persist();
                    render();
                }
            }
            e.target.value = '';
            return;
        }
        onField(e);
    });

    // Drag & drop photos
    document.addEventListener('dragover', (e) => {
        const zone = e.target instanceof Element ? e.target.closest('#dropzone') : null;
        if (!zone) return;
        e.preventDefault();
        zone.classList.add('border-brass-400', 'bg-brass-50');
    });
    document.addEventListener('dragleave', (e) => {
        if (e.target instanceof Element) e.target.closest('#dropzone')?.classList.remove('border-brass-400', 'bg-brass-50');
    });
    document.addEventListener('drop', (e) => {
        const zone = e.target instanceof Element ? e.target.closest('#dropzone') : null;
        if (!zone) return;
        e.preventDefault();
        addFiles(e.dataTransfer.files);
    });

    // Enter in a text field = Continue (not in textareas)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target instanceof Element && e.target.matches('input[data-bind]') && cur().id !== 'review') {
            e.preventDefault();
            next();
        }
    });

    render();
    if (restored) toast('Welcome back — we restored your saved draft.');
}
