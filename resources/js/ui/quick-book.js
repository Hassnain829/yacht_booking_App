/**
 * Quick-book forms (home hero, yacht sidebar) are plain GET forms to /book, so they work without JS.
 * JS only drops empty fields, so the URL stays clean (/book?guests=10 instead of ?experience=&date=&guests=10).
 */
export function initQuickBook(root = document) {
    root.querySelectorAll('form[data-quick-book]').forEach((form) => {
        form.addEventListener('submit', () => {
            for (const field of form.elements) {
                if (field.name && 'value' in field && field.value === '') field.disabled = true;
            }
            // Re-enable when the user comes back with the browser's back button.
            window.addEventListener('pageshow', () => [...form.elements].forEach((f) => (f.disabled = false)), { once: true });
        });
    });
}
