/** Phase 0 QA page (/dev/theme, local only): exercises the theme and every JS helper in a real browser. */
import { html, setHtml, $, $$, on } from '../lib/dom.js';
import { iconNames, renderIcons } from '../lib/icons.js';
import { formatCents, formatBp } from '../lib/money.js';
import { todayInChicago, formatDate, formatTime, addHoursToTime, isWeekend } from '../lib/dates.js';
import { toast } from '../lib/toast.js';

export default function devTheme() {
    // Icons
    $('#icon-count').textContent = String(iconNames.length);
    setHtml(
        $('#icon-grid'),
        html`${iconNames.map(
            (name) => html`<div class="flex flex-col items-center gap-2 rounded-xl bg-white p-3 text-center shadow-card">
                <i data-lucide="${name}" class="h-6 w-6 text-navy-900"></i>
                <span class="text-[10px] font-semibold text-navy-500">${name}</span>
            </div>`,
        )}`,
    );
    renderIcons($('#icon-grid'));

    // Money + dates
    $$('[data-money]').forEach((el) => (el.textContent = formatCents(Number(el.dataset.money))));
    const today = todayInChicago();
    const rows = [
        ['formatCents(125050)', formatCents(125050)],
        ['formatCents(165000)', formatCents(165000)],
        ['formatBp(900) tax', formatBp(900)],
        ['todayInChicago()', today],
        ['formatDate(today)', formatDate(today)],
        ['isWeekend(today)', String(isWeekend(today))],
        ["formatTime('17:30') + 3h", `${formatTime('17:30')} → ${formatTime(addHoursToTime('17:30', 3))}`],
    ];
    setHtml(
        $('#format-report'),
        html`${rows.map(([k, v]) => html`<div class="flex justify-between gap-4"><dt class="font-mono text-navy-500">${k}</dt><dd class="font-bold">${v}</dd></div>`)}`,
    );

    // Fonts actually used (after web fonts load)
    document.fonts.ready.then(() => {
        const body = getComputedStyle(document.body).fontFamily;
        const display = getComputedStyle($('h1')).fontFamily;
        $('[data-font="body"]').textContent = `${body} · loaded: ${document.fonts.check('500 16px Manrope')}`;
        $('[data-font="display"]').textContent = `${display} · loaded: ${document.fonts.check('600 16px "Playfair Display"')}`;
    });

    // Container width at this viewport
    const report = () => {
        const c = $('main.container');
        const s = getComputedStyle(c);
        $('#container-report').textContent = `viewport ${window.innerWidth}px → container ${Math.round(c.getBoundingClientRect().width)}px (max-width ${s.maxWidth}, padding ${s.paddingLeft})`;
    };
    report();
    window.addEventListener('resize', report);

    // Toasts (the XSS button proves messages are shown as text)
    on(document, 'click', '[data-toast]', (event, button) => {
        const kind = button.dataset.toast;
        if (kind === 'xss') toast('<img src=x onerror="alert(1)"> stays plain text');
        else toast(kind === 'error' ? 'Something went wrong (error toast)' : 'Saved (success toast)', kind);
    });
}
