import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { http, request, HttpError, newIdempotencyKey } from '../../resources/js/lib/http.js';

const ORIGIN = 'https://chicagoyachtbooking.test';
let calls;

function json(status, body, headers = {}) {
    return new Response(body === undefined ? null : JSON.stringify(body), {
        status,
        headers: { 'content-type': 'application/json', ...headers },
    });
}

function respondWith(responseOrFn) {
    globalThis.fetch = async (url, init) => {
        calls.push({ url: String(url), init });
        return typeof responseOrFn === 'function' ? responseOrFn(url, init) : responseOrFn;
    };
}

beforeEach(() => {
    calls = [];
    globalThis.window = { location: { origin: ORIGIN } };
    globalThis.document = {
        querySelector: (sel) => (sel === 'meta[name="csrf-token"]' ? { getAttribute: () => 'csrf-123' } : null),
    };
});

test('GET sends JSON headers + cookies, no CSRF header, and skips empty params', async () => {
    respondWith(json(200, { ok: true }));
    const body = await http.get('/book/availability', { params: { boat: 'b1', date: '', page: 0, x: null } });
    assert.deepEqual(body, { ok: true });
    const { url, init } = calls[0];
    assert.equal(url, `${ORIGIN}/book/availability?boat=b1&page=0`);
    assert.equal(init.method, 'GET');
    assert.equal(init.credentials, 'same-origin');
    assert.equal(init.headers.Accept, 'application/json');
    assert.equal(init.headers['X-CSRF-TOKEN'], undefined);
    assert.equal(init.body, undefined);
});

test('GET sends an array param as key[] (each value once, an empty array not at all)', async () => {
    respondWith(json(200, { ok: true }));
    await http.get('/yachts/azure/quote', { params: { hours: 3, addons: ['champagne', 'decor'], none: [] } });
    assert.equal(calls[0].url, `${ORIGIN}/yachts/azure/quote?hours=3&addons%5B%5D=champagne&addons%5B%5D=decor`);
});

test('POST adds the CSRF token, JSON body and idempotency key', async () => {
    respondWith(json(201, { id: 'x' }));
    const key = newIdempotencyKey();
    await http.post('/book/hold', { boat: 'b1', hours: 3 }, { idempotencyKey: key });
    const { init } = calls[0];
    assert.equal(init.method, 'POST');
    assert.equal(init.headers['X-CSRF-TOKEN'], 'csrf-123');
    assert.equal(init.headers['Content-Type'], 'application/json');
    assert.equal(init.headers['Idempotency-Key'], key);
    assert.equal(init.body, JSON.stringify({ boat: 'b1', hours: 3 }));
    assert.match(key, /^[0-9a-f-]{36}$/);
});

test('cross-origin URLs are refused before any request is sent', async () => {
    respondWith(json(200, {}));
    await assert.rejects(() => http.get('https://evil.example/steal'), /cross-origin/);
    await assert.rejects(() => http.post('//evil.example/steal', {}), /cross-origin/);
    assert.equal(calls.length, 0);
});

test('422 keeps Laravel validation errors and the server message', async () => {
    respondWith(json(422, { message: 'The guests field must not be greater than 13.', errors: { guests: ['The guests field must not be greater than 13.'] } }));
    const error = await http.post('/book/quote', {}).catch((e) => e);
    assert.ok(error instanceof HttpError);
    assert.equal(error.status, 422);
    assert.ok(error.isValidation);
    assert.equal(error.message, 'The guests field must not be greater than 13.');
    assert.equal(error.first('guests'), 'The guests field must not be greater than 13.');
    assert.equal(error.first('missing'), '');
});

test('other errors use our safe messages, never the server text', async () => {
    respondWith(json(500, { message: 'SQLSTATE[42S02]: Base table not found' }));
    let error = await http.get('/x').catch((e) => e);
    assert.equal(error.status, 500);
    assert.equal(error.message, 'Something went wrong on our side. Please try again.');

    respondWith(json(419, { message: 'CSRF token mismatch.' }));
    error = await http.post('/x', {}).catch((e) => e);
    assert.equal(error.status, 419);
    assert.match(error.message, /session expired/i);

    respondWith(json(429, {}));
    error = await http.post('/x', {}).catch((e) => e);
    assert.match(error.message, /too many attempts/i);
});

test('a 200 that is not JSON (e.g. an HTML page) is treated as an error', async () => {
    respondWith(new Response('<html>login</html>', { status: 200, headers: { 'content-type': 'text/html' } }));
    const error = await http.get('/x').catch((e) => e);
    assert.ok(error instanceof HttpError);
    assert.equal(error.status, 500);
});

test('a redirect (e.g. to /login) becomes a 401', async () => {
    respondWith({ type: 'opaqueredirect', status: 0, ok: false, headers: new Headers() });
    const error = await http.get('/account/trips').catch((e) => e);
    assert.equal(error.status, 401);
});

test('204 No Content resolves to an empty object', async () => {
    respondWith(new Response(null, { status: 204 }));
    assert.deepEqual(await http.delete('/owner/boats/x/photos/y'), {});
});

test('network failures and timeouts become status 0', async () => {
    respondWith(() => Promise.reject(new TypeError('Failed to fetch')));
    let error = await http.get('/x').catch((e) => e);
    assert.equal(error.status, 0);

    globalThis.fetch = (url, init) =>
        new Promise((resolve, reject) => init.signal.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError'))));
    error = await request('GET', '/slow', { timeout: 20 }).catch((e) => e);
    assert.ok(error instanceof HttpError);
    assert.equal(error.status, 0);
});

test('a caller abort is re-thrown as-is so the caller can ignore it', async () => {
    globalThis.fetch = (url, init) =>
        new Promise((resolve, reject) => init.signal.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError'))));
    const controller = new AbortController();
    const pending = http.get('/search', { signal: controller.signal });
    controller.abort();
    const error = await pending.catch((e) => e);
    assert.equal(error.name, 'AbortError');
    assert.ok(!(error instanceof HttpError));
});

test('FormData bodies are sent untouched (browser sets the multipart header)', async () => {
    respondWith(json(200, { ok: true }));
    const form = new FormData();
    form.append('photo', new Blob(['x']), 'a.jpg');
    await http.post('/owner/boats/x/photos', form);
    assert.equal(calls[0].init.body, form);
    assert.equal(calls[0].init.headers['Content-Type'], undefined);
});
