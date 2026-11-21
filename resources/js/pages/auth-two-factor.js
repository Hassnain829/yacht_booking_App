/** 2FA challenge: switch between the 6-digit code and a recovery code (only the visible field is enabled/posted). */
export default function authTwoFactor() {
    const button = document.querySelector('[data-tfa-switch]');
    if (!button) return;
    let mode = 'code';
    button.addEventListener('click', () => {
        mode = mode === 'code' ? 'recovery' : 'code';
        for (const el of document.querySelectorAll('[data-tfa-field], [data-tfa-text]')) {
            const on = (el.dataset.tfaField ?? el.dataset.tfaText) === mode;
            el.classList.toggle('hidden', !on);
            const input = el.querySelector('input');
            if (input) {
                input.disabled = !on;
                if (on) input.focus();
            }
        }
        button.textContent = mode === 'code' ? 'Use a recovery code instead' : 'Use your authenticator code instead';
    });
}
