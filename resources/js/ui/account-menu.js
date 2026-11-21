/**
 * Header account dropdown: opens on click, closes on Escape (focus returns to the button),
 * on a click outside, or when keyboard focus leaves the menu.
 */
export function initAccountMenu() {
    const root = document.querySelector('[data-account-menu]');
    const button = root?.querySelector('[data-account-toggle]');
    const panel = root?.querySelector('#account-panel');
    if (!root || !button || !panel) return;

    const setOpen = (open, { focusButton = false } = {}) => {
        panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        if (open) panel.querySelector('a, button')?.focus({ preventScroll: true });
        else if (focusButton) button.focus();
    };

    button.addEventListener('click', () => setOpen(panel.hidden));
    document.addEventListener('click', (event) => {
        if (!panel.hidden && event.target instanceof Node && !root.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false, { focusButton: true });
    });
    root.addEventListener('focusout', (event) => {
        if (!panel.hidden && event.relatedTarget instanceof Node && !root.contains(event.relatedTarget)) setOpen(false);
    });
}
