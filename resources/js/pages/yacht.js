import { html, setHtml } from '../lib/dom.js';
import { formatCents } from '../lib/money.js';
import { quote, durationsFor } from '../lib/demo-quote.js';
import { toast } from '../lib/toast.js';

/** Estimates are shown in whole dollars (exact cents appear at checkout). */
const WHOLE = { cents: 'never' };
import { openLightbox } from '../ui/lightbox.js';

/**
 * Yacht detail page: live estimate in the booking card + mobile bar, guest stepper,
 * share / save, "view all photos". The booking card is a GET form to /book, so it also works without JS.
 */
export default function yachtPage() {
    initGallery();
    initShareAndSave();

    const form = document.getElementById('y-book-form');
    if (!form) return; // boat is unavailable: no booking card

    const { yacht, experiences, pricing } = JSON.parse(form.dataset.quote);
    const experienceBySlug = Object.fromEntries(experiences.map((e) => [e.slug, e]));
    const estimate = document.getElementById('y-estimate');
    const bar = document.getElementById('mobile-book-bar');

    const clampGuests = () => {
        const value = Math.max(1, Math.min(yacht.max_guests, Math.round(Number(form.guests.value) || 1)));
        form.guests.value = String(value);
        return value;
    };

    const fillHours = () => {
        const current = Number(form.hours.value);
        const options = durationsFor(yacht, experienceBySlug[form.experience.value]);
        setHtml(form.hours, html`${options.map((h) => html`<option value="${h}">${h} hours</option>`)}`);
        if (options.includes(current)) form.hours.value = String(current);
    };

    const update = () => {
        const q = quote(yacht, experienceBySlug[form.experience.value], pricing, {
            date: form.date.value,
            hours: form.hours.value,
            guests: clampGuests(),
        });
        estimate.querySelector('[data-est="base"]').textContent = formatCents(q.charter + q.captain, WHOLE);
        estimate.querySelector('[data-est="extras"]').textContent = formatCents(q.total - q.charter - q.captain, WHOLE);
        estimate.querySelector('[data-est="total"]').textContent = formatCents(q.total, WHOLE);
        estimate.querySelector('[data-est="weekend"]').classList.toggle('hidden', !q.weekend);

        bar.querySelector('[data-bar="hours"]').textContent = String(q.hours);
        bar.querySelector('[data-bar="total"]').textContent = formatCents(q.total, WHOLE);
        const params = new URLSearchParams({ yacht: yacht.slug, experience: form.experience.value, hours: form.hours.value, guests: form.guests.value });
        if (form.date.value) params.set('date', form.date.value);
        bar.querySelector('[data-bar="link"]').href = `${form.action}?${params}`;
    };

    form.experience.addEventListener('change', () => {
        fillHours();
        update();
    });
    form.addEventListener('input', update);
    form.guests.addEventListener('blur', update);
    form.addEventListener('click', (event) => {
        const step = event.target instanceof Element ? event.target.closest('[data-guests-step]') : null;
        if (!step) return;
        form.guests.value = String((Number(form.guests.value) || 1) + Number(step.dataset.guestsStep));
        update();
    });
    form.addEventListener('submit', () => {
        clampGuests();
        if (!form.date.value) form.date.disabled = true; // keep the /book URL clean
    });

    fillHours();
    update();
}

function initGallery() {
    document.querySelector('[data-open-gallery]')?.addEventListener('click', (event) => {
        const gallery = event.currentTarget.closest('[data-lightbox-gallery]');
        const photos = Array.from(gallery.querySelectorAll('[data-lightbox]')).map((el) => el.dataset.lightbox);
        openLightbox(photos, 0);
    });
}

/** Share uses the native share sheet or copies the link. Save keeps favourites in this browser only. */
function initShareAndSave() {
    document.getElementById('share-btn')?.addEventListener('click', async () => {
        try {
            if (navigator.share) await navigator.share({ title: document.title, url: window.location.href });
            else {
                await navigator.clipboard.writeText(window.location.href);
                toast('Link copied to clipboard');
            }
        } catch {
            /* the visitor closed the share sheet */
        }
    });

    const save = document.getElementById('save-btn');
    if (!save) return;
    const KEY = 'cyb_saved_yachts';
    const read = () => {
        try {
            const list = JSON.parse(localStorage.getItem(KEY) ?? '[]');
            return Array.isArray(list) ? list.filter((s) => typeof s === 'string' && /^[a-z0-9-]{1,64}$/.test(s)) : [];
        } catch {
            return [];
        }
    };
    const paint = (on) => {
        save.classList.toggle('bg-white', on);
        save.classList.toggle('text-navy-950', on);
        save.setAttribute('aria-pressed', String(on));
    };
    paint(read().includes(save.dataset.slug));
    save.addEventListener('click', () => {
        const list = read();
        const on = !list.includes(save.dataset.slug);
        try {
            localStorage.setItem(KEY, JSON.stringify(on ? [...list, save.dataset.slug] : list.filter((s) => s !== save.dataset.slug)));
        } catch {
            /* storage disabled */
        }
        paint(on);
        toast(on ? `${save.dataset.name} saved to your favorites` : 'Removed from favorites');
    });
}
