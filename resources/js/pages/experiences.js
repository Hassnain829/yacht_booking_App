import { revealAll } from '../ui/reveal.js';

/**
 * Experiences page: instant category filter over the server-rendered rows. Keeps the
 * left/right alternation correct for the visible rows and the URL in sync (?category=…).
 */
export default function experiences() {
    const filter = document.getElementById('exp-filter');
    const list = document.getElementById('exp-list');
    if (!filter || !list) return;

    const rows = Array.from(list.querySelectorAll('article[data-cat]'));

    const apply = (category) => {
        let visibleIndex = 0;
        for (const row of rows) {
            const visible = category === 'all' || row.dataset.cat === category;
            row.hidden = !visible;
            if (!visible) continue;
            const flip = visibleIndex++ % 2 === 1;
            row.querySelector('[data-flip-media]').classList.toggle('lg:order-2', flip);
            const badge = row.querySelector('[data-flip-badge]');
            badge.classList.toggle('left-6', flip);
            badge.classList.toggle('right-6', !flip);
        }
        for (const link of filter.querySelectorAll('[data-cat]')) {
            const on = link.dataset.cat === category;
            link.className = on ? filter.dataset.chipOn : filter.dataset.chipOff;
            if (on) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }
        history.replaceState(null, '', category === 'all' ? window.location.pathname : `?category=${encodeURIComponent(category)}`);
        revealAll(list);
    };

    filter.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('[data-cat]') : null;
        if (!link) return;
        event.preventDefault();
        apply(link.dataset.cat);
    });
}
