import { html, setHtml } from '../lib/dom.js';
import { http, HttpError } from '../lib/http.js';
import { toast } from '../lib/toast.js';
import { formatCents } from '../lib/money.js';
import { quote } from '../lib/demo-quote.js';
import { setFieldError } from '../ui/field-errors.js';

/**
 * Checkout page: live totals (optional add-ons, gratuity, promo; always paid in full, D22), card field formatting
 * and client-side checks, hold timer. The SERVER recalculates everything on submit (CheckoutController@store).
 *
 * Card numbers never leave the browser: the card inputs have no name. Only brand + last 4 are posted.
 * Phase 5: Stripe Payment Element replaces the card inputs.
 */
const WHOLE = { cents: 'never' };
const money = (cents) => formatCents(cents, WHOLE);
const digits = (value) => String(value).replace(/\D/g, '');
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

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
    const card = {
        number: form.querySelector('[data-card="number"]'),
        exp: form.querySelector('[data-card="exp"]'),
        cvc: form.querySelector('[data-card="cvc"]'),
        zip: form.querySelector('[data-card="zip"]'),
    };
    let promo = null; // { code, type, value, label } — confirmed by the server
    const addons = Object.fromEntries(data.addons.map((addon) => [addon.slug, addon]));
    const chosenAddons = () => Array.from(form.querySelectorAll('input[name="addons[]"]:checked')).map((box) => addons[box.value]).filter(Boolean);

    /* ---------------- Totals ---------------- */
    const currentQuote = () =>
        quote(data.yacht, data.experience, { ...data.pricing, promos: promo ? [promo] : [] }, {
            date: data.selection.date,
            hours: data.selection.hours,
            guests: data.selection.guests,
            addons: chosenAddons(),
            promo: promo?.code,
            gratuity: Number(F.gratuity.value),
        });

    function renderTotals() {
        const q = currentQuote();
        const base = q.charter + q.captain;

        for (const el of form.querySelectorAll('[data-tip-amount]')) {
            const pct = Number(el.dataset.tipAmount);
            if (pct > 0) el.textContent = money(Math.round((base * pct) / 100));
        }
        setHtml(
            document.getElementById('checkout-lines'),
            html`${q.lines.map((l) => html`<div class="flex justify-between gap-4"><dt class="text-navy-600">${l.label}<span class="block text-[11px] text-navy-400">${l.sub}</span></dt><dd class="font-semibold text-navy-900">${money(l.amount)}</dd></div>`)}
                ${q.discount ? html`<div class="flex justify-between gap-4 text-emerald-700"><dt class="font-semibold">Discount (${q.promo.code})</dt><dd class="font-bold">−${money(q.discount)}</dd></div>` : ''}
                <div class="flex justify-between gap-4"><dt class="text-navy-600">Taxes (est. ${data.pricing.tax_rate_bp / 100}%)</dt><dd class="font-semibold text-navy-900">${money(q.tax)}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-navy-600">Crew gratuity (${q.gratuityPct}%)</dt><dd class="font-semibold text-navy-900">${money(q.gratuity)}</dd></div>`,
        );
        document.getElementById('checkout-total').textContent = money(q.total);
        document.getElementById('checkout-due').textContent = money(q.total);
        document.getElementById('pay-label').textContent = `Pay ${money(q.total)} & confirm booking`;
    }

    /* ---------------- Promo (checked by the server, one code at a time) ---------------- */
    const promoBox = document.getElementById('promo-box');
    const promoInput = document.getElementById('promo-input');
    const showPromo = () => {
        promoBox.querySelector('[data-promo-form]').classList.toggle('hidden', Boolean(promo));
        promoBox.querySelector('[data-promo-hint]').classList.toggle('hidden', Boolean(promo));
        const applied = promoBox.querySelector('[data-promo-applied]');
        applied.classList.toggle('hidden', !promo);
        applied.classList.toggle('flex', Boolean(promo));
        promoBox.querySelector('[data-promo-label]').textContent = promo ? `${promo.code} · ${promo.label}` : '';
        F.promo.value = promo?.code ?? '';
    };

    async function applyPromo() {
        const code = promoInput.value.trim().toUpperCase();
        if (!code) return;
        if (!/^[A-Z0-9]{1,30}$/.test(code)) {
            toast('That promo code is not valid', 'error');
            return;
        }
        try {
            promo = (await http.post(data.promoUrl, { code })).data; // an API Resource: { data: { code, type, value, label } }
            showPromo();
            renderTotals();
            toast(`Promo ${promo.code} applied — ${promo.label}`);
        } catch (error) {
            toast(error instanceof HttpError ? error.message : 'Could not check that code', 'error');
        }
    }

    promoBox.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (target?.closest('[data-apply-promo]')) applyPromo();
        if (target?.closest('[data-remove-promo]')) {
            promo = null;
            promoInput.value = '';
            showPromo();
            renderTotals();
        }
    });
    promoInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyPromo();
        }
    });

    /* ---------------- Card formatting ---------------- */
    const brandBadge = document.getElementById('card-brand');
    const paintBrand = () => {
        const brand = cardBrand(digits(card.number.value));
        brandBadge.textContent = brand;
        brandBadge.className = `absolute right-3 top-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider ${brand === 'Card' ? 'bg-navy-50 text-navy-400' : 'bg-navy-950 text-white'}`;
        return brand;
    };
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

    /* ---------------- Validation (UX only — the server validates again) ---------------- */
    const rules = [
        [F.first_name, (v) => v.trim().length > 0, 'Required'],
        [F.last_name, (v) => v.trim().length > 0, 'Required'],
        [F.email, (v) => EMAIL.test(v.trim()), 'Enter a valid email'],
        [F.phone, (v) => digits(v).length >= 10, 'Enter a valid phone number'],
        [card.number, (v) => luhn(digits(v)), 'Enter a valid card number'],
        [card.exp, (v) => {
            const m = v.match(/^(\d{2})\s*\/\s*(\d{2})$/);
            if (!m) return false;
            const month = Number(m[1]);
            return month >= 1 && month <= 12 && new Date(2000 + Number(m[2]), month, 0, 23, 59) >= new Date();
        }, 'Invalid date'],
        [card.cvc, (v) => /^\d{3,4}$/.test(v), '3–4 digits'],
        [card.zip, (v) => digits(v).length >= 5, 'Required'],
        [F.agree, (_, el) => el.checked, 'Please accept to continue'],
    ];
    const check = ([field, ok, message]) => {
        const valid = ok(field.value, field);
        setFieldError(field, valid ? '' : message);
        return valid;
    };
    form.addEventListener('blur', (event) => {
        const rule = rules.find(([field]) => field === event.target);
        if (rule && event.target.value) check(rule);
    }, true);
    F.agree.addEventListener('change', () => check(rules.find(([field]) => field === F.agree)));

    /* ---------------- Submit ---------------- */
    form.addEventListener('change', (event) => {
        if (event.target.name === 'gratuity' || event.target.name === 'addons[]') renderTotals();
    });

    form.addEventListener('submit', (event) => {
        const results = rules.map(check);
        if (!results.every(Boolean)) {
            event.preventDefault();
            const first = rules[results.indexOf(false)][0];
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => first.focus({ preventScroll: true }), 400);
            toast('Please check the highlighted fields', 'error');
            return;
        }
        // Only the brand and last 4 digits are sent. The card inputs have no name, so they are not posted.
        F.card_brand.value = paintBrand();
        F.card_last4.value = digits(card.number.value).slice(-4);
        const button = document.getElementById('pay-btn');
        button.disabled = true;
        setHtml(button, html`<span class="spinner"></span><span>Processing payment…</span>`);
    });

    document.getElementById('fill-demo')?.addEventListener('click', () => {
        card.number.value = '4242 4242 4242 4242';
        card.exp.value = `12 / ${String((new Date().getFullYear() + 2) % 100).padStart(2, '0')}`;
        card.cvc.value = '123';
        card.zip.value = '60601';
        paintBrand();
        rules.filter(([field]) => Object.values(card).includes(field)).forEach(check);
    });

    /* ---------------- Notes handed over from the booking wizard (this tab only) ---------------- */
    try {
        const notes = sessionStorage.getItem('cyb_booking_notes');
        if (notes && !F.notes.value) F.notes.value = notes.slice(0, 1000);
        sessionStorage.removeItem('cyb_booking_notes');
    } catch {
        /* storage disabled */
    }

    /* ---------------- Hold timer (Phase 4: the server-side hold expiry) ---------------- */
    let left = 15 * 60;
    const timer = document.getElementById('hold-timer');
    const interval = setInterval(() => {
        left -= 1;
        if (left <= 0) {
            clearInterval(interval);
            timer.textContent = 'expired';
            toast('Your hold expired — times may change, but you can still complete checkout.', 'error');
            return;
        }
        timer.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
    }, 1000);

    if (F.promo.value) {
        promoInput.value = F.promo.value;
        applyPromo();
    }
    renderTotals();
}
