import { test } from 'node:test';
import assert from 'node:assert/strict';
import { icsDocument, icsText } from '../../resources/js/lib/ics.js';

test('icsText escapes RFC 5545 special characters', () => {
    assert.equal(icsText('Sunset, cruise; fun\nline \\ end'), 'Sunset\\, cruise\\; fun\\nline \\\\ end');
    assert.equal(icsText(null), '');
});

test('icsDocument builds one event and cannot be broken by injected lines', () => {
    const doc = icsDocument(
        {
            ref: 'CYB-8M2K4Q',
            domain: 'chicagoyachtbooking.com',
            start: '20260810T180000',
            end: '20260810T210000\r\nX-EVIL:1',
            summary: 'Sunset Cruise\r\nATTENDEE:mailto:x@evil.com',
            location: 'DuSable Harbor, Chicago, IL',
            description: 'Booking CYB-8M2K4Q',
        },
        new Date('2026-01-01T00:00:00Z'),
    );
    const lines = doc.split('\r\n');
    assert.equal(lines[0], 'BEGIN:VCALENDAR');
    assert.equal(lines.at(-1), 'END:VCALENDAR');
    assert.ok(lines.includes('DTSTAMP:20260101T000000Z'));
    assert.ok(lines.includes('DTSTART;TZID=America/Chicago:20260810T180000'));
    assert.ok(lines.includes('LOCATION:DuSable Harbor\\, Chicago\\, IL'));
    assert.ok(!lines.some((l) => l.startsWith('X-EVIL') || l.startsWith('ATTENDEE')), 'no injected properties');
    assert.equal(lines.filter((l) => l === 'BEGIN:VEVENT').length, 1);
});
