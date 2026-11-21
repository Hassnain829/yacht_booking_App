/** Show / hide password buttons from <x-form.password> (aria-controls = the input id). */
export function initPasswordToggles() {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-toggle-password]') : null;
        if (!button) return;
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!(input instanceof HTMLInputElement)) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.querySelector('[data-icon-show]')?.classList.toggle('hidden', show);
        button.querySelector('[data-icon-hide]')?.classList.toggle('hidden', !show);
    });
}
