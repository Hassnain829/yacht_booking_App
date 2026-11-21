/**
 * Profile & security: remove a saved card (asks first).
 * PHASE 1 DEMO: the card is removed from the page only. Phase 5: DELETE /account/cards/{id} (Stripe detach).
 */
import { toast } from '../lib/toast.js';

export default function accountProfile() {
    document.getElementById('card-list')?.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-remove-card]') : null;
        if (!button) return;
        const item = button.closest('[data-card]');
        const label = item.querySelector('.font-bold').textContent.trim();
        if (!window.confirm(`Remove ${label}?`)) return;
        item.remove();
        toast('Card removed (demo — it comes back when the page reloads)');
    });
}
