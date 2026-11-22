/**
 * Profile & security:
 * - changing the email shows the "current password" box (the server requires it too);
 * - the "Delete my account" dialog (it opens again by itself after a wrong password);
 * - remove a saved card (asks first). PHASE 1 DEMO: the card is removed from the page only.
 *   Phase 5: DELETE /account/cards/{id} (Stripe detach).
 */
import { toast } from '../lib/toast.js';

export default function accountProfile() {
    /* ---------------- Email change needs the current password ---------------- */
    const email = document.getElementById('f-email');
    const passwordBox = document.querySelector('[data-email-password]');
    if (email && passwordBox) {
        const password = passwordBox.querySelector('input[type=password]');
        const sync = () => {
            const changing = email.value.trim().toLowerCase() !== email.dataset.currentEmail;
            passwordBox.classList.toggle('hidden', !changing);
            password.disabled = !changing;
            password.required = changing;
        };
        email.addEventListener('input', sync);
        sync();
    }

    /* ---------------- Delete account ---------------- */
    const dialog = document.getElementById('delete-dialog');
    if (dialog) {
        const open = () => {
            dialog.showModal();
            dialog.querySelector('input[type=password]').focus();
        };
        document.querySelector('[data-open-delete]')?.addEventListener('click', open);
        dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close(); // click on the backdrop
        });
        if (dialog.hasAttribute('data-open-on-load')) open();
    }

    /* ---------------- Saved cards (demo) ---------------- */
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
