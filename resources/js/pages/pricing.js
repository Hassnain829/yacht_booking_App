import { html, setHtml, safeUrl } from '../lib/dom.js';
import { formatCents } from '../lib/money.js';
import { quote, durationsFor } from '../lib/demo-quote.js';

/** Estimates are shown in whole dollars (exact cents appear at checkout). */
const WHOLE = { cents: 'never' };

/**
 * Pricing page: live estimator (DEMO maths — see lib/demo-quote.js; the server recalculates at checkout).
 * The first estimate is rendered by the server, so the card is never empty.
 */
export default function pricingPage() {
    const form = document.getElementById('estimator');
    if (!form) return;

    const data = JSON.parse(form.dataset.estimator);
    const yachts = Object.fromEntries(data.yachts.map((y) => [y.slug, y]));
    const experiences = Object.fromEntries(data.experiences.map((e) => [e.slug, e]));
    const addons = Object.fromEntries(data.addons.map((a) => [a.slug, a]));
    const selected = new Set();
    const $ = (id) => document.getElementById(id);

    const fillExperiences = () => {
        const yacht = yachts[form.yacht.value];
        const previous = form.experience.value;
        const list = data.experiences.filter((e) => yacht.experiences.includes(e.slug));
        setHtml(form.experience, html`${list.map((e) => html`<option value="${e.slug}">${e.name}</option>`)}`);
        if (list.some((e) => e.slug === previous)) form.experience.value = previous;
        form.guests.max = String(yacht.max_guests);
        if (Number(form.guests.value) > yacht.max_guests) form.guests.value = String(yacht.max_guests);
    };

    const fillHours = () => {
        const previous = Number(form.hours.value);
        const list = durationsFor(yachts[form.yacht.value], experiences[form.experience.value]);
        setHtml(form.hours, html`${list.map((h) => html`<option value="${h}">${h} hours</option>`)}`);
        if (list.includes(previous)) form.hours.value = String(previous);
    };

    const update = () => {
        const yacht = yachts[form.yacht.value];
        const experience = experiences[form.experience.value];
        const guests = Math.max(1, Math.min(yacht.max_guests, Math.round(Number(form.guests.value) || 1)));
        const weekend = form.day.value === 'weekend';
        const q = quote(yacht, experience, data.pricing, {
            date: weekend ? data.dates.weekend : data.dates.weekday,
            hours: form.hours.value,
            guests,
            addons: [...selected].map((slug) => addons[slug]),
        });

        $('est-image').src = safeUrl(yacht.cover);
        $('est-experience').textContent = experience?.name ?? '';
        $('est-title').textContent = `${yacht.title} · ${q.hours} hrs · ${guests} guests`;
        setHtml(
            $('est-lines'),
            html`${q.lines.map(
                (l) => html`<div class="flex justify-between gap-4"><dt class="text-white/70">${l.label}<span class="block text-xs text-white/40">${l.sub}</span></dt><dd class="font-semibold">${formatCents(l.amount, WHOLE)}</dd></div>`,
            )}<div class="flex justify-between gap-4"><dt class="text-white/70">Taxes (est. ${data.pricing.tax_rate_bp / 100}%)</dt><dd class="font-semibold">${formatCents(q.tax, WHOLE)}</dd></div>`,
        );
        $('est-total').textContent = formatCents(q.total, WHOLE);
        $('est-deposit').textContent = formatCents(q.deposit, WHOLE);

        const params = new URLSearchParams({ yacht: yacht.slug, experience: form.experience.value, hours: String(q.hours), guests: String(guests) });
        if (selected.size) params.set('addons', [...selected].join(','));
        $('est-book').href = `${data.bookUrl}?${params}`;
    };

    form.yacht.addEventListener('change', () => {
        fillExperiences();
        fillHours();
        update();
    });
    form.experience.addEventListener('change', () => {
        fillHours();
        update();
    });
    form.addEventListener('input', update);
    form.addEventListener('change', update);
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
        update();
    });

    update();
}
