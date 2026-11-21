import { html, setHtml, safeUrl } from '../lib/dom.js';
import { renderIcons } from '../lib/icons.js';

/**
 * Full-screen photo viewer (mockup CYB.lightbox).
 *
 * Declarative: put [data-lightbox-gallery] on a wrapper and data-lightbox="<image url>" on each
 * clickable photo (button or link). Clicking opens the gallery at that photo.
 * Programmatic: openLightbox(['/images/a.jpg', …], startIndex).
 * Keyboard: ← → to move, Esc to close. Focus returns to the clicked photo.
 */
let box = null;
let images = [];
let index = 0;
let returnFocusTo = null;

function build() {
    box = document.createElement('div');
    box.id = 'lightbox';
    box.className = 'fixed inset-0 z-[110] flex items-center justify-center bg-navy-950/95 p-4 backdrop-blur';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-label', 'Photo viewer');
    box.inert = true;
    setHtml(
        box,
        html`<button type="button" data-lb-close class="absolute right-5 top-5 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close"><i data-lucide="x" class="h-6 w-6"></i></button>
            <button type="button" data-lb-prev class="absolute left-3 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20 md:left-8" aria-label="Previous photo"><i data-lucide="chevron-left" class="h-6 w-6"></i></button>
            <img data-lb-img class="max-h-[82vh] max-w-full rounded-2xl object-contain shadow-2xl" alt="">
            <button type="button" data-lb-next class="absolute right-3 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20 md:right-8" aria-label="Next photo"><i data-lucide="chevron-right" class="h-6 w-6"></i></button>
            <p data-lb-count class="absolute bottom-6 left-1/2 -translate-x-1/2 text-sm font-semibold tracking-widest text-white/70" aria-live="polite"></p>`,
    );
    document.body.appendChild(box);
    renderIcons(box);

    box.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        if (target === box || target.closest('[data-lb-close]')) close();
        else if (target.closest('[data-lb-prev]')) go(-1);
        else if (target.closest('[data-lb-next]')) go(1);
    });
    document.addEventListener('keydown', (event) => {
        if (!box.classList.contains('is-open')) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') go(-1);
        if (event.key === 'ArrowRight') go(1);
    });
}

function show() {
    box.querySelector('[data-lb-img]').src = safeUrl(images[index]);
    box.querySelector('[data-lb-count]').textContent = `${index + 1} / ${images.length}`;
    const single = images.length < 2;
    box.querySelector('[data-lb-prev]').hidden = single;
    box.querySelector('[data-lb-next]').hidden = single;
}

function go(delta) {
    index = (index + delta + images.length) % images.length;
    show();
}

function close() {
    box.classList.remove('is-open');
    box.inert = true;
    document.documentElement.classList.remove('overflow-hidden');
    if (returnFocusTo instanceof HTMLElement) returnFocusTo.focus();
}

export function openLightbox(list, start = 0) {
    if (!list.length) return;
    if (!box) build();
    images = list;
    index = Math.max(0, Math.min(start, list.length - 1));
    returnFocusTo = document.activeElement;
    show();
    box.inert = false;
    document.documentElement.classList.add('overflow-hidden');
    requestAnimationFrame(() => box.classList.add('is-open'));
    // Still `visibility: hidden` until the fade starts; focus the close button a moment later.
    setTimeout(() => box.querySelector('[data-lb-close]').focus(), 60);
}

export function initLightboxes(root = document) {
    root.querySelectorAll('[data-lightbox-gallery]').forEach((gallery) => {
        if (gallery.dataset.bound) return;
        gallery.dataset.bound = '1';
        gallery.addEventListener('click', (event) => {
            const trigger = event.target instanceof Element ? event.target.closest('[data-lightbox]') : null;
            if (!trigger || !gallery.contains(trigger)) return;
            event.preventDefault();
            const triggers = Array.from(gallery.querySelectorAll('[data-lightbox]'));
            openLightbox(
                triggers.map((t) => t.dataset.lightbox),
                triggers.indexOf(trigger),
            );
        });
    });
}
