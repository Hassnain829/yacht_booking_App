import { test } from 'node:test';
import assert from 'node:assert/strict';
import { quote } from '../../resources/js/lib/demo-quote.js';

// Same data as config/demo.php and the same scenarios as tests/Unit/DemoQuoteTest.php.
const pricing = {
    weekend_multiplier_bp: 11500,
    tax_rate_bp: 900,
    promos: [
        { code: 'WELCOME10', type: 'percent', value: 10 },
        { code: 'SUNSET50', type: 'fixed', value: 5000 },
    ],
};
const azure = { min_hours: 3, hourly_rate_cents: 45000, captain_rate_cents: 8500, crew_count: 1, cleaning_fee_cents: 15000 };
const windward = { min_hours: 2, hourly_rate_cents: 22500, captain_rate_cents: 6500, crew_count: 1, cleaning_fee_cents: 7500 };
// Experiences are free and their extras optional (2026-11-25): an experience never adds a line by itself.
const privateExp = { slug: 'private', name: 'Private Day Charter' };
const playpen = { slug: 'playpen', name: 'Playpen Swim Party' };
const fireworks = { slug: 'fireworks', name: 'Navy Pier Fireworks' };
const lilypad = { slug: 'lilypad', name: 'Lily pad & water toys', price_cents: 15000, unit: 'flat' };
const spot = { slug: 'spot', name: 'Fireworks spot', price_cents: 25000, unit: 'flat' };
const catering = { slug: 'catering', name: 'Chef-curated catering', price_cents: 3500, unit: 'per_guest' };
const champagne = { slug: 'champagne', name: 'Champagne toast', price_cents: 12000, unit: 'flat' };

const pick = (q, keys) => keys.map((k) => q[k]);

test('weekday private charter', () => {
    const q = quote(azure, privateExp, pricing, { date: '2026-11-23', hours: 3, guests: 10 });
    assert.equal(q.weekend, false);
    assert.deepEqual(pick(q, ['charter', 'captain', 'subtotal', 'discount', 'tax', 'gratuity', 'total']), [135000, 25500, 175500, 0, 15795, 0, 191295]);
    assert.deepEqual(q.lines.map((l) => l.key), ['charter', 'captain', 'cleaning']);
});

test('weekend + a picked extra + add-ons + percent promo + gratuity', () => {
    const q = quote(azure, playpen, pricing, { date: '2026-11-21', hours: 4, guests: 10, addons: [lilypad, catering, champagne], promo: ' welcome10 ', gratuity: 18 });
    assert.equal(q.weekend, true);
    assert.equal(q.rate, 51800);
    assert.deepEqual(pick(q, ['charter', 'captain', 'subtotal', 'discount', 'tax', 'gratuity', 'total']), [207200, 34000, 318200, 20720, 26773, 43416, 367669]);
    assert.equal(q.lines.find((l) => l.key === 'addon-lilypad').sub, 'Add-on');
    assert.equal(q.lines.find((l) => l.key === 'addon-catering').sub, '$35 × 10 guests');
});

test('an experience adds nothing until its extras are picked', () => {
    const plain = quote(azure, playpen, pricing, { date: '2026-11-21', hours: 4, guests: 10 });
    assert.deepEqual(plain.lines.map((l) => l.key), ['charter', 'captain', 'cleaning']);
    assert.equal(plain.total, quote(azure, null, pricing, { date: '2026-11-21', hours: 4, guests: 10 }).total);
});

test('fixed promo', () => {
    const q = quote(windward, fireworks, pricing, { date: '2026-11-25', hours: 2, guests: 6, addons: [spot], promo: 'SUNSET50', gratuity: 15 });
    assert.deepEqual(pick(q, ['charter', 'captain', 'subtotal', 'discount', 'tax', 'gratuity', 'total']), [45000, 13000, 90500, 5000, 7695, 8700, 101895]);
});

test('unknown promo and bad date are ignored', () => {
    const q = quote(azure, null, pricing, { hours: 3, promo: '<script>', date: '2026-02-30' });
    assert.equal(q.discount, 0);
    assert.equal(q.promo, null);
    assert.equal(q.weekend, false);
});
