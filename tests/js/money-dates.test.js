import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatCents, formatBp } from '../../resources/js/lib/money.js';
import {
    isIsoDate,
    parseIsoDate,
    addDays,
    isWeekend,
    todayInChicago,
    formatDate,
    formatTime,
    addHoursToTime,
    formatDateTimeInChicago,
} from '../../resources/js/lib/dates.js';

test('formatCents', () => {
    assert.equal(formatCents(125000), '$1,250');
    assert.equal(formatCents(125050), '$1,250.50');
    assert.equal(formatCents(99), '$0.99');
    assert.equal(formatCents(0), '$0');
    assert.equal(formatCents(-5000), '-$50');
    assert.equal(formatCents(-0.4), '$0');
    assert.equal(formatCents(125000, { cents: 'always' }), '$1,250.00');
    assert.equal(formatCents(125050, { cents: 'never' }), '$1,251');
    assert.equal(formatCents('abc'), '$0');
    assert.equal(formatCents(165000000), '$1,650,000');
});

test('formatBp', () => {
    assert.equal(formatBp(2000), '20%');
    assert.equal(formatBp(1250), '12.5%');
    assert.equal(formatBp(1205), '12.05%');
    assert.equal(formatBp(900), '9%');
    assert.equal(formatBp(0), '0%');
});

test('ISO dates: validation, parsing, adding days across months/years', () => {
    assert.ok(isIsoDate('2026-02-28'));
    assert.ok(!isIsoDate('2026-02-30'));
    assert.ok(!isIsoDate('2026-2-3'));
    assert.ok(!isIsoDate('<script>'));
    assert.throws(() => parseIsoDate('2026-13-01'), RangeError);
    assert.equal(addDays('2026-12-31', 1), '2027-01-01');
    assert.equal(addDays('2026-03-01', -1), '2026-02-28');
    assert.equal(addDays('2026-03-07', 2), '2026-03-09'); // across the US DST change
});

test('weekend pricing days are Fri, Sat, Sun', () => {
    // 2026-11-20 is a Friday
    assert.deepEqual(
        ['2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22', '2026-11-23'].map(isWeekend),
        [false, true, true, true, false],
    );
});

test('todayInChicago uses Chicago time, not the visitor timezone', () => {
    assert.equal(todayInChicago(new Date('2026-07-01T03:00:00Z')), '2026-06-30'); // 10 PM CDT
    assert.equal(todayInChicago(new Date('2026-07-01T06:00:00Z')), '2026-07-01'); // 1 AM CDT
    assert.equal(todayInChicago(new Date('2026-01-15T05:30:00Z')), '2026-01-14'); // 11:30 PM CST
});

test('formatting dates and times', () => {
    assert.equal(formatDate('2026-11-21'), 'Sat, Nov 21, 2026');
    assert.equal(formatTime('17:30'), '5:30 PM');
    assert.equal(formatTime('00:05'), '12:05 AM');
    assert.equal(formatTime('12:00'), '12:00 PM');
    assert.throws(() => formatTime('25:00'), RangeError);
    assert.equal(addHoursToTime('17:30', 3), '20:30');
    assert.equal(addHoursToTime('22:00', 3), '01:00');
    assert.equal(addHoursToTime('09:00', 2.5), '11:30');
    assert.match(formatDateTimeInChicago('2026-07-04T01:30:00Z'), /^Jul 3, 2026, 8:30\sPM$/u);
    assert.throws(() => formatDateTimeInChicago('nope'), RangeError);
});
