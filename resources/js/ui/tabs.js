/**
 * Accessible tabs (WAI-ARIA tabs pattern) for server-rendered panels.
 *
 * <div role="tablist" data-tabs data-tab-on="…classes…" data-tab-off="…classes…">
 *   <button role="tab" aria-controls="panel-a" aria-selected="true">A</button> …
 * </div>
 * <div id="panel-a" role="tabpanel">…</div> <div id="panel-b" role="tabpanel" hidden>…</div>
 *
 * Click or ←/→/Home/End switches tabs. Fires a "tabs:change" event on the tablist.
 */
export function initTabs(root = document) {
    root.querySelectorAll('[data-tabs]').forEach((list) => {
        if (list.dataset.bound) return;
        list.dataset.bound = '1';

        const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
        const on = list.dataset.tabOn ?? '';
        const off = list.dataset.tabOff ?? '';

        const select = (tab, focus = false) => {
            for (const t of tabs) {
                const active = t === tab;
                t.setAttribute('aria-selected', String(active));
                t.tabIndex = active ? 0 : -1;
                if (on || off) t.className = active ? on : off;
                const panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) panel.hidden = !active;
            }
            if (focus) tab.focus();
            list.dispatchEvent(new CustomEvent('tabs:change', { detail: { tab } }));
        };

        list.addEventListener('click', (event) => {
            const tab = event.target instanceof Element ? event.target.closest('[role="tab"]') : null;
            if (tab && list.contains(tab)) select(tab);
        });

        list.addEventListener('keydown', (event) => {
            const index = tabs.indexOf(document.activeElement);
            if (index === -1) return;
            const moves = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 };
            if (!(event.key in moves)) return;
            event.preventDefault();
            select(tabs[(moves[event.key] + tabs.length) % tabs.length], true);
        });
    });
}
