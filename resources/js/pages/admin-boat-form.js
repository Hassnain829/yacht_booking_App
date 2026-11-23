/**
 * Admin → boat form (Add / Edit): the Listing checklist follows the form as it is filled in (3.7).
 *
 * Each checklist item (<li data-check="key">) has a rule here over the form's fields, the same as
 * BoatController::checklist() applies to the saved boat; the server stays the judge when the form is saved.
 * `photos` counts the saved photos (data-photo-count on the card) + the ones chosen in the Photos card, which go
 * up with the save. The Coast Guard warning (more than 6 guests without a COI) shows and hides the same way.
 * Every [data-checklist-done] counter follows (the card's, and the phone Save bar's).
 */

/** @typedef {(form: HTMLFormElement, card: HTMLElement) => boolean} Rule */

/** @type {Record<string, Rule>} */
export const RULES = {
    description: (form) => filled(form, 'short') && filled(form, 'description'),
    highlights: (form) => lineCount(text(form, 'highlights_text')) >= 3,
    specs: (form) => filled(form, 'category') && amount(text(form, 'length_ft')) > 0 && amount(text(form, 'max_guests')) > 0,
    harbor: (form) => filled(form, 'harbor'),
    price: (form) => amount(text(form, 'hourly_rate')) > 0,
    policy: (form) => filled(form, 'cancellation_policy'),
    photos: (form, card) => Number(card.dataset.photoCount ?? 0) + chosenPhotos(form) >= Number(card.dataset.minPhotos ?? 4),
    experiences: (form) => anyTicked(form, /^experiences\[[^\]]+\]\[offered\]$/),
    hours: (form) => anyTicked(form, /^hours\[\d\]\[open\]$/),
};

export default function adminBoatForm() {
    const card = document.querySelector('[data-checklist]');
    const form = card?.closest('form');
    if (!(card instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;

    const refresh = () => refreshChecklist(card, form);
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    refresh(); // a form that came back with errors shows what was typed, so check that, not the saved boat
}

/**
 * @param {HTMLElement} card
 * @param {HTMLFormElement} form
 */
function refreshChecklist(card, form) {
    const items = [...card.querySelectorAll('[data-check]')];
    let done = 0;
    for (const item of items) {
        if (!(item instanceof HTMLElement)) continue;
        const rule = RULES[item.dataset.check ?? ''];
        const ok = rule ? rule(form, card) : item.dataset.ok === '1';
        if (ok) done += 1;
        item.dataset.ok = ok ? '1' : '0';
        item.querySelector('[data-check-icon="yes"]')?.classList.toggle('hidden', !ok);
        item.querySelector('[data-check-icon="no"]')?.classList.toggle('hidden', ok);
        const label = item.querySelector('[data-check-label]');
        label?.classList.toggle('text-navy-950', !ok);
        label?.classList.toggle('text-navy-500', ok);
        item.querySelector('[data-check-hint]')?.classList.toggle('hidden', ok);
    }

    for (const counter of document.querySelectorAll('[data-checklist-done]')) {
        if (counter.textContent !== String(done)) counter.textContent = String(done);
    }
    card.querySelector('[data-checklist-ready]')?.classList.toggle('hidden', done !== items.length);

    const guests = text(form, 'max_guests');
    const inspected = form.querySelector('input[type=checkbox][name="uscg_inspected"]');
    const warn = !(inspected instanceof HTMLInputElement && inspected.checked) && amount(guests) > 6;
    card.querySelector('[data-coi-warning]')?.classList.toggle('hidden', !warn);
    const count = card.querySelector('[data-coi-guests]');
    if (count && warn) count.textContent = guests;
}

/**
 * The trimmed value of a single field (input, select or textarea), '' if there is none.
 *
 * @param {HTMLFormElement} form
 * @param {string} name
 */
function text(form, name) {
    const field = form.elements.namedItem(name);
    return field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement ? field.value.trim() : '';
}

/**
 * @param {HTMLFormElement} form
 * @param {string} name
 */
function filled(form, name) {
    return text(form, name) !== '';
}

/**
 * Non-empty lines, like the server's one-per-line lists (PreparesSlug::lines).
 *
 * @param {string} value
 */
export function lineCount(value) {
    return value.split(/\r\n|\r|\n/).filter((line) => line.trim() !== '').length;
}

/**
 * A plain typed number ("450", "450.5"), as the server's `numeric` rule takes it; NaN for anything else
 * ("$450", "1,250", "abc"), so the checklist never turns green for a value the save would refuse.
 *
 * @param {string} value
 */
export function amount(value) {
    return /^\d+(\.\d+)?$/.test(value.trim()) ? Number(value.trim()) : Number.NaN;
}

/**
 * How many photos are chosen in the Photos card (they upload with the save).
 *
 * @param {HTMLFormElement} form
 */
function chosenPhotos(form) {
    return form.querySelector('input[type=file][name="photos[]"]')?.files?.length ?? 0;
}

/**
 * @param {HTMLFormElement} form
 * @param {RegExp} pattern the checkbox names to look at
 */
function anyTicked(form, pattern) {
    return [...form.querySelectorAll('input[type=checkbox]:checked')].some((box) => box instanceof HTMLInputElement && pattern.test(box.name));
}
