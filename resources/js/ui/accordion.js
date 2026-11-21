/**
 * Accordions: [data-accordion="single|multi"] > .acc-item > .acc-trigger + .acc-panel
 * Closed panels are `inert`, so links inside them can't be tabbed to while hidden.
 */
function setItem(item, open) {
    item.classList.toggle('is-open', open);
    item.querySelector('.acc-trigger')?.setAttribute('aria-expanded', String(open));
    const panel = item.querySelector('.acc-panel');
    if (panel) panel.inert = !open;
}

export function initAccordions(root = document) {
    root.querySelectorAll('[data-accordion]').forEach((accordion) => {
        if (accordion.dataset.bound) return;
        accordion.dataset.bound = '1';

        accordion.addEventListener('click', (event) => {
            const trigger = event.target instanceof Element ? event.target.closest('.acc-trigger') : null;
            if (!trigger || !accordion.contains(trigger)) return;

            const item = trigger.closest('.acc-item');
            const willOpen = !item.classList.contains('is-open');
            if (accordion.dataset.accordion !== 'multi') {
                accordion.querySelectorAll('.acc-item.is-open').forEach((other) => other !== item && setItem(other, false));
            }
            setItem(item, willOpen);
        });
    });
}
