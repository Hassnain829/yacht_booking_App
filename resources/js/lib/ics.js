/**
 * Calendar (.ics) download for a trip. Text values are escaped per RFC 5545.
 * Times are Chicago wall-clock times ("YYYYMMDDTHHMMSS") from the server.
 */
export const icsText = (value) => String(value ?? '').replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n');

/** @param {{ ref: string, domain: string, start: string, end: string, summary: string, location: string, description: string }} e */
export function icsDocument(e, now = new Date()) {
    return [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Chicago Yacht Booking//EN',
        'BEGIN:VEVENT',
        `UID:${icsText(e.ref)}@${icsText(e.domain)}`,
        `DTSTAMP:${now.toISOString().replace(/[-:]/g, '').split('.')[0]}Z`,
        `DTSTART;TZID=America/Chicago:${String(e.start).replace(/[^0-9T]/g, '')}`,
        `DTEND;TZID=America/Chicago:${String(e.end).replace(/[^0-9T]/g, '')}`,
        `SUMMARY:${icsText(e.summary)}`,
        `LOCATION:${icsText(e.location)}`,
        `DESCRIPTION:${icsText(e.description)}`,
        'END:VEVENT',
        'END:VCALENDAR',
    ].join('\r\n');
}

export function downloadIcs(e) {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([icsDocument(e)], { type: 'text/calendar' }));
    link.download = `${String(e.ref).replace(/[^A-Za-z0-9-]/g, '') || 'trip'}.ics`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
}
