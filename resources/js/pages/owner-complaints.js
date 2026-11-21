/**
 * Complaints: the owner's response form (UX checks only).
 * PHASE 1 DEMO: the response is shown on the page but not sent.
 * Phase 7: PATCH /owner/complaints/{ulid} (ComplaintPolicy, FormRequest max 2000, named rate limiter).
 */
import { html, setHtml } from '../lib/dom.js';
import { toast } from '../lib/toast.js';
import { setFieldError } from '../ui/field-errors.js';

const MIN = 10;
const MAX = 2000;

export default function ownerComplaints() {
    for (const form of document.querySelectorAll('[data-response-form]')) {
        const textarea = form.querySelector('textarea');
        const counter = form.querySelector('[data-count]');

        textarea.addEventListener('input', () => {
            counter.textContent = String(MAX - textarea.value.length);
            setFieldError(textarea, '');
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const text = textarea.value.trim();
            if (text.length < MIN) {
                setFieldError(textarea, `Please write at least ${MIN} characters`);
                textarea.focus();
                return;
            }
            const box = document.createElement('div');
            box.className = 'rounded-2xl border border-navy-100 p-5';
            setHtml(box, html`
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-navy-400">Your response</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-navy-700">${text.slice(0, MAX)}</p>
                <p class="mt-3 text-xs font-semibold text-emerald-700">Sent to our team · just now</p>`);
            form.replaceWith(box);
            toast('Response sent to our team (demo: nothing was sent)');
        });
    }
}
