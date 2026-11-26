import { toast } from '../lib/toast.js';

/**
 * Confirmation page (4.6): copy the reference and print. "Add to calendar" is a plain link to the server's .ics
 * (the same file the confirmation email carries).
 */
export default function confirmation() {
    document.getElementById('copy-ref')?.addEventListener('click', async () => {
        const ref = document.getElementById('booking-ref').textContent.trim();
        try {
            await navigator.clipboard.writeText(ref);
            toast('Booking reference copied');
        } catch {
            toast(ref);
        }
    });

    document.getElementById('print-btn')?.addEventListener('click', () => window.print());
}
