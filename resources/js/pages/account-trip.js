/**
 * Trip detail (PLAN 5.4): the "Cancel trip" dialog. The form posts to the server (account.trips.cancel): the time
 * is freed and the refund its policy gives waits for the team's approval. The dialog opens again by itself when
 * the server sends it back with an error. Reason is checked here for UX only — the server checks it again.
 */
import { setFieldError } from '../ui/field-errors.js';

export default function accountTrip() {
    const dialog = document.getElementById('cancel-dialog');
    if (!dialog) return;

    const form = document.getElementById('cancel-form');
    const reason = form.elements.reason;
    const open = () => {
        dialog.showModal();
        reason.focus();
    };
    document.querySelector('[data-open-cancel]')?.addEventListener('click', open);
    dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close(); // click on the backdrop
    });
    reason.addEventListener('change', () => setFieldError(reason, ''));

    let sent = false;
    form.addEventListener('submit', (event) => {
        if (!reason.value) {
            event.preventDefault();
            setFieldError(reason, 'Please choose a reason.');
            reason.focus();
            return;
        }
        if (sent) {
            event.preventDefault(); // one cancellation per click
            return;
        }
        sent = true;
        form.querySelector('button[type=submit]').disabled = true;
    });

    if (dialog.hasAttribute('data-open-on-load')) open();
}
