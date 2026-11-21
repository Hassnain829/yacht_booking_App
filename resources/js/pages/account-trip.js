/**
 * Trip detail: add to calendar, cancel dialog, report-a-problem form (UX checks only).
 * PHASE 1 DEMO: nothing is sent. Phase 4/7: POST /account/trips/{ulid}/cancel (password not needed,
 * idempotency key, refund from the policy snapshot) and POST /account/trips/{ulid}/complaints
 * (FormRequest, photos through MediaService on the private disk) — both via lib/http.js.
 */
import { html, setHtml } from '../lib/dom.js';
import { toast } from '../lib/toast.js';
import { downloadIcs } from '../lib/ics.js';
import { setFieldError } from '../ui/field-errors.js';

const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

export default function accountTrip() {
    const icsButton = document.getElementById('ics-btn');
    icsButton?.addEventListener('click', () => downloadIcs(JSON.parse(icsButton.dataset.ics)));

    /* ---------------- Cancel ---------------- */
    const dialog = document.getElementById('cancel-dialog');
    if (dialog) {
        const form = document.getElementById('cancel-form');
        const reason = form.elements.reason;
        document.querySelector('[data-open-cancel]')?.addEventListener('click', () => {
            dialog.showModal();
            reason.focus();
        });
        dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close(); // click on the backdrop
        });
        reason.addEventListener('change', () => setFieldError(reason, ''));
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!reason.value) {
                setFieldError(reason, 'Please choose a reason');
                return;
            }
            dialog.close();
            const state = document.querySelector('[data-cancel-state]');
            if (state) {
                state.className = 'mt-5 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200';
                setHtml(state, html`<p class="font-bold">Cancellation requested</p><p class="mt-1 text-xs">Demo only — in the live site your refund starts right away and you get an email.</p>`);
            }
            document.querySelector('[data-open-cancel]')?.remove();
            toast('Cancellation requested (demo: nothing was sent)');
        });
    }

    /* ---------------- Report a problem ---------------- */
    const report = document.getElementById('report-form');
    if (report) {
        const max = Number(report.dataset.maxPhotos) || 5;
        const { category, description } = report.elements;
        const photos = document.getElementById('report-photos');
        for (const field of [category, description, photos]) field.addEventListener('change', () => setFieldError(field, ''));
        description.addEventListener('input', () => setFieldError(description, ''));

        report.addEventListener('submit', (event) => {
            event.preventDefault();
            const files = [...photos.files];
            const checks = [
                [category, category.value ? '' : 'Please choose a category'],
                [description, description.value.trim().length >= 20 ? '' : 'Please describe what happened (at least 20 characters)'],
                [photos, files.length > max ? `Up to ${max} photos` : files.some((f) => !PHOTO_TYPES.includes(f.type) || f.size > MAX_PHOTO_BYTES) ? 'Photos must be JPG, PNG or WEBP, up to 10 MB each' : ''],
            ];
            for (const [field, message] of checks) setFieldError(field, message);
            const firstBad = checks.find(([, message]) => message);
            if (firstBad) {
                firstBad[0].focus();
                return;
            }
            const box = document.createElement('div');
            box.className = 'mt-5 rounded-2xl bg-emerald-50 p-5 text-sm text-emerald-900 ring-1 ring-emerald-200';
            setHtml(box, html`<p class="font-bold">Thanks — your report was sent</p><p class="mt-1">Our team replies within 1 business day. (Demo: nothing was sent.)</p>`);
            report.replaceWith(box);
            toast('Report sent (demo)');
        });
    }
}
