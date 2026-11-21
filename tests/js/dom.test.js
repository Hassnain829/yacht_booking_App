import { test } from 'node:test';
import assert from 'node:assert/strict';
import { escapeHtml, html, trusted, setHtml, safeUrl, SafeHtml } from '../../resources/js/lib/dom.js';

test('escapeHtml escapes every HTML-special character', () => {
    assert.equal(escapeHtml(`<img src=x onerror="alert('1')">&\``), '&lt;img src=x onerror=&quot;alert(&#39;1&#39;)&quot;&gt;&amp;&#96;');
    assert.equal(escapeHtml(null), '');
    assert.equal(escapeHtml(undefined), '');
    assert.equal(escapeHtml(0), '0');
});

test('html`` escapes interpolated values (XSS payloads stay text)', () => {
    const name = '<script>alert(1)</script>';
    const out = html`<p title="${'" onmouseover="x'}">${name}</p>`;
    assert.ok(out instanceof SafeHtml);
    assert.equal(String(out), '<p title="&quot; onmouseover=&quot;x">&lt;script&gt;alert(1)&lt;/script&gt;</p>');
});

test('html`` nests templates without double escaping, joins arrays, skips null/false', () => {
    const items = ['a&b', 'c'].map((x) => html`<li>${x}</li>`);
    const out = html`<ul>${items}${null}${false}${undefined}${0}</ul>`;
    assert.equal(String(out), '<ul><li>a&amp;b</li><li>c</li>0</ul>');
});

test('trusted() passes our own markup through unchanged', () => {
    assert.equal(String(html`<span>${trusted('<b>ok</b>')}</span>`), '<span><b>ok</b></span>');
});

test('setHtml() refuses plain strings, accepts html`` results', () => {
    const el = { innerHTML: '' };
    assert.throws(() => setHtml(el, '<b>raw</b>'), TypeError);
    setHtml(el, html`<b>${'<i>'}</b>`);
    assert.equal(el.innerHTML, '<b>&lt;i&gt;</b>');
});

test('safeUrl() allows normal links and blocks script / protocol-relative URLs', () => {
    for (const ok of ['/yachts/azure', 'yachts?x=1', '#top', '?page=2', '../a', 'https://example.com', 'mailto:a@b.c', 'tel:+13125550148']) {
        assert.equal(safeUrl(ok), ok, ok);
    }
    for (const bad of ['javascript:alert(1)', ' JavaScript:alert(1)', 'java\tscript:alert(1)', 'data:text/html,<b>', 'vbscript:x', '//evil.com', '/\\evil.com', '\\\\evil.com', '', null]) {
        assert.equal(safeUrl(bad), '#', String(bad));
    }
});
