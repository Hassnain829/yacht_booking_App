import { http } from '../lib/http.js';

/**
 * "Confirming your payment" (PLAN 4.5): asks the server every 2 seconds (it asks the provider) until the booking
 * is confirmed → the confirmation page, or the payment failed → back to checkout. After 30 seconds it says so.
 */
export default function checkoutComplete() {
    const box = document.querySelector('[data-checkout-complete]');
    if (!box || box.dataset.state !== 'processing') return;

    const started = Date.now();
    const poll = async () => {
        try {
            const { data } = await http.get(box.dataset.statusUrl);
            if (data.confirmation_url) {
                window.location.assign(data.confirmation_url);
                return;
            }
            if (data.payment_failure || data.status !== 'hold') {
                window.location.reload(); // the page shows the failure or the closed booking
                return;
            }
        } catch {
            /* a network blip: ask again */
        }
        if (Date.now() - started > 30000) box.querySelector('[data-complete-slow]')?.removeAttribute('hidden');
        setTimeout(poll, 2000);
    };
    setTimeout(poll, 1500);
}
