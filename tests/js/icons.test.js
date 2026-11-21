import { test } from 'node:test';
import assert from 'node:assert/strict';
import { icons, iconNames, toIconName } from '../../resources/js/lib/icons.js';

test('every registered icon resolved to real icon data (catches renamed Lucide exports)', () => {
    for (const [name, node] of Object.entries(icons)) {
        assert.ok(Array.isArray(node) && node.length > 0, `${name} is missing from lucide`);
        for (const [tag, attrs] of node) {
            assert.equal(typeof tag, 'string', name);
            assert.equal(typeof attrs, 'object', name);
        }
    }
});

test('export names map to the data-lucide names used in markup', () => {
    assert.equal(toIconName('Building2'), 'building-2');
    assert.equal(toIconName('Share2'), 'share-2');
    assert.equal(toIconName('BadgeDollarSign'), 'badge-dollar-sign');
    assert.equal(toIconName('X'), 'x');
});

test('all icons the mockup uses are registered', () => {
    const mockup = `anchor arrow-left arrow-right arrow-up-right badge-dollar-sign bath bed-double briefcase building-2
        calendar-check calendar-days calendar-plus calendar-x camera check chevron-down chevron-left chevron-right
        circle-alert circle-check circle-plus clipboard-check clock cloud-lightning cloud-sun compass copy credit-card
        external-link facebook file-text file-up gift hand-coins heart heart-handshake history image-off image-plus images
        info instagram landmark life-buoy lightbulb list-checks lock mail mail-check map-pin menu message-circle minus
        music navigation party-popper pause pencil phone play plus printer quote ruler sailboat search send share-2
        shield-check ship-wheel snowflake sparkles star sun sunset tag timer triangle-alert upload user-plus user-round
        users utensils waves wine x youtube zap`.split(/\s+/);
    const missing = mockup.filter((name) => !iconNames.includes(name));
    assert.deepEqual(missing, []);
});
