/**
 * My boats: drafts started on this device are added on top (PHASE 1 DEMO, lib/listing-store.js),
 * using the server's card markup from <template id="local-boat-template"> — fields are filled with
 * textContent only. Delete asks first. Phase 3: DELETE /owner/boats/{ulid} (BoatPolicy) instead.
 */
import { safeImageSrc } from '../lib/dom.js';
import { renderIcons } from '../lib/icons.js';
import { toast } from '../lib/toast.js';
import { listingStore, listingTitle } from '../lib/listing-store.js';

const STATUS = {
    draft: ['In progress', 'bg-navy-100 text-navy-700'],
    pending: ['Pending review', 'bg-amber-100 text-amber-800'],
    approved: ['Approved', 'bg-emerald-100 text-emerald-800'],
};
const NEXT_UP = {
    intro: 'Getting started', availability: 'Availability', advance: 'Availability', multi: 'Availability',
    operators: 'Operators and durations', pricing: 'Pricing', insurance: 'Insurance', insuranceOwner: 'Insurance',
    insuranceBoat: 'Insurance', commercial: 'Insurance', review: 'Review and submit',
};
const PILL = 'rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider';

export default function ownerBoats() {
    const list = document.getElementById('boat-list');
    const template = document.getElementById('local-boat-template');
    if (!list || !template) return;

    const wizardUrl = new URL(list.dataset.boatsUrl, window.location.origin);

    function card(listing) {
        const node = template.content.firstElementChild.cloneNode(true);
        const f = (name) => node.querySelector(`[data-f="${name}"]`);
        const status = STATUS[listing.status] ? listing.status : 'draft';
        const specs = listing.specs ?? {};
        const location = listing.location ?? {};
        const photos = Array.isArray(listing.photos) ? listing.photos : [];
        const updated = new Date(listing.updated_at || listing.created_at || Date.now());

        node.dataset.boat = listing.id;
        node.dataset.local = '';
        f('status').className = `${PILL} ${STATUS[status][1]}`;
        f('status').textContent = STATUS[status][0];
        f('updated').textContent = `Updated ${updated.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} · ID ${listing.id} · on this device`;
        f('title').textContent = listingTitle(listing);
        f('meta').textContent = [
            [specs.year, specs.make !== 'Other' ? specs.make : '', specs.model].filter(Boolean).join(' '),
            specs.length ? `${specs.length} ft` : '',
            [location.city, location.state].filter(Boolean).join(', '),
        ].filter(Boolean).join(' · ') || 'Details not added yet';
        f('next').textContent = status === 'draft' ? `Next up: ${NEXT_UP[listing.step] ?? 'Boat details'}` : 'Our team reviews new listings within 2 business days';
        f('photos').textContent = `${photos.length} ${photos.length === 1 ? 'photo' : 'photos'}`;

        const src = photos[0] ? safeImageSrc(photos[0]) : '#';
        if (src !== '#') {
            const img = document.createElement('img');
            img.src = src;
            img.alt = '';
            img.className = 'h-full w-full object-cover';
            f('no-cover')?.replaceWith(img);
        }

        const link = new URL(wizardUrl);
        link.searchParams.set('draft', listing.id);
        if (status !== 'draft') link.searchParams.set('step', 'review');
        f('edit').href = link.pathname + link.search;
        f('edit').textContent = status === 'draft' ? 'Continue listing' : 'Edit listing';
        return node;
    }

    function updateCount() {
        const count = list.querySelectorAll('article[data-boat]').length;
        let label = list.querySelector('[data-boat-count]');
        if (!label && count) {
            label = document.createElement('p');
            label.className = 'pt-2 text-center text-xs text-navy-400';
            label.dataset.boatCount = '';
            list.appendChild(label);
        }
        if (label) label.textContent = count ? `${count} ${count === 1 ? 'boat' : 'boats'}` : '';
    }

    const local = listingStore.all().filter((l) => /^BL-[A-Z0-9]{1,20}$/.test(l.id));
    if (local.length) {
        list.querySelector('[data-empty]')?.remove();
        list.prepend(...local.map(card));
        updateCount();
        renderIcons(list);
    }

    list.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-delete]') : null;
        if (!button) return;
        const article = button.closest('article[data-boat]');
        const title = article.querySelector('[data-f="title"]').textContent;
        if (!window.confirm(`Delete "${title}"? This can't be undone.`)) return;
        if ('local' in article.dataset) listingStore.remove(article.dataset.boat);
        article.remove();
        updateCount();
        toast('local' in article.dataset ? 'Listing deleted' : 'Listing deleted (demo — it comes back when the page reloads)');
    });
}
