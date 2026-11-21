import { renderIcons } from './icons.js';

/**
 * Small notification at the bottom of the screen (same look as the mockup's CYB.toast).
 * The message is set with textContent, so server text can never inject HTML.
 *
 *     toast('Saved');            toast(error.message, 'error');
 */
export function toast(message, type = 'success', { duration = 3400 } = {}) {
    let wrap = document.getElementById('toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'toast-wrap';
        wrap.className = 'fixed bottom-6 left-1/2 z-[120] w-[calc(100%-2rem)] max-w-sm -translate-x-1/2 space-y-2';
        wrap.setAttribute('aria-live', 'polite');
        wrap.setAttribute('role', 'status');
        document.body.appendChild(wrap);
    }

    const isError = type === 'error';
    const el = document.createElement('div');
    el.className =
        'toast flex items-start gap-3 rounded-2xl bg-navy-950 px-5 py-4 text-sm font-medium text-white shadow-2xl ring-1 ring-white/10';

    const icon = document.createElement('i');
    icon.dataset.lucide = isError ? 'circle-alert' : 'circle-check';
    icon.className = `mt-0.5 h-5 w-5 shrink-0 ${isError ? 'text-red-400' : 'text-brass-300'}`;

    const text = document.createElement('span');
    text.textContent = String(message ?? '');

    el.append(icon, text);
    wrap.appendChild(el);
    renderIcons(el);

    setTimeout(() => {
        el.classList.add('toast-out');
        setTimeout(() => el.remove(), 300);
    }, duration);

    return el;
}
