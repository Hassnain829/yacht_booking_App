import { toast } from '../lib/toast.js';
import { setFieldError } from '../ui/field-errors.js';

/**
 * Contact form: friendly client-side checks (the server validates again — this is only UX).
 *
 * PHASE 1 DEMO: a valid form shows the success panel without sending anything.
 * Phase 9 replaces submitDemo() with http.post('/contact', …) + the server's 422 errors.
 */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const MESSAGES = {
    name: 'Please enter your name',
    email: 'Please enter a valid email',
    message: 'Please add a short message',
};

function check(field) {
    const value = field.value.trim();
    const bad = field.required && (!value || (field.type === 'email' && !EMAIL.test(value)));
    setFieldError(field, bad ? MESSAGES[field.name] : '');
    return !bad;
}

export default function contact() {
    const form = document.getElementById('contact-form');
    const success = document.getElementById('contact-success');
    if (!form || !success) return;

    const required = () => ['name', 'email', 'message'].map((name) => form.elements[name]);

    form.addEventListener(
        'blur',
        (event) => {
            if (event.target instanceof HTMLElement && event.target.required) check(event.target);
        },
        true,
    );

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const results = required().map(check);
        if (!results.every(Boolean)) {
            toast('Please fix the highlighted fields', 'error');
            required()[results.indexOf(false)].focus();
            return;
        }
        if (form.elements.website.value) return; // honeypot filled: silently ignore (bots)
        submitDemo(form, success);
    });

    document.getElementById('cs-again')?.addEventListener('click', () => {
        success.classList.add('hidden');
        form.classList.remove('hidden');
        form.elements.name.focus();
    });
}

function submitDemo(form, success) {
    const button = form.querySelector('button[type="submit"]');
    const label = button.querySelector('.btn-label');
    button.disabled = true;
    label.textContent = 'Sending…';
    setTimeout(() => {
        document.getElementById('cs-name').textContent = form.elements.name.value.trim().split(/\s+/)[0];
        form.classList.add('hidden');
        success.classList.remove('hidden');
        success.focus();
        button.disabled = false;
        label.textContent = 'Send message';
        form.reset();
    }, 900);
}
