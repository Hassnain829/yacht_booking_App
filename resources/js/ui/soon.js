import { toast } from '../lib/toast.js';

/** Links and buttons for sections that arrive in a later phase: <a href="#" data-soon>. */
export function initSoon() {
    document.addEventListener('click', (event) => {
        const el = event.target instanceof Element ? event.target.closest('[data-soon]') : null;
        if (!el) return;
        event.preventDefault();
        toast('Coming soon — this section opens in a later release.');
    });
}
