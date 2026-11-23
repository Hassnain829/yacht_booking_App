import { toast } from '../lib/toast.js';

/**
 * Admin shell: sidebar drawer on small screens, <dialog> modals, and the jump to the first error after a save.
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
    else revealFirstError();

    // A demo form inside a modal closes the modal once it is valid (ui/demo-forms.js shows the toast).
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form instanceof HTMLFormElement && form.hasAttribute('data-demo-form') && form.closest('dialog') && form.checkValidity()) {
            setTimeout(() => form.closest('dialog')?.close(), 0);
        }
    });

    guardUnsavedChanges();
    fitFormAside();
}

/**
 * lg+: the record form's right-hand column (x-admin.form-aside) is never taller than the screen space below its
 * top. Its CSS max height assumes it is already stuck under the top bar; before that (top of the page) the
 * column sits lower and its Save buttons would start below the fold. Set through CSSOM (allowed by the CSP).
 */
function fitFormAside() {
    const aside = document.querySelector('[data-form-aside]');
    if (!(aside instanceof HTMLElement)) return;

    const wide = window.matchMedia('(min-width: 64rem)');
    let frame = 0;
    const fit = () => {
        frame = 0;
        if (!wide.matches) {
            aside.style.removeProperty('max-height');
            return;
        }
        const stuckAt = Number.parseFloat(getComputedStyle(aside).top) || 0;
        const top = Math.max(aside.getBoundingClientRect().top, stuckAt);
        aside.style.maxHeight = `${Math.max(240, Math.floor(window.innerHeight - top - 16))}px`;
    };
    const queue = () => {
        if (!frame) frame = requestAnimationFrame(fit);
    };
    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
    fit();
}

/**
 * A record form (the one with the Save buttons) that has unsaved edits asks before the page goes away.
 * That covers a link, Cancel, another form on the page (a photo button, a block, Publish, Trash) and closing the
 * tab. Saving the form itself never asks. A form that came back with errors starts as unsaved: what is typed
 * there isn't saved yet. Controls that belong to another form (form="…", e.g. a photo's description) don't count.
 */
function guardUnsavedChanges() {
    const form = document.querySelector('[data-form-actions], [data-form-bar]')?.closest('form');
    if (!(form instanceof HTMLFormElement)) return;

    // Errors of the form's own fields only: a failed block add shows its errors inside the boat form's area too.
    let unsaved = [...form.querySelectorAll('[data-field-error]')].some((error) => {
        const field = error instanceof HTMLElement ? fieldFor(error) : null;
        return (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) && field.form === form;
    });
    const mark = (event) => {
        const field = event.target;
        if ((field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) && field.form === form) {
            unsaved = true;
        }
    };
    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    form.addEventListener('submit', () => {
        unsaved = false;
    });
    window.addEventListener('beforeunload', (event) => {
        if (!unsaved) return;
        event.preventDefault(); // the browser asks "Leave site? Changes you made may not be saved."
        event.returnValue = ''; // older Safari / Chrome
    });
}

/**
 * A form that failed validation comes back as a new page scrolled to the top, so the admin can't see what went
 * wrong on a long form. Bring the first error message ([data-field-error], set by the x-form components and the
 * custom field groups) to the middle of the screen, put the cursor in its field and say how many need a fix.
 */
function revealFirstError() {
    const errors = document.querySelectorAll('#main [data-field-error]');
    const first = errors[0];
    if (!(first instanceof HTMLElement)) return;

    first.scrollIntoView({ block: 'center' });
    const field = fieldFor(first);
    if (field) field.focus({ preventScroll: true });

    toast(errors.length > 1 ? `${errors.length} fields need a fix. The first one is highlighted.` : 'Please fix the highlighted field.', 'error', { duration: 5000 });
}

/**
 * The control an error message belongs to: the one that names it in aria-describedby (x-form components), else the
 * first control in the same field group (durations, hours, features, refund rules).
 *
 * @param {HTMLElement} error
 * @returns {HTMLElement|null}
 */
function fieldFor(error) {
    const controls = 'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])';
    if (error.id) {
        const described = document.querySelector(`[aria-describedby~="${CSS.escape(error.id)}"]`);
        if (described instanceof HTMLElement) return described;
    }
    let group = error.parentElement;
    for (let depth = 0; group && depth < 3 && !group.matches('form, main'); depth += 1, group = group.parentElement) {
        const control = group.querySelector(controls);
        if (control instanceof HTMLElement) return control;
    }

    return null;
}
