/**
 * 2FA challenge: switch between the 6-digit code and a recovery code (only the visible field is
 * enabled, so only it is posted). The page may start in either mode (a wrong recovery code comes
 * back in recovery mode), so the starting mode is read from the markup.
 */
export default function authTwoFactor() {
    const button = document.querySelector('[data-tfa-switch]');
    if (!button) return;
    const recoveryField = document.querySelector('[data-tfa-field="recovery"]');
    let mode = recoveryField && !recoveryField.classList.contains('hidden') ? 'recovery' : 'code';

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
