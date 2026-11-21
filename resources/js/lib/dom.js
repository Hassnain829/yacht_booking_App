/**
 * Safe DOM helpers (PLAN §8.2 rule 9).
 *
 * The rule: untrusted text (anything from the server, the URL or a form) never goes into
 * innerHTML as-is. Use `el.textContent = …`, or build markup with the `html` tagged template:
 *
 *     setHtml(list, html`<li class="item">${boat.name}</li>`);   // boat.name is escaped
 *
 * Every value interpolated into html`` is escaped, unless it is itself the result of html``
 * (so templates can be nested) or of trusted() for markup written in our own code.
 */

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' };

/** Escape text for use inside HTML text or a quoted attribute value. */
export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"'`]/g, (c) => ESCAPES[c]);
}

/** A string of markup that is known to be safe. Only html`` and trusted() create these. */
export class SafeHtml {
    #value;

    constructor(value) {
        this.#value = value;
    }

    toString() {
        return this.#value;
    }
}

/**
 * Mark markup written in OUR source code as safe (e.g. a static SVG).
 * Never pass server data, URL params or user input to this function.
 */
export function trusted(markup) {
    return new SafeHtml(String(markup));
}

function renderValue(value) {
    if (value instanceof SafeHtml) return value.toString();
    if (Array.isArray(value)) return value.map(renderValue).join('');
    if (value === null || value === undefined || value === false) return '';
    return escapeHtml(value);
}

/** Tagged template that escapes every interpolated value. Arrays are joined; null/false render nothing. */
export function html(strings, ...values) {
    let out = strings[0];
    for (let i = 0; i < values.length; i++) {
        out += renderValue(values[i]) + strings[i + 1];
    }
    return new SafeHtml(out);
}

/** Replace an element's content. Only accepts html``/trusted() results, so raw strings can't slip in. */
export function setHtml(el, markup) {
    if (!(markup instanceof SafeHtml)) {
        throw new TypeError('setHtml() only accepts html`` or trusted() markup');
    }
    el.innerHTML = markup.toString();
}

const SAFE_PROTOCOLS = new Set(['http:', 'https:', 'mailto:', 'tel:']);

/**
 * Return the URL if it is safe to put in href/src, otherwise '#'.
 * Allows relative paths and http(s)/mailto/tel. Blocks javascript:, data:, vbscript:
 * (also when hidden with tabs/newlines) and protocol-relative URLs (//evil.com, /\evil.com).
 */
export function safeUrl(url) {
    const value = String(url ?? '').trim();
    if (value === '' || /^[\\/]{2}/.test(value)) return '#';
    try {
        // The URL parser strips tabs/newlines exactly like the browser does.
        const parsed = new URL(value, 'https://relative.invalid');
        return SAFE_PROTOCOLS.has(parsed.protocol) ? value : '#';
    } catch {
        return '#';
    }
}

const DATA_IMAGE = /^data:image\/(?:jpeg|png|gif|webp);base64,[a-z0-9+/]+=*$/i;

/**
 * Like safeUrl(), but for <img src>: also allows base64 data URLs of raster images
 * (photo previews made in the browser). SVG data URLs are refused.
 */
export function safeImageSrc(url) {
    const value = String(url ?? '').trim();
    return DATA_IMAGE.test(value) ? value : safeUrl(value);
}

export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

/** Delegated event listener: handler(event, matchedElement). */
export function on(root, type, selector, handler, options) {
    root.addEventListener(
        type,
        (event) => {
            const match = event.target instanceof Element ? event.target.closest(selector) : null;
            if (match && root.contains(match)) handler(event, match);
        },
        options,
    );
}
