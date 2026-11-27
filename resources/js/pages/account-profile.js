/**
 * Profile & security:
 * - changing the email shows the "current password" box (the server requires it too);
 * - the "Delete my account" dialog (it opens again by itself after a wrong password);
 * - saved cards (5.3): "Remove" asks first; "Add a card" saves one — with Stripe, the Payment Element in setup
 *   mode (the server creates the SetupIntent and, after the browser confirms it, asks Stripe whether the card was
 *   saved); with the fake gateway (local / tests), a test card whose brand, last 4 and expiry only are posted.
 */
import { http, HttpError, newIdempotencyKey } from '../lib/http.js';

const digits = (value) => String(value).replace(/\D/g, '');
const brandOf = (number) => {
    if (/^4/.test(number)) return 'Visa';
    if (/^(5[1-5]|2[2-7])/.test(number)) return 'Mastercard';
    if (/^3[47]/.test(number)) return 'Amex';
    if (/^6(011|5)/.test(number)) return 'Discover';
    return 'Card';
};
const luhn = (number) => {
    let sum = 0;
    let double = false;
    for (let i = number.length - 1; i >= 0; i--) {
        let n = Number(number[i]);
        if (double) {
            n *= 2;
            if (n > 9) n -= 9;
        }
        sum += n;
        double = !double;
    }
    return number.length >= 13 && sum % 10 === 0;
};

export default function accountProfile() {
    /* ---------------- Email change needs the current password ---------------- */
    const email = document.getElementById('f-email');
    const passwordBox = document.querySelector('[data-email-password]');
    if (email && passwordBox) {
        const password = passwordBox.querySelector('input[type=password]');
        const sync = () => {
            const changing = email.value.trim().toLowerCase() !== email.dataset.currentEmail;
            passwordBox.classList.toggle('hidden', !changing);
            password.disabled = !changing;
            password.required = changing;
        };
        email.addEventListener('input', sync);
        sync();
    }

    /* ---------------- Delete account ---------------- */
    const dialog = document.getElementById('delete-dialog');
    if (dialog) {
        const open = () => {
            dialog.showModal();
            dialog.querySelector('input[type=password]').focus();
        };
        document.querySelector('[data-open-delete]')?.addEventListener('click', open);
        dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close(); // click on the backdrop
        });
        if (dialog.hasAttribute('data-open-on-load')) open();
    }

    /* ---------------- Saved cards ---------------- */
    for (const removeForm of document.querySelectorAll('form[data-remove-card]')) {
        removeForm.addEventListener('submit', (event) => {
            if (!window.confirm(`Remove ${removeForm.dataset.removeCard}?`)) event.preventDefault();
        });
    }

    const cardDialog = document.getElementById('card-dialog');
    const cardForm = document.getElementById('card-form');
    if (!cardDialog || !cardForm) return;
    const setup = JSON.parse(cardForm.dataset.cardSetup);
    const problem = cardForm.querySelector('[data-card-dialog-error]');
    const saveButton = cardForm.querySelector('[data-save-card]');
    const showProblem = (message) => {
        problem.textContent = message ?? '';
        problem.classList.toggle('hidden', !message);
    };
    const failure = (error) => (error instanceof HttpError ? Object.values(error.errors)[0]?.[0] ?? error.message : error?.message ?? 'Something went wrong. Please try again.');

    let stripe = null;
    let elements = null;
    const mountStripe = () => {
        if (elements || setup.provider !== 'stripe') return;
        if (typeof window.Stripe !== 'function' || !setup.publishableKey) {
            showProblem('The card form could not load. Please refresh the page.');
            return;
        }
        stripe = window.Stripe(setup.publishableKey);
        elements = stripe.elements({ mode: 'setup', currency: setup.currency, paymentMethodTypes: ['card'], appearance: { theme: 'stripe', variables: { colorPrimary: '#0a1a2f', borderRadius: '12px' } } });
        elements.create('payment').mount('#card-element');
    };

    document.querySelector('[data-open-add-card]')?.addEventListener('click', () => {
        showProblem(null);
        cardDialog.showModal();
        mountStripe();
    });
    cardDialog.querySelector('[data-close-dialog]').addEventListener('click', () => cardDialog.close());
    cardDialog.addEventListener('click', (event) => {
        if (event.target === cardDialog) cardDialog.close();
    });

    const test = cardForm.querySelector('[data-test-card]')
        ? { number: cardForm.querySelector('[data-card="number"]'), exp: cardForm.querySelector('[data-card="exp"]'), cvc: cardForm.querySelector('[data-card="cvc"]') }
        : null;
    if (test) {
        test.number.addEventListener('input', () => (test.number.value = digits(test.number.value).slice(0, 16).replace(/(.{4})/g, '$1 ').trim()));
        test.exp.addEventListener('input', (event) => {
            let v = digits(test.exp.value).slice(0, 4);
            if (v.length >= 3) v = `${v.slice(0, 2)} / ${v.slice(2)}`;
            else if (v.length === 2 && event.inputType !== 'deleteContentBackward') v = `${v} / `;
            test.exp.value = v;
        });
        test.cvc.addEventListener('input', () => (test.cvc.value = digits(test.cvc.value).slice(0, 4)));
        cardForm.querySelector('[data-fill-test-card]')?.addEventListener('click', () => {
            test.number.value = '4242 4242 4242 4242';
            test.exp.value = `12 / ${String((new Date().getFullYear() + 2) % 100).padStart(2, '0')}`;
            test.cvc.value = '123';
        });
    }

    let busy = false;
    cardForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        showProblem(null);
        busy = true;
        saveButton.disabled = true;
        try {
            if (setup.provider === 'stripe') {
                if (!elements) throw new Error('The card form could not load. Please refresh the page.');
                const { error: incomplete } = await elements.submit();
                if (incomplete) throw new Error(incomplete.message);
                const { data: start } = await http.post(setup.setupUrl, {}, { idempotencyKey: newIdempotencyKey() });
                const { error, setupIntent } = await stripe.confirmSetup({ elements, clientSecret: start.client_secret, confirmParams: { return_url: start.return_url }, redirect: 'if_required' });
                if (error) throw new Error(error.message ?? 'Your card was not saved. Please try again.');
                await http.post(start.store_url, { setup_intent: setupIntent.id }, { idempotencyKey: newIdempotencyKey() });
            } else {
                const number = digits(test.number.value);
                const exp = test.exp.value.match(/^(\d{2})\s*\/\s*(\d{2})$/);
                if (!luhn(number)) throw new Error('Enter a valid card number.');
                if (!exp || Number(exp[1]) < 1 || Number(exp[1]) > 12) throw new Error('Enter the expiry as MM / YY.');
                if (!/^\d{3,4}$/.test(test.cvc.value)) throw new Error('Enter the 3–4 digit CVC.');
                const { data: start } = await http.post(setup.setupUrl, {}, { idempotencyKey: newIdempotencyKey() });
                await http.post(start.test_card_url, { brand: brandOf(number), last4: number.slice(-4), exp_month: Number(exp[1]), exp_year: 2000 + Number(exp[2]) }, { idempotencyKey: newIdempotencyKey() });
            }
            window.location.reload(); // the page shows "Your card is saved." and the list
        } catch (error) {
            showProblem(failure(error));
            busy = false;
            saveButton.disabled = false;
        }
    });
}
