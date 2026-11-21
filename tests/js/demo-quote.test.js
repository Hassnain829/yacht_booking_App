import { test } from 'node:test';
import assert from 'node:assert/strict';
import { quote, durationsFor } from '../../resources/js/lib/demo-quote.js';

// Same data as config/demo.php and the same scenarios as tests/Unit/DemoQuoteTest.php.
const pricing = {
    weekend_multiplier_bp: 11500,
    tax_rate_bp: 900,
    deposit_bp: 5000,
    promos: [
        { code: 'WELCOME10', type: 'percent', value: 10 },
        { code: 'SUNSET50', type: 'fixed', value: 5000 },
    ],
};
const azure = { min_hours: 3, hourly_rate_cents: 45000, captain_rate_cents: 8500, crew_count: 1, cleaning_fee_cents: 15000 };
const windward = { min_hours: 2, hourly_rate_cents: 22500, captain_rate_cents: 6500, crew_count: 1, cleaning_fee_cents: 7500 };
const lakeside = { min_hours: 3 };
const privateExp = { name: 'Private Day Charter', package_fee_cents: 0, durations: [2, 3, 4, 6, 8] };
const playpen = { name: 'Playpen Swim Party', package_fee_cents: 15000, durations: [3, 4, 5] };
const fireworks = { name: 'Navy Pier Fireworks', package_fee_cents: 25000, durations: [2, 3] };
const sunset = { name: 'Sunset Skyline Cruise', package_fee_cents: 0, durations: [2, 3, 4] };
const catering = { slug: 'catering', name: 'Chef-curated catering', price_cents: 3500, unit: 'per_guest' };
const champagne = { slug: 'champagne', name: 'Champagne toast', price_cents: 12000, unit: 'flat' };

const pick = (q, keys) => keys.map((k) => q[k]);

test('weekday private charter', () => {
    const q = quote(azure, privateExp, pricing, { date: '2026-11-23', hours: 3, guests: 10 });
    assert.equal(q.weekend, false);
    assert.deepEqual(pick(q, ['charter', 'captain', 'subtotal', 'discount', 'tax', 'gratuity', 'total', 'deposit']), [135000, 25500, 175500, 0, 15795, 0, 191295, 95648]);
    assert.deepEqual(q.lines.map((l) => l.key), ['charter', 'captain', 'cleaning']);
});

test('weekend + package + add-ons + percent promo + gratuity, paid in full', () => {
    const q = quote(azure, playpen, pricing, { date: '2026-11-21', hours: 4, guests: 10, addons: [catering, champagne], promo: ' welcome10 ', gratuity: 18, plan: 'full' });
    assert.equal(q.weekend, true);
    assert.equal(q.rate, 51800);
    assert.deepEqual(pick(q, ['charter', 'captain', 'subtotal', 'discount', 'tax', 'gratuity', 'total', 'dueNow', 'balance']), [207200, 34000, 318200, 20720, 26773, 43416, 367669, 367669, 0]);
    assert.equal(q.lines.find((l) => l.key === 'addon-catering').sub, '$35 × 10 guests');
});

test('fixed promo with deposit', () => {
    const q = quote(windward, fireworks, pricing, { date: '2026-11-25', hours: 2, guests: 6, promo: 'SUNSET50', gratuity: 15 });
    assert.deepEqual(pick(q, ['charter', 'captain', 'subtotal', 'discount', 'tax', 'gratuity', 'total', 'deposit', 'balance']), [45000, 13000, 90500, 5000, 7695, 8700, 101895, 50948, 50947]);
});

test('unknown promo and bad date are ignored', () => {
    const q = quote(azure, null, pricing, { hours: 3, promo: '<script>', date: '2026-02-30' });
    assert.equal(q.discount, 0);
    assert.equal(q.promo, null);
    assert.equal(q.weekend, false);
});

test('durations never go below the boat minimum', () => {
    assert.deepEqual(durationsFor(lakeside, sunset), [3, 4]);
    assert.deepEqual(durationsFor(windward, privateExp), [2, 3, 4, 6, 8]);
    assert.deepEqual(durationsFor(azure, null), [3]);
});
