import { toast } from '../lib/toast.js';

/**
 * Footer newsletter form.
 * PHASE 1 DEMO: validates the email and shows a toast; nothing is sent.
 * Phase 9 replaces this with http.post('/newsletter', …) (double opt-in, Turnstile, rate limit).
 */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export function initNewsletter(root = document) {
    root.querySelectorAll('form[data-newsletter]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const input = form.querySelector('input[type="email"]');
            const email = input?.value.trim() ?? '';
            if (!EMAIL.test(email) || email.length > 254) {
                toast('Please enter a valid email address.', 'error');
                input?.focus();
                return;
            }
            form.reset();
            toast("You're on the list! Watch your inbox for seasonal deals.");
        });
    });
}
