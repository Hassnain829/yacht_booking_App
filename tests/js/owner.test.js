import { test } from 'node:test';
import assert from 'node:assert/strict';
import { dollarsToCents, weekendRateCents, charterCents, ownerNetCents } from '../../resources/js/lib/owner-estimate.js';
import { safeImageSrc } from '../../resources/js/lib/dom.js';
import { listingTitle } from '../../resources/js/lib/listing-store.js';

test('dollarsToCents parses what owners type, and rejects everything else', () => {
    assert.equal(dollarsToCents('380'), 38000);
    assert.equal(dollarsToCents(' 380.5 '), 38050);
    assert.equal(dollarsToCents('1,250.05'), 125005);
    assert.equal(dollarsToCents('0'), 0);
    for (const bad of ['', '-5', '1e3', '12.345', 'abc', '$380', '38 0', '12345678', null, undefined]) {
        assert.equal(dollarsToCents(bad), null, `"${bad}" should be rejected`);
    }
});

test('weekend rate is rounded to whole dollars (same as DemoOwner::charterCents)', () => {
    assert.equal(weekendRateCents(38000, 1500), 43700); // 380 × 1.15 = 437
    assert.equal(weekendRateCents(22500, 1000), 24800); // 247.5 → 248
    assert.equal(weekendRateCents(38000, 0), 38000);
});

test('charter uses the fixed duration price when set, else rate × hours', () => {
    const pricing = { hourlyCents: 38000, markupBp: 1500, fixedCents: { 4: 150000 } };
    assert.equal(charterCents(pricing, 3, false), 114000);
    assert.equal(charterCents(pricing, 3, true), 131100);
    assert.equal(charterCents(pricing, 4, true), 150000, 'fixed price ignores the weekend markup');
    assert.equal(charterCents({ hourlyCents: 38000, markupBp: 1500 }, 6, false), 228000);
});

test('owner net = charter − commission on the charter + cleaning fee in full', () => {
    // Same numbers as the PHP DemoOwnerTest: Friday 3-hour trip on Lady Grace.
    assert.equal(ownerNetCents(131100, 12000, 2000), 116880);
    assert.equal(ownerNetCents(114000, 12000, 2000), 103200);
    assert.equal(ownerNetCents(0, 0, 2000), 0);
});

test('safeImageSrc allows raster data URLs and safe URLs only', () => {
    const png = 'data:image/png;base64,iVBORw0KGgo=';
    assert.equal(safeImageSrc(png), png);
    assert.equal(safeImageSrc('data:image/jpeg;base64,/9j/4AAQ'), 'data:image/jpeg;base64,/9j/4AAQ');
    assert.equal(safeImageSrc('/images/boat.jpg'), '/images/boat.jpg');
    assert.equal(safeImageSrc('http://localhost:8000/images/boat.jpg'), 'http://localhost:8000/images/boat.jpg');
    for (const bad of ['data:image/svg+xml;base64,PHN2Zz4=', 'data:text/html;base64,PHNjcmlwdD4=', 'javascript:alert(1)', '//evil.com/x.png', 'data:image/png;base64,abc"onerror="x']) {
        assert.equal(safeImageSrc(bad), '#', bad);
    }
});

test('listingTitle falls back to year + make + model, then "Untitled boat"', () => {
    assert.equal(listingTitle({ title: 'Lady Grace' }), 'Lady Grace');
    assert.equal(listingTitle({ title: '  ', specs: { year: '2021', make: 'Cobalt', model: 'R30' } }), '2021 Cobalt R30');
    assert.equal(listingTitle({ specs: { year: '2021', make: 'Other', model: 'Custom' } }), '2021 Custom');
    assert.equal(listingTitle({}), 'Untitled boat');
    assert.equal(listingTitle(null), 'Untitled boat');
});
