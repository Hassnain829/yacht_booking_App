/**
 * Payouts: withdrawal request form (UX checks only).
 * PHASE 1 DEMO: nothing is sent — the request is shown in the history list.
 * Phase 7: POST /owner/payouts through lib/http.js with an idempotency key; the server checks
 * the available balance, the $50 minimum, Stripe Connect, 2FA, password confirmation and paused payouts.
 */
import { html, setHtml } from '../lib/dom.js';
import { toast } from '../lib/toast.js';
import { formatCents } from '../lib/money.js';
import { dollarsToCents } from '../lib/owner-estimate.js';
import { setFieldError } from '../ui/field-errors.js';

export default function ownerPayouts() {
    const form = document.getElementById('withdraw-form');
    if (!form) return;

    const cfg = JSON.parse(form.dataset.withdraw);
    const input = document.getElementById('withdraw-amount');
    const history = document.getElementById('payout-history');
    let available = cfg.available;

    form.querySelector('[data-withdraw-all]')?.addEventListener('click', () => {
        input.value = (available / 100).toFixed(2);
        setFieldError(input, '');
        input.focus();
    });

    input.addEventListener('input', () => setFieldError(input, ''));

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!cfg.enabled) {
            toast('Withdrawals are not available right now', 'error');
            return;
        }
        const cents = dollarsToCents(input.value);
        let message = '';
        if (cents === null) message = 'Enter an amount like 250 or 250.50';
        else if (cents < cfg.min) message = `The minimum withdrawal is ${formatCents(cfg.min, { cents: 'never' })}`;
        else if (cents > available) message = `You can withdraw up to ${formatCents(available, { cents: 'always' })}`;
        setFieldError(input, message);
        if (message) {
            input.focus();
            return;
        }

        available -= cents;
        document.getElementById('available-amount').textContent = formatCents(available, { cents: 'always' });
        input.placeholder = (available / 100).toFixed(2);
        input.value = '';

        const row = document.createElement('li');
        row.className = 'grid gap-x-4 gap-y-1 px-6 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center md:px-7';
        setHtml(row, html`
            <div class="min-w-0">
                <p class="text-sm font-bold text-navy-950">${formatCents(cents, { cents: 'always' })} <span class="font-semibold text-navy-400">· New request</span></p>
                <p class="mt-0.5 text-xs text-navy-500">Requested ${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} · ${cfg.method ?? 'Bank transfer'}</p>
            </div>
            <span class="justify-self-start rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800 sm:justify-self-end">Requested</span>`);
        history.querySelector('[data-empty]')?.remove();
        history.prepend(row);

        if (available < cfg.min) {
            for (const el of form.querySelectorAll('input, button')) el.disabled = true;
        }
        toast('Withdrawal requested — our team will review it (demo: nothing was sent)');
    });
}
