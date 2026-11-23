/**
 * Admin → Boats: the "Bookable" switch (booking_enabled; off = the Unavailable tag on the site).
 * PATCH /admin/boats/{ulid}/bookable through lib/http.js (CSRF, JSON). The switch changes only after the server
 * agrees; on an error it stays as it was and a toast explains why.
 */
import { http } from '../lib/http.js';
import { toast } from '../lib/toast.js';

export default function adminBoats() {
    document.addEventListener('click', async (event) => {
        const toggle = event.target instanceof Element ? event.target.closest('[data-bookable-toggle]') : null;
        if (!toggle || toggle.getAttribute('aria-busy') === 'true') return;

        const on = toggle.getAttribute('aria-checked') !== 'true';
        toggle.setAttribute('aria-busy', 'true');
        try {
            const { data } = await http.patch(toggle.dataset.url, { booking_enabled: on });
            toggle.setAttribute('aria-checked', String(data.booking_enabled));
            toast(`${data.title} is now ${data.booking_enabled ? 'bookable' : 'shown as Unavailable'}.`);
        } catch (error) {
            toast(error.message || 'That did not work. Please try again.', 'error');
        } finally {
            toggle.removeAttribute('aria-busy');
        }
    });
}
