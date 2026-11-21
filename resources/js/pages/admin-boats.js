/**
 * Boats: the "Bookable" switch (booking_enabled). PHASE 1 DEMO: changes the switch only.
 * Phase 3: PATCH /admin/boats/{ulid}/availability via lib/http.js (BoatPolicy, audit log).
 */
import { toast } from '../lib/toast.js';

export default function adminBoats() {
    document.addEventListener('click', (event) => {
        const toggle = event.target instanceof Element ? event.target.closest('[data-booking-toggle]') : null;
        if (!toggle) return;
        const on = toggle.getAttribute('aria-checked') !== 'true';
        toggle.setAttribute('aria-checked', String(on));
        toast(`${toggle.dataset.bookingToggle} is now ${on ? 'bookable' : 'shown as Unavailable'} (demo)`);
    });
}
