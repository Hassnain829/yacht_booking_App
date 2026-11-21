/**
 * Scroll-snap carousels: [data-carousel] > [data-track] (+ optional [data-prev], [data-next],
 * [data-counter] "01 / 06", [data-progress] bar). Native scrolling, so touch/trackpad just work.
 */
export function initCarousels(root = document) {
    root.querySelectorAll('[data-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('[data-track]');
        if (!track || carousel.dataset.bound) return;
        carousel.dataset.bound = '1';

        const items = () => Array.from(track.children);
        const step = () => {
            const first = items()[0];
            return first ? first.getBoundingClientRect().width + (parseFloat(getComputedStyle(track).columnGap) || 24) : 300;
        };

        carousel.querySelector('[data-prev]')?.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
        carousel.querySelector('[data-next]')?.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));

        const counter = carousel.querySelector('[data-counter]');
        const progress = carousel.querySelector('[data-progress]');
        const update = () => {
            const count = items().length;
            const max = track.scrollWidth - track.clientWidth;
            const index = Math.min(Math.round(track.scrollLeft / step()) + 1, count);
            if (counter) counter.textContent = `${String(index).padStart(2, '0')} / ${String(count).padStart(2, '0')}`;
            if (progress) progress.style.width = `${max > 0 ? Math.max(12, (track.scrollLeft / max) * 100) : 100}%`;
        };
        track.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
        update();
    });
}
