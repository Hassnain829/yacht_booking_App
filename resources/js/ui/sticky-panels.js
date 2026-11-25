/**
 * Sticky side panels on large screens (lg+) never end below the screen (found in the user's QA, 2026-11-26: the
 * boat page's booking card was taller than the screen, so the mouse wheel over it scrolled the page and its lower
 * part was out of reach). A panel marked `data-sticky-panel` gets a max height = the screen space below its top;
 * a taller panel scrolls on its own — the wheel over it scrolls the panel first, then the page. Its classes carry
 * `overflow-y: auto`, `no-scrollbar` (no visible bar, user 2026-11-26) and a CSS max height for before this runs.
 * Below lg the panels are ordinary blocks.
 *
 * `data-sticky-panel="release"` (a decorative block) stops sticking instead when it doesn't fit, rather than
 * becoming a scroll box. Styles are set through CSSOM, which the CSP allows (inline style attributes it blocks).
 *
 * Used by the boat page's booking card, the checkout summary, a trip's side column, the owner calendar's day
 * panel, the legal pages' contents, the pricing estimator, the home page's occasions block and the admin record
 * forms' right-hand column (x-admin.form-aside).
 */
const WIDE = '(min-width: 64rem)';
const GAP = 16; // px kept free under a panel

export function initStickyPanels(root = document) {
    const panels = [...root.querySelectorAll('[data-sticky-panel]')].filter((panel) => panel instanceof HTMLElement);
    if (panels.length === 0) return;

    const wide = window.matchMedia(WIDE);
    let frame = 0;

    const fit = () => {
        frame = 0;
        for (const panel of panels) {
            if (!wide.matches) {
                panel.style.removeProperty('max-height');
                panel.style.removeProperty('position');
                continue;
            }
            const stuckAt = Number.parseFloat(getComputedStyle(panel).top) || 0;
            if (panel.dataset.stickyPanel === 'release') {
                panel.style.position = panel.offsetHeight > window.innerHeight - stuckAt - GAP ? 'static' : '';
                continue;
            }
            // Before it sticks (top of the page) the panel sits lower: it fits the space below where it is now.
            const top = Math.max(panel.getBoundingClientRect().top, stuckAt);
            panel.style.maxHeight = `${Math.max(240, Math.floor(window.innerHeight - top - GAP))}px`;
        }
    };
    const queue = () => {
        if (!frame) frame = requestAnimationFrame(fit);
    };

    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
    wide.addEventListener('change', queue);
    // A decorative block grows when its image loads.
    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(queue);
        panels.filter((panel) => panel.dataset.stickyPanel === 'release').forEach((panel) => observer.observe(panel));
    }
    fit();
}
