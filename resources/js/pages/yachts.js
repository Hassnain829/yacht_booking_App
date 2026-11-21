import { revealAll } from '../ui/reveal.js';

/**
 * Fleet page: instant filtering/sorting of the server-rendered cards (no reload), with the
 * URL kept in sync so filtered views can be shared. The server applies the same filters to
 * the initial render (YachtController::index), so it also works without JS.
 */
const GUEST_MIN = { 6: 1, 13: 7, 49: 14, 80: 50 };
const DEFAULTS = { type: 'All', guests: '0', harbor: '', sort: 'popular' };
const SORTERS = {
    'price-asc': (a, b) => a.dataset.price - b.dataset.price,
    'price-desc': (a, b) => b.dataset.price - a.dataset.price,
    guests: (a, b) => b.dataset.guests - a.dataset.guests,
    popular: (a, b) => b.dataset.reviews - a.dataset.reviews,
};

export default function yachts() {
    const form = document.getElementById('fleet-filters');
    const grid = document.getElementById('yacht-grid');
    const chips = document.getElementById('type-chips');
    if (!form || !grid || !chips) return;

    const typeInput = form.querySelector('input[name="type"]');
    const count = document.getElementById('result-count');
    const empty = document.getElementById('empty-state');
    const reset = document.getElementById('reset-filters');
    const cards = Array.from(grid.querySelectorAll('article[data-type]'));

    const values = () => ({ type: typeInput.value, guests: form.guests.value, harbor: form.harbor.value, sort: form.sort.value });

    const apply = () => {
        const { type, guests, harbor, sort } = values();
        const minGuests = GUEST_MIN[guests] ?? 0;
        let shown = 0;

        for (const card of cards) {
            const d = card.dataset;
            const match = (type === 'All' || d.type === type) && (!harbor || d.harbor === harbor) && Number(d.guests) >= minGuests;
            card.hidden = !match;
            if (match) shown++;
        }
        [...cards].sort(SORTERS[sort] ?? SORTERS.popular).forEach((card) => grid.appendChild(card));

        count.textContent = String(shown);
        empty.classList.toggle('hidden', shown > 0);
        const filtered = Object.entries(values()).some(([key, value]) => value !== DEFAULTS[key]);
        reset.classList.toggle('hidden', !filtered);

        for (const chip of chips.querySelectorAll('[data-type]')) {
            const on = chip.dataset.type === type;
            chip.className = on ? chips.dataset.chipOn : chips.dataset.chipOff;
            chip.setAttribute('aria-pressed', String(on));
        }

        const params = new URLSearchParams();
        for (const [key, value] of Object.entries(values())) if (value !== DEFAULTS[key]) params.set(key, value);
        history.replaceState(null, '', params.toString() ? `?${params}` : window.location.pathname);

        revealAll(grid);
    };

    chips.addEventListener('click', (event) => {
        const chip = event.target instanceof Element ? event.target.closest('[data-type]') : null;
        if (!chip) return;
        event.preventDefault();
        typeInput.value = chip.dataset.type;
        apply();
    });
    form.addEventListener('change', apply);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        apply();
    });
    reset.addEventListener('click', (event) => {
        event.preventDefault();
        typeInput.value = DEFAULTS.type;
        form.guests.value = DEFAULTS.guests;
        form.harbor.value = DEFAULTS.harbor;
        form.sort.value = DEFAULTS.sort;
        apply();
    });
}
