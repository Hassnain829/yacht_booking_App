import { html, setHtml } from '../lib/dom.js';
import { http, HttpError, newIdempotencyKey } from '../lib/http.js';
import { toast } from '../lib/toast.js';
import { formatCents } from '../lib/money.js';
import { renderIcons } from '../lib/icons.js';
import { setFieldError } from '../ui/field-errors.js';

/**
 * Checkout of a held booking (PLAN 4.5, D22: paid in full). The browser never adds up a price: add-ons and the
 * gratuity ask GET …/price (QuoteService), a promo code is applied on the server (POST …/promo). "Pay" sends the
 * final choices (POST …/pay → the payment for the total) and then:
 *  - Stripe: confirms the card in Stripe's Payment Element (card data stays in Stripe's frames) and goes to the
 *    return page, where the server asks Stripe — never trusting the browser — and confirms the booking;
 *  - the fake gateway (local / tests): sends only the test card's brand, last 4, expiry and decline flag.
 * Since 5.3 the guest can pay with a saved card (Stripe confirms with that card) or tick "Save this card for next
 * time" (Stripe's setup_future_usage, matched on the Payment Element). The hold timer counts down to the server's hold expiry; then the page reloads into "This hold has ended".
 */
const digits = (value) => String(value).replace(/\D/g, '');
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const DECLINE_CARD = '4000000000000002';

function luhn(number) {
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
}

function cardBrand(number) {
    if (/^4/.test(number)) return 'Visa';
    if (/^(5[1-5]|2[2-7])/.test(number)) return 'Mastercard';
    if (/^3[47]/.test(number)) return 'Amex';
    if (/^6(011|5)/.test(number)) return 'Discover';
    return 'Card';
}

export default function checkout() {
    const form = document.getElementById('checkout-form');
    if (!form) return;

    const data = JSON.parse(form.dataset.checkout);
    const F = form.elements;
    const payButton = document.getElementById('pay-btn');
    const pageError = form.querySelector('[data-checkout-error]');
    let amountCents = data.amountCents;
    let priceTimer = null;
    let priceAbort = null;
    let paying = false;

    const showPageError = (message) => {
        pageError.textContent = message ?? '';
        pageError.hidden = !message;
        if (message) pageError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    const failure = (error) => (error instanceof HttpError ? Object.values(error.errors)[0]?.[0] ?? error.message : 'Something went wrong. Please try again.');
    const chosenAddons = () => Array.from(form.querySelectorAll('input[name="addons[]"]:checked')).map((box) => box.value);
    const savedCard = () => form.querySelector('input[name="card"]:checked:not([value=""])');
    const saveBox = F.save_card;
    const gratuity = () => Number(form.querySelector('input[name="gratuity"]:checked')?.value ?? 0);

    /* ---------------- Stripe's Payment Element (only with the Stripe driver) ---------------- */
    let stripe = null;
    let elements = null;
    if (data.provider === 'stripe') {
        if (typeof window.Stripe !== 'function' || !data.publishableKey) {
            showPageError('The payment form could not load. Please refresh the page.');
        } else {
            stripe = window.Stripe(data.publishableKey);
            elements = stripe.elements({
                mode: 'payment',
                amount: amountCents,
                currency: data.currency,
                appearance: { theme: 'stripe', variables: { colorPrimary: '#0a1a2f', borderRadius: '12px' } },
            });
            elements.create('payment', { layout: 'tabs' }).mount('#payment-element');
        }
    }
    // "Save this card": the Payment Element must ask for the same setup_future_usage as the intent.
    const syncSave = () => elements?.update({ setupFutureUsage: !savedCard() && saveBox?.checked ? 'off_session' : null });
    saveBox?.addEventListener('change', syncSave);

    /* ---------------- Saved cards (5.3): a saved card hides the new card's fields ---------------- */
    const newCard = form.querySelector('[data-new-card]');
    form.querySelector('[data-saved-cards]')?.addEventListener('change', () => {
        newCard.classList.toggle('hidden', Boolean(savedCard()));
        form.querySelector('[data-card-error]')?.classList.add('hidden');
        syncSave();
    });

    /* ---------------- Price (QuoteService through the price endpoint) ---------------- */
    const payText = () => `Pay ${formatCents(amountCents)} & confirm booking`;

    function paintPrice(q) {
        const row = (line) =>
            html`<div class="flex justify-between gap-4"><dt class="text-navy-600">${line.label}<span class="block text-[11px] text-navy-400">${line.description}</span></dt><dd class="font-semibold ${line.amount_cents < 0 ? 'text-emerald-700' : 'text-navy-900'}">${line.amount_cents < 0 ? `−${formatCents(-line.amount_cents)}` : formatCents(line.amount_cents)}</dd></div>`;
        setHtml(document.getElementById('checkout-lines'), html`${[...q.lines, ...q.adjustments].map(row)}`);
        amountCents = q.total_cents;
        document.getElementById('checkout-total').textContent = formatCents(q.total_cents);
        document.getElementById('checkout-due').textContent = formatCents(q.total_cents);
        const label = document.getElementById('pay-label');
        if (label) label.textContent = payText();
        const note = document.querySelector('[data-promo-note]');
        note.textContent = q.promo_rejection ?? '';
        note.hidden = !q.promo_rejection;
        elements?.update({ amount: q.total_cents });
    }

    function refreshPrice() {
        clearTimeout(priceTimer);
        priceTimer = setTimeout(async () => {
            priceAbort?.abort();
            priceAbort = new AbortController();
            try {
                const { data: q } = await http.get(data.urls.price, { params: { addons: chosenAddons(), gratuity: gratuity() }, signal: priceAbort.signal });
                paintPrice(q);
                showPageError(null);
            } catch (error) {
                if (priceAbort.signal.aborted) return;
                if (error instanceof HttpError && error.first('hold')) window.location.reload();
                else showPageError(failure(error));
            }
        }, 150);
    }

    form.addEventListener('change', (event) => {
        if (event.target.name === 'gratuity' || event.target.name === 'addons[]') refreshPrice();
    });

    /* ---------------- Promo code (checked and applied on the server) ---------------- */
    const promoBox = document.getElementById('promo-box');
    const promoInput = document.getElementById('promo-input');
    const promoError = promoBox.querySelector('[data-promo-error]');
    const showPromo = (promo) => {
        promoBox.querySelector('[data-promo-form]').classList.toggle('hidden', Boolean(promo));
        const applied = promoBox.querySelector('[data-promo-applied]');
        applied.classList.toggle('hidden', !promo);
        applied.classList.toggle('flex', Boolean(promo));
        promoBox.querySelector('[data-promo-label]').textContent = promo ? `${promo.code} · ${promo.label}` : '';
        promoError.hidden = true;
    };

    async function applyPromo() {
        const code = promoInput.value.trim().toUpperCase();
        if (!code) return;
        try {
            const { data: promo } = await http.post(data.urls.promo, { code });
            showPromo(promo);
            refreshPrice();
            toast(`Promo ${promo.code} applied — ${promo.label}`);
        } catch (error) {
            promoError.textContent = failure(error);
            promoError.hidden = false;
        }
    }

    promoBox.addEventListener('click', async (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (target?.closest('[data-apply-promo]')) applyPromo();
        if (target?.closest('[data-remove-promo]')) {
            try {
                await http.delete(data.urls.promo);
                promoInput.value = '';
                showPromo(null);
                refreshPrice();
            } catch (error) {
                showPageError(failure(error));
            }
        }
    });
    promoInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyPromo();
        }
    });

    /* ---------------- Test card (fake gateway only) ---------------- */
    const card = form.querySelector('[data-test-card]')
        ? { number: form.querySelector('[data-card="number"]'), exp: form.querySelector('[data-card="exp"]'), cvc: form.querySelector('[data-card="cvc"]'), zip: form.querySelector('[data-card="zip"]') }
        : null;
    const paintBrand = () => {
        const brand = cardBrand(digits(card.number.value));
        const badge = document.getElementById('card-brand');
        badge.textContent = brand;
        badge.className = `absolute right-3 top-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider ${brand === 'Card' ? 'bg-navy-50 text-navy-400' : 'bg-navy-950 text-white'}`;
        return brand;
    };

    /* ---------------- Validation (UX only — the server validates again) ---------------- */
    const allRules = [
        [F.first_name, (v) => v.trim().length > 0, 'Required'],
        [F.last_name, (v) => v.trim().length > 0, 'Required'],
        [F.email, (v) => EMAIL.test(v.trim()), 'Enter a valid email'],
        [F.phone, (v) => digits(v).length >= 7, 'Enter a valid phone number'],
        ...(card
            ? [
                  [card.number, (v) => luhn(digits(v)), 'Enter a valid card number'],
                  [
                      card.exp,
                      (v) => {
                          const m = v.match(/^(\d{2})\s*\/\s*(\d{2})$/);
                          if (!m) return false;
                          const month = Number(m[1]);
                          return month >= 1 && month <= 12 && new Date(2000 + Number(m[2]), month, 0, 23, 59) >= new Date();
                      },
                      'Invalid date',
                  ],
                  [card.cvc, (v) => /^\d{3,4}$/.test(v), '3–4 digits'],
                  [card.zip, (v) => digits(v).length >= 5, 'Required'],
              ]
            : []),
        [F.agree, (_, el) => el.checked, 'Please accept to continue'],
    ];
    const cardFields = card ? Object.values(card) : [];
    const rules = () => (savedCard() ? allRules.filter(([field]) => !cardFields.includes(field)) : allRules);
    const check = ([field, ok, message]) => {
        const valid = ok(field.value, field);
        setFieldError(field, valid ? '' : message);
        return valid;
    };
    form.addEventListener('blur', (event) => {
        const rule = rules().find(([field]) => field === event.target);
        if (rule && event.target.value) check(rule);
    }, true);
    F.agree.addEventListener('change', () => check(allRules.find(([field]) => field === F.agree)));

    if (card) {
        card.number.addEventListener('input', () => {
            card.number.value = digits(card.number.value).slice(0, 16).replace(/(.{4})/g, '$1 ').trim();
            paintBrand();
        });
        card.exp.addEventListener('input', (event) => {
            let v = digits(card.exp.value).slice(0, 4);
            if (v.length >= 3) v = `${v.slice(0, 2)} / ${v.slice(2)}`;
            else if (v.length === 2 && event.inputType !== 'deleteContentBackward') v = `${v} / `;
            card.exp.value = v;
        });
        card.cvc.addEventListener('input', () => (card.cvc.value = digits(card.cvc.value).slice(0, 4)));
        document.getElementById('fill-demo')?.addEventListener('click', () => {
            card.number.value = '4242 4242 4242 4242';
            card.exp.value = `12 / ${String((new Date().getFullYear() + 2) % 100).padStart(2, '0')}`;
            card.cvc.value = '123';
            card.zip.value = '60601';
            paintBrand();
            allRules.filter(([field]) => cardFields.includes(field)).forEach(check);
        });
    }

    /* ---------------- Pay ---------------- */
    const setPaying = (on) => {
        paying = on;
        payButton.disabled = on;
        if (on) {
            setHtml(payButton, html`<span class="spinner"></span><span>Processing payment…</span>`);
            return;
        }
        setHtml(payButton, html`<i data-lucide="lock" class="h-5 w-5"></i><span id="pay-label">${payText()}</span>`);
        renderIcons(payButton);
    };
    const choices = () => ({
        addons: chosenAddons(),
        gratuity: gratuity(),
        first_name: F.first_name.value,
        last_name: F.last_name.value,
        email: F.email.value,
        phone: F.phone.value,
        occasion: F.occasion.value || null,
        heard: F.heard.value || null,
        notes: F.notes.value || null,
        agree: F.agree.checked,
        card: savedCard()?.value ?? null,
        save_card: !savedCard() && Boolean(saveBox?.checked),
    });
    const fieldErrors = (error) => {
        if (!(error instanceof HttpError) || !error.isValidation) return false;
        let shown = false;
        for (const [name, messages] of Object.entries(error.errors)) {
            const field = F[name];
            if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                setFieldError(field, messages[0]);
                shown = true;
            } else if (name === 'card') {
                const box = form.querySelector('[data-card-error]');
                if (box) {
                    box.textContent = messages[0];
                    box.classList.remove('hidden');
                    shown = true;
                }
            }
        }
        return shown;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (paying) return;
        const current = rules();
        const results = current.map(check);
        if (!results.every(Boolean)) {
            const first = current[results.indexOf(false)][0];
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => first.focus({ preventScroll: true }), 400);
            toast('Please check the highlighted fields', 'error');
            return;
        }
        showPageError(null);
        setPaying(true);

        try {
            const saved = savedCard();
            if (elements && !saved) {
                syncSave();
                const { error: incomplete } = await elements.submit(); // Stripe checks its own fields first
                if (incomplete) throw new Error(incomplete.message);
            }
            const { data: payment } = await http.post(data.urls.pay, choices(), { idempotencyKey: newIdempotencyKey() });

            if (payment.provider === 'stripe') {
                const name = `${F.first_name.value} ${F.last_name.value}`.trim();
                // A saved card: Stripe confirms with that card (3-D Secure if its bank asks); a new one: the Element.
                const { error, paymentIntent } = await stripe.confirmPayment(
                    payment.payment_method
                        ? { clientSecret: payment.client_secret, confirmParams: { return_url: payment.return_url, payment_method: payment.payment_method }, redirect: 'if_required' }
                        : {
                              elements,
                              clientSecret: payment.client_secret,
                              confirmParams: { return_url: payment.return_url, payment_method_data: { billing_details: { name, email: F.email.value, phone: F.phone.value } } },
                              redirect: 'if_required',
                          },
                );
                if (error) throw new Error(error.message ?? 'Your card was not charged. Please try again.');
                if (paymentIntent) window.location.assign(payment.return_url); // the server asks Stripe and confirms
                return;
            }

            const test = saved
                ? { brand: saved.dataset.brand, last4: saved.dataset.last4, decline: false, exp_month: Number(saved.dataset.expMonth), exp_year: Number(saved.dataset.expYear) }
                : (() => {
                      const number = digits(card.number.value);
                      const [month, year] = card.exp.value.split('/').map((part) => Number(digits(part)));
                      return { brand: paintBrand(), last4: number.slice(-4), decline: number === DECLINE_CARD, exp_month: month, exp_year: 2000 + year };
                  })();
            const { data: result } = await http.post(payment.test_card_url, test, { idempotencyKey: newIdempotencyKey() });
            window.location.assign(result.redirect);
        } catch (error) {
            setPaying(false);
            if (error instanceof HttpError && error.first('hold')) {
                window.location.reload(); // shows "This hold has ended"
                return;
            }
            if (fieldErrors(error)) {
                toast('Please check the highlighted fields', 'error');
                return;
            }
            showPageError(error instanceof HttpError ? failure(error) : error.message);
        }
    });

    /* ---------------- Hold timer (the server's hold expiry) ---------------- */
    const timer = document.getElementById('hold-timer');
    if (timer && data.holdExpiresAt) {
        const offset = Date.parse(data.serverNow) - Date.now(); // the server's clock, not the visitor's
        const expiresAt = Date.parse(data.holdExpiresAt);
        let interval = null;
        const tick = () => {
            const left = Math.max(0, Math.round((expiresAt - (Date.now() + offset)) / 1000));
            timer.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
            if (left === 0) {
                clearInterval(interval);
                if (!paying) window.location.reload();
            }
        };
        interval = setInterval(tick, 1000);
        tick();
    }
}
