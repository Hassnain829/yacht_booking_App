/**
 * Admin shell: sidebar drawer on small screens, and <dialog> modals.
 *   <button data-open-modal="refund-modal"> … <dialog id="refund-modal"> … <button data-close-modal>
 */
export function initAdmin() {
    const sidebar = document.getElementById('admin-sidebar');
    const overlay = document.querySelector('[data-admin-overlay]');
    const toggle = document.querySelector('[data-admin-menu]');
    if (sidebar && toggle) {
        const setOpen = (open) => {
            sidebar.classList.toggle('is-open', open);
            overlay?.classList.toggle('hidden', !open);
            toggle.setAttribute('aria-expanded', String(open));
            document.documentElement.classList.toggle('overflow-hidden', open);
            if (open) setTimeout(() => sidebar.querySelector('a')?.focus(), 60);
        };
        toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
        overlay?.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const opener = target?.closest('[data-open-modal]');
        if (opener) {
            const dialog = document.getElementById(opener.dataset.openModal);
            if (dialog instanceof HTMLDialogElement) {
                dialog.showModal();
                dialog.querySelector('input:not([type=hidden]), select, textarea')?.focus();
            }
            return;
        }
        if (target?.closest('[data-close-modal]')) target.closest('dialog')?.close();
        if (target instanceof HTMLDialogElement) target.close(); // click on the backdrop
    });

    // A dialog whose form came back with errors opens again, so the admin sees them (x-admin.modal :open).
    const reopen = document.querySelector('dialog[data-open-on-load]');
    if (reopen instanceof HTMLDialogElement) reopen.showModal();

    // A demo form inside a modal closes the modal once it is valid (ui/demo-forms.js shows the toast).
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form instanceof HTMLFormElement && form.hasAttribute('data-demo-form') && form.closest('dialog') && form.checkValidity()) {
            setTimeout(() => form.closest('dialog')?.close(), 0);
        }
    });
}
