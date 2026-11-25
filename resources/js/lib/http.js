/**
 * The ONLY way our JavaScript talks to the server (PLAN §8.2 rules 1–3).
 *
 * - Same-origin only: the session cookie travels with the request and a CSRF token header is
 *   added to every non-GET request. Calls to other origins are refused before they are sent.
 * - JSON in, JSON out. Every failure becomes an HttpError with a safe, user-friendly message.
 * - Requests time out (15 s by default). No automatic retries for writes: pass an
 *   idempotency key instead, so the server can ignore a duplicate.
 *
 *     import { http, HttpError, newIdempotencyKey } from './lib/http';
 *     const quote = await http.post('/book/quote', { boat: ulid, hours: 3 });
 */

const MESSAGES = {
    0: 'Network error. Please check your connection and try again.',
    400: 'Something was wrong with that request. Please refresh the page and try again.',
    401: 'Please log in to continue.',
    403: 'You are not allowed to do that.',
    404: 'We could not find that.',
    409: 'This was just changed. Please refresh and try again.',
    413: 'That file is too large.',
    419: 'Your session expired. Please refresh the page and try again.',
    422: 'Please check the highlighted fields.',
    429: 'Too many attempts. Please wait a moment and try again.',
    500: 'Something went wrong on our side. Please try again.',
    503: 'We are doing a quick update. Please try again in a minute.',
};

/** Statuses whose server message is meant for the user (validation, conflicts like "slot just taken"). */
const SERVER_MESSAGE_STATUSES = new Set([409, 422]);

export class HttpError extends Error {
    constructor(status, message, data = {}) {
        super(message);
        this.name = 'HttpError';
        this.status = status;
        this.data = data;
        /** Laravel validation errors: { field: ['message', …] } */
        this.errors = data && typeof data.errors === 'object' && data.errors !== null ? data.errors : {};
    }

    get isValidation() {
        return this.status === 422;
    }

    /** First error message for a field, or ''. */
    first(field) {
        const list = this.errors[field];
        return Array.isArray(list) && list.length ? String(list[0]) : '';
    }
}

/** A fresh key for one logical action (one "Pay" click). Reuse it when retrying that same action. */
export function newIdempotencyKey() {
    return crypto.randomUUID();
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function messageFor(status, body) {
    if (SERVER_MESSAGE_STATUSES.has(status) && typeof body?.message === 'string' && body.message) {
        return body.message;
    }
    return MESSAGES[status] ?? (status >= 500 ? MESSAGES[500] : MESSAGES[0]);
}

/**
 * @param {string} method  GET | POST | PUT | PATCH | DELETE
 * @param {string} url     a path on our own site, e.g. '/book/quote'
 * @param {object} [options]
 * @param {object|FormData} [options.data]  request body (JSON-encoded unless FormData)
 * @param {object} [options.params]         query string values (empty values are skipped; an array is sent as key[])
 * @param {string} [options.idempotencyKey] sent as the Idempotency-Key header
 * @param {number} [options.timeout=15000]  milliseconds
 * @param {AbortSignal} [options.signal]    lets the caller cancel (e.g. a newer search started)
 */
export async function request(method, url, { data, params, idempotencyKey, timeout = 15000, signal } = {}) {
    const verb = String(method).toUpperCase();
    const target = new URL(url, window.location.origin);

    if (target.origin !== window.location.origin) {
        throw new Error(`http.js refuses cross-origin requests (${target.origin})`);
    }

    for (const [key, value] of Object.entries(params ?? {})) {
        if (Array.isArray(value)) {
            for (const item of value) target.searchParams.append(`${key}[]`, String(item)); // addons: [a, b] → addons[]=a&addons[]=b
        } else if (value !== undefined && value !== null && value !== '') {
            target.searchParams.set(key, String(value));
        }
    }

    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const isForm = typeof FormData !== 'undefined' && data instanceof FormData;

    if (verb !== 'GET' && verb !== 'HEAD') headers['X-CSRF-TOKEN'] = csrfToken();
    if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
    if (data !== undefined && !isForm) headers['Content-Type'] = 'application/json';

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    const onCallerAbort = () => controller.abort();
    signal?.addEventListener('abort', onCallerAbort, { once: true });

    let response;
    try {
        response = await fetch(target, {
            method: verb,
            headers,
            body: data === undefined ? undefined : isForm ? data : JSON.stringify(data),
            credentials: 'same-origin',
            redirect: 'manual', // a redirect (e.g. to /login) is never a valid JSON answer
            signal: controller.signal,
        });
    } catch (error) {
        if (signal?.aborted) throw error; // cancelled on purpose: let the caller ignore it
        throw new HttpError(0, MESSAGES[0]);
    } finally {
        clearTimeout(timer);
        signal?.removeEventListener('abort', onCallerAbort);
    }

    // Redirects arrive as an opaque response (status 0) with redirect: 'manual'.
    if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
        throw new HttpError(401, MESSAGES[401]);
    }

    if (response.status === 204) return {};

    const isJson = (response.headers.get('content-type') ?? '').includes('application/json');
    const body = isJson ? await response.json().catch(() => null) : null;

    if (!response.ok) {
        throw new HttpError(response.status, messageFor(response.status, body), body ?? {});
    }
    if (body === null) {
        // A 200 that is not JSON means something unexpected answered (e.g. an HTML error page).
        throw new HttpError(500, MESSAGES[500]);
    }
    return body;
}

export const http = {
    get: (url, options) => request('GET', url, options),
    post: (url, data, options) => request('POST', url, { ...options, data }),
    put: (url, data, options) => request('PUT', url, { ...options, data }),
    patch: (url, data, options) => request('PATCH', url, { ...options, data }),
    delete: (url, options) => request('DELETE', url, options),
};
