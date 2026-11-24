import { test } from 'node:test';
import assert from 'node:assert/strict';
import { amount, lineCount, RULES, startTimesSummary, timeLabel } from '../../resources/js/pages/admin-boat-form.js';

test('start times read like the server summary (StartTimes::summary)', () => {
    assert.equal(timeLabel('05:00'), '5:00 AM');
    assert.equal(timeLabel('12:30'), '12:30 PM');
    assert.equal(timeLabel('22:00'), '10:00 PM');
    assert.equal(startTimesSummary(['08:00', '08:30', '09:00']), '8:00 AM – 9:00 AM');
    assert.equal(startTimesSummary(['17:30', '10:00', '18:00', '18:30']), '10:00 AM, 5:30 PM – 6:30 PM');
    assert.equal(startTimesSummary([]), '');
});

test('lineCount counts the non-empty lines, like the server one-per-line lists', () => {
    assert.equal(lineCount(''), 0);
    assert.equal(lineCount('Generator\n\n  \nFull galley\r\nSwim platform'), 3);
    assert.equal(lineCount('  one  '), 1);
});

test('amount takes plain numbers only, as the server numeric rule does', () => {
    assert.equal(amount('450'), 450);
    assert.equal(amount(' 450.50 '), 450.5);
    assert.equal(amount('0'), 0);
    for (const bad of ['', '$450', '1,250', '-5', 'abc', '4 50']) {
        assert.ok(Number.isNaN(amount(bad)), bad);
    }
});

test('every live rule matches a checklist key of BoatController', () => {
    assert.deepEqual(Object.keys(RULES).sort(), ['description', 'experiences', 'harbor', 'highlights', 'hours', 'photos', 'policy', 'price', 'specs']);
});

test('photos counts the saved photos plus the ones chosen to go up with the save', () => {
    const form = (chosen) => ({ querySelector: () => ({ files: { length: chosen } }) });
    const card = { dataset: { photoCount: '2', minPhotos: '4' } };
    assert.equal(RULES.photos(form(1), card), false);
    assert.equal(RULES.photos(form(2), card), true);
    assert.equal(RULES.photos({ querySelector: () => null }, { dataset: { photoCount: '4', minPhotos: '4' } }), true);
});
