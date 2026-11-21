/**
 * PHASE 1 DEMO ONLY: listing drafts saved in this browser (localStorage), like the mockup's CYB.listings.
 * Phase 3 replaces this with the server (a JSON PATCH per wizard step) and this file is deleted.
 *
 * Stored data is treated as untrusted: it is always rendered with textContent / html``.
 */
const KEY = 'cyb_listings';
const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

export const listingStore = {
    all() {
        try {
            const list = JSON.parse(localStorage.getItem(KEY) ?? '[]');
            return Array.isArray(list) ? list.filter((l) => isObject(l) && typeof l.id === 'string') : [];
        } catch {
            return [];
        }
    },
    get(id) {
        return this.all().find((l) => l.id === id) ?? null;
    },
    /** Returns false when the browser storage is full (large photos). */
    save(listing) {
        listing.updated_at = new Date().toISOString();
        const list = [listing, ...this.all().filter((l) => l.id !== listing.id)];
        try {
            localStorage.setItem(KEY, JSON.stringify(list));
            return true;
        } catch {
            return false;
        }
    },
    remove(id) {
        try {
            localStorage.setItem(KEY, JSON.stringify(this.all().filter((l) => l.id !== id)));
        } catch {
            /* storage disabled */
        }
    },
};

/** "Title" or "2021 Cobalt R30" or "Untitled boat" (mockup CYB.listings.title). */
export function listingTitle(listing) {
    const specs = isObject(listing?.specs) ? listing.specs : {};
    const model = [specs.year, specs.make !== 'Other' ? specs.make : '', specs.model].filter((v) => typeof v === 'string' && v.trim()).join(' ');
    return (typeof listing?.title === 'string' && listing.title.trim()) || model || 'Untitled boat';
}
