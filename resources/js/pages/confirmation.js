import { toast } from '../lib/toast.js';
import { downloadIcs } from '../lib/ics.js';

/** Confirmation page: copy the reference, print, and download an .ics calendar event. */
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

    const icsButton = document.getElementById('ics-btn');
    icsButton?.addEventListener('click', () => downloadIcs(JSON.parse(icsButton.dataset.ics)));
}
