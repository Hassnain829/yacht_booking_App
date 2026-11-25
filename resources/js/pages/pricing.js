import { html, setHtml, safeUrl } from '../lib/dom.js';
import { formatCents } from '../lib/money.js';
import { http, HttpError } from '../lib/http.js';

/** Estimates are shown in whole dollars (exact cents appear at checkout). */
const WHOLE = { cents: 'never' };

/**
 * Pricing page: live estimator. Since 4.3 every number comes from GET /yachts/{slug}/quote (QuoteService), so it
 * matches the boat page and checkout exactly; the browser only shows it. The first estimate is rendered by the
 * server, so the card is never empty.
 */
export default function pricingPage() {
    const form = document.getElementById('estimator');
    if (!form) return;

    const data = JSON.parse(form.dataset.estimator);
    const yachts = Object.fromEntries(data.yachts.map((y) => [y.slug, y]));
    const selected = new Set();
    const $ = (id) => document.getElementById(id);
    let timer = null;
    let pending = null;

    const fillExperiences = () => {
        const yacht = yachts[form.yacht.value];
        const previous = form.experience.value;
        const list = yacht.experiences.filter((slug) => data.experiences[slug]);
        setHtml(form.experience, html`<option value="">Just the boat</option>${list.map((slug) => html`<option value="${slug}">${data.experiences[slug]}</option>`)}`);
        form.experience.value = list.includes(previous) ? previous : '';
        form.guests.max = String(yacht.max_guests);
        if (Number(form.guests.value) > yacht.max_guests) form.guests.value = String(yacht.max_guests);
    };

    const fillHours = () => {
        const yacht = yachts[form.yacht.value];
        const previous = Number(form.hours.value);
        const list = yacht.lengths; // the boat's, the same with any experience
        setHtml(form.hours, html`${list.map((h) => html`<option value="${h}">${h} ${h === 1 ? 'hour' : 'hours'}</option>`)}`);
        if (list.includes(previous)) form.hours.value = String(previous);
    };

    const update = async () => {
        const yacht = yachts[form.yacht.value];
        const guests = Math.max(1, Math.min(yacht.max_guests, Math.round(Number(form.guests.value) || 1)));
        const query = new URLSearchParams({ hours: form.hours.value, guests: String(guests), date: data.dates[form.day.value === 'weekend' ? 'weekend' : 'weekday'] });
        if (form.experience.value) query.set('experience', form.experience.value);
        for (const slug of selected) query.append('addons[]', slug);

        pending?.abort();
        pending = new AbortController();
        try {
            const { data: q } = await http.get(`${yacht.quoteUrl}?${query}`, { signal: pending.signal });
            $('est-image').src = safeUrl(yacht.cover);
            $('est-experience').textContent = data.experiences[form.experience.value] ?? 'Private charter';
            $('est-title').textContent = `${yacht.title} · ${q.hours} hrs · ${guests} guests`;
            setHtml(
                $('est-lines'),
                html`${q.lines.map(
                    (l) => html`<div class="flex justify-between gap-4"><dt class="text-white/70">${l.label}<span class="block text-xs text-white/40">${l.description}</span></dt><dd class="font-semibold">${formatCents(l.amount_cents, WHOLE)}</dd></div>`,
                )}<div class="flex justify-between gap-4"><dt class="text-white/70">Taxes</dt><dd class="font-semibold">${formatCents(q.tax_cents, WHOLE)}</dd></div>`,
            );
            $('est-total').textContent = formatCents(q.total_cents, WHOLE);

            const book = new URLSearchParams({ hours: String(q.hours), guests: String(guests) });
            if (form.experience.value) book.set('experience', form.experience.value);
            if (selected.size) book.set('addons', [...selected].join(',')); // ticked at checkout, where they can be unticked
            $('est-book').href = `${yacht.bookUrl}?${book}#book`;
        } catch (error) {
            if (pending.signal.aborted) return;
            $('est-total').textContent = '—';
            setHtml($('est-lines'), html`<p class="text-sm text-white/70">${error instanceof HttpError ? error.message : 'Could not load the price. Please try again.'}</p>`);
        }
    };
    const refresh = () => {
        clearTimeout(timer);
        timer = setTimeout(update, 200);
    };

    form.yacht.addEventListener('change', () => {
        fillExperiences();
        fillHours();
        refresh();
    });
    form.experience.addEventListener('change', () => {
        fillHours();
        refresh();
    });
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('submit', (event) => event.preventDefault());

    $('est-addons').addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-addon]') : null;
        if (!button) return;
        const slug = button.dataset.addon;
        if (selected.has(slug)) selected.delete(slug);
        else selected.add(slug);
        const on = selected.has(slug);
        button.classList.toggle('bg-navy-950', on);
        button.classList.toggle('text-white', on);
        button.classList.toggle('border-navy-950', on);
        button.setAttribute('aria-pressed', String(on));
        refresh();
    });
}
