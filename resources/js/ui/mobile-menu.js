/**
 * Mobile navigation drawer: open/close, Escape key, focus moves in and back out,
 * page scroll locked while open, closes automatically on desktop widths.
 */
export function initMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    const openButton = document.getElementById('menu-open');
    if (!menu || !openButton) return;

    let returnFocusTo = null;

    const setOpen = (open) => {
        menu.classList.toggle('is-open', open);
        menu.setAttribute('aria-hidden', String(!open));
        menu.inert = !open;
        openButton.setAttribute('aria-expanded', String(open));
        document.documentElement.classList.toggle('overflow-hidden', open);
        if (open) {
            returnFocusTo = document.activeElement;
            // The drawer is still `visibility: hidden` until its transition starts; focus a moment later.
            setTimeout(() => menu.querySelector('[data-menu-focus]')?.focus(), 60);
        } else if (returnFocusTo instanceof HTMLElement) {
            returnFocusTo.focus();
            returnFocusTo = null;
        }
    };

    openButton.addEventListener('click', () => setOpen(true));
    menu.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-close]')) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.classList.contains('is-open')) setOpen(false);
    });
    window.matchMedia('(min-width: 80rem)').addEventListener('change', (query) => {
        if (query.matches && menu.classList.contains('is-open')) setOpen(false);
    });
}
