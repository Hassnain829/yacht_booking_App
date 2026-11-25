import { revealAll } from '../ui/reveal.js';

/**
 * Fleet page (4.4, D21): instant filtering / sorting of the server-rendered cards, with the URL kept in sync so a
 * filtered view can be shared. Same rules as App\Support\FleetFilters, which also renders the first view, so it
 * works without JS. A date needs the availability calendar, so with a date the form is submitted (the server
 * shows only the boats free that day) instead of filtering here.
 */
const PRICES = { 'under-500': [null, 50000], '500-1000': [50000, 100000], 'over-1000': [100000, null] };
const LENGTHS = { 'under-40': [null, 40], '40-60': [40, 60], 'over-60': [60, null] };
const DEFAULTS = { type: 'All', date: '', guests: '0', experience: '', hours: '0', harbor: '', price: '', length: '', sort: 'popular' };
const SORTERS = {
    'price-asc': (a, b) => a.dataset.price - b.dataset.price,
    'price-desc': (a, b) => b.dataset.price - a.dataset.price,
    guests: (a, b) => b.dataset.guests - a.dataset.guests,
    popular: (a, b) => b.dataset.reviews - a.dataset.reviews,
};

const inRange = (value, [min, max]) => Number.isFinite(value) && (min === null || value >= min) && (max === null || value < max);

export default function yachts() {
    const form = document.getElementById('fleet-filters');
    const grid = document.getElementById('yacht-grid');
    const chips = document.getElementById('type-chips');
    if (!form || !grid || !chips) return;

    const typeInput = form.querySelector('input[name="type"]');
    const count = document.getElementById('result-count');
    const empty = document.getElementById('empty-state');
    const reset = document.getElementById('reset-filters');
    const more = document.getElementById('more-filters');
    const moreToggle = document.getElementById('more-toggle');
    const moreCount = document.getElementById('more-count');
    const cards = Array.from(grid.querySelectorAll('article[data-type]'));

    const field = (name) => form.elements.namedItem(name); // not form.length: that is the number of controls
    const amenities = () => Array.from(form.querySelectorAll('input[name="amenities[]"]:checked')).map((box) => box.value);
    const values = () => ({
        type: typeInput.value,
        date: field('date').value,
        guests: field('guests').value,
        experience: field('experience').value,
        hours: field('hours').value,
        harbor: field('harbor').value,
        price: field('price').value,
        length: field('length').value,
        sort: field('sort').value,
    });

    const matches = (card, v, chosen) => {
        const d = card.dataset;
        const offered = JSON.parse(d.lengths || '[]'); // the boat's lengths, the same with any experience
        const has = (list, item) => list.split(' ').includes(item);
        return (
            (v.type === 'All' || d.type === v.type) &&
            (v.guests === '0' || Number(d.guests) >= Number(v.guests)) &&
            (!v.experience || has(d.experiences, v.experience)) &&
            (v.hours === '0' || offered.includes(Number(v.hours))) &&
            (!v.harbor || d.harbor === v.harbor) &&
            (!v.price || inRange(Number(d.price), PRICES[v.price])) &&
            (!v.length || inRange(d.length === '' ? NaN : Number(d.length), LENGTHS[v.length])) &&
            chosen.every((ulid) => has(d.amenities, ulid))
        );
    };

    // Every link to a boat page carries the search, so its booking card opens with the experience, date, guests and
    // length already picked (the server renders the same links for the first view: FleetFilters::bookingQuery).
    const syncLinks = (v) => {
        const params = new URLSearchParams();
        if (v.date) params.set('date', v.date);
        if (v.guests !== DEFAULTS.guests) params.set('guests', v.guests);
        if (v.experience) params.set('experience', v.experience);
        if (v.hours !== DEFAULTS.hours) params.set('hours', v.hours);
        for (const link of grid.querySelectorAll('a[data-yacht-link]')) {
            const url = new URL(link.href, window.location.origin);
            url.search = params.toString();
            url.hash = link.dataset.yachtLink === 'book' ? 'book' : '';
            link.href = url.toString();
        }
    };

    const syncUrl = (v, chosen) => {
        const params = new URLSearchParams();
        for (const [key, value] of Object.entries(v)) if (value !== DEFAULTS[key]) params.set(key, value);
        for (const ulid of chosen) params.append('amenities[]', ulid);
        history.replaceState(null, '', params.toString() ? `?${params}` : window.location.pathname);
        return params.toString() !== '';
    };

    const apply = () => {
        const v = values();
        const chosen = amenities();
        let shown = 0;

        for (const card of cards) {
            const match = matches(card, v, chosen);
            card.hidden = !match;
            if (match) shown++;
        }
        [...cards].sort(SORTERS[v.sort] ?? SORTERS.popular).forEach((card) => grid.appendChild(card));

        count.textContent = String(shown);
        empty.classList.toggle('hidden', shown > 0);
        reset.classList.toggle('hidden', !syncUrl(v, chosen));
        syncLinks(v);

        for (const chip of chips.querySelectorAll('[data-type]')) {
            const on = chip.dataset.type === v.type;
            chip.className = on ? chips.dataset.chipOn : chips.dataset.chipOff;
            chip.setAttribute('aria-pressed', String(on));
        }
        const extra = [v.harbor, v.price, v.length].filter(Boolean).length + chosen.length;
        moreCount.textContent = String(extra);
        moreCount.classList.toggle('hidden', extra === 0);

        revealAll(grid);
    };

    // With a date, the server has to work out which boats are free: submit instead of filtering here.
    const changed = () => (field('date').value ? form.requestSubmit() : apply());

    chips.addEventListener('click', (event) => {
        const chip = event.target instanceof Element ? event.target.closest('[data-type]') : null;
        if (!chip) return;
        event.preventDefault();
        typeInput.value = chip.dataset.type;
        changed();
    });
    form.addEventListener('change', (event) => (event.target === field('date') ? form.requestSubmit() : changed()));
    form.addEventListener('submit', (event) => {
        if (field('date').value) {
            for (const field of form.elements) if (field.name && 'value' in field && field.value === (DEFAULTS[field.name] ?? null)) field.disabled = true; // a clean URL
            return;
        }
        event.preventDefault();
        apply();
    });
    moreToggle.addEventListener('click', () => {
        more.hidden = !more.hidden;
        moreToggle.setAttribute('aria-expanded', String(!more.hidden));
    });
    reset.addEventListener('click', (event) => {
        if (field('date').value) return; // the plain link reloads without a date
        event.preventDefault();
        for (const [key, value] of Object.entries(DEFAULTS)) {
            if (key === 'type') typeInput.value = value;
            else if (field(key)) field(key).value = value;
        }
        form.querySelectorAll('input[name="amenities[]"]').forEach((box) => (box.checked = false));
        apply();
    });
}
