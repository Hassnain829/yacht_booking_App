/**
 * Show / clear an inline validation message under a form field (client-side UX only;
 * the server validates again). Sets aria-invalid + aria-describedby for screen readers.
 */
export function setFieldError(field, message) {
    const id = `${field.id || field.name}-error`;
    let error = document.getElementById(id);
    const bad = Boolean(message);

    if (field.type === 'checkbox') {
        field.toggleAttribute('aria-invalid', bad);
    } else {
        field.toggleAttribute('aria-invalid', bad);
        field.classList.toggle('border-red-500', bad);
        field.classList.toggle('border-navy-200', !bad);
    }

    if (!bad) {
        error?.remove();
        field.removeAttribute('aria-describedby');
        return;
    }
    if (!error) {
        error = document.createElement('p');
        error.id = id;
        error.className = 'mt-1.5 text-xs font-semibold text-red-600';
        // <x-form.input> wraps inputs in a `relative` div; checkboxes sit inside their <label>.
        const anchor = field.type === 'checkbox' ? field.closest('label') : field.parentElement?.classList.contains('relative') ? field.parentElement : field;
        anchor.after(error);
    }
    error.textContent = message;
    field.setAttribute('aria-describedby', id);
}
