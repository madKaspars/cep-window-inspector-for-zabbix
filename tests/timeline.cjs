/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
const assert = require('node:assert/strict');
const T = require('../assets/js/timeline.js');
const now = Date.UTC(2026, 9, 29, 10) / 1000;
let cases = 0;
for (const duration of [60, 900, 3600, 86400, 14 * 86400, 365 * 86400]) {
    for (const timezone of ['UTC', 'Europe/Riga', 'America/Los_Angeles', 'Asia/Kathmandu', 'Australia/Lord_Howe']) {
        for (const width of [300, 500, 900, 1800]) {
            const rows = [0.8, 0.6, 0.4, 0.2].map(age => ({clock: now - duration * age, expiry: now + duration * (1 - age)}));
            const range = T.fitRange(rows, now, duration, null);
            assert(range[0] <= rows[0].clock && range[1] >= rows[3].expiry);
            const ticks = T.ticks(range, width, timezone);
            assert(ticks.values.length > 0 && ticks.values.length <= 24);
            assert(ticks.values.every((v, i, all) => v >= range[0] && v <= range[1] && (!i || v > all[i - 1])));
            for (const row of rows) {
                const parts = T.segments(row, now, range, width);
                assert.equal(parts.length, 2);
                assert(Math.abs(parts[0].x + parts[0].width - T.position(now, range, width)) < 1e-7);
                assert(Math.abs(parts[1].x - T.position(now, range, width)) < 1e-7);
                assert(Math.abs(parts.reduce((n, p) => n + p.width, 0) - duration / (range[1] - range[0]) * width) < 1e-7);
                const recovery = (row.clock + now) / 2;
                const closed = T.segments({...row, state: 'closed', recovery}, now, range, width);
                assert.deepEqual(closed.map(p => p.kind), ['elapsed', 'recovered', 'recovered-estimate']);
                assert(Math.abs(closed[0].x + closed[0].width - T.position(recovery, range, width)) < 1e-7);
                assert(Math.abs(closed[1].x - T.position(recovery, range, width)) < 1e-7);
                assert(Math.abs(closed[1].x + closed[1].width - T.position(now, range, width)) < 1e-7);
                assert(Math.abs(closed.reduce((n, p) => n + p.width, 0) - duration / (range[1] - range[0]) * width) < 1e-7);
            }
            const zoomed = T.zoomRange(range, 0.5);
            assert(Math.abs((zoomed[1] - zoomed[0]) * 2 - (range[1] - range[0])) < 1e-5);
            const panned = T.panRange(range, 0.25);
            assert(Math.abs(panned[1] - panned[0] - (range[1] - range[0])) < 1e-5);
            cases++;
        }
    }
}
// Month boundaries must be local first-of-month midnight, including a DST offset change.
const year = [Date.UTC(2026, 0, 1) / 1000, Date.UTC(2027, 0, 1) / 1000];
for (const zone of ['Europe/Riga', 'America/Los_Angeles', 'Asia/Kathmandu', 'Australia/Lord_Howe']) {
    const ticks = T.ticks(year, 1800, zone);
    assert.equal(ticks.kind, 'month');
    for (const time of ticks.values) {
        const parts = T.civilParts(time, zone);
        assert.equal(parts.day, 1); assert.equal(parts.hour, 0); assert.equal(parts.minute, 0);
    }
}
const march = T.civilToTime(2026, 3, 29, 'Europe/Riga');
const nextDay = T.civilToTime(2026, 3, 30, 'Europe/Riga');
assert.equal(nextDay - march, 23 * 3600);
assert.deepEqual(T.segments({clock: null, expiry: now}, now, year, 900), []);
assert.deepEqual(T.segments({clock: now, expiry: null}, now, year, 900), []);
assert.deepEqual(T.segments({clock: now, expiry: now - 1}, now, year, 900), []);
const clipped = T.segments({clock: now - 20, expiry: now + 20}, now, [now - 5, now + 5], 400);
assert.deepEqual(clipped, [{kind: 'elapsed', x: 0, width: 200}, {kind: 'estimate', x: 200, width: 200}]);
assert.equal(T.segments({clock: now - 20, expiry: now - 10}, now, [now - 30, now + 30], 600).length, 1);
assert.equal(T.segments({clock: now + 10, expiry: now + 20}, now, [now - 30, now + 30], 600)[0].kind, 'estimate');
assert.deepEqual(T.fitRange([], now, null, null), [now - 32.4, now + 32.4]);
assert(!T.validTime(null)); assert(T.validTime(0)); assert(!T.validTime(NaN));
assert.deepEqual(T.normalize(-100, 100), [0, 200]);
assert.equal(T.zoomRange([now, now + 1], 0.5)[1] - T.zoomRange([now, now + 1], 0.5)[0], 1);
const recovered = {clock: now - 20, recovery: now - 10, expiry: now + 20, state: 'closed'};
assert.deepEqual(T.segments(recovered, now, [now - 5, now + 5], 400),
    [{kind: 'recovered', x: 0, width: 200}, {kind: 'recovered-estimate', x: 200, width: 200}]);
assert.deepEqual(T.segments({...recovered, recovery: null}, now, [now - 30, now + 30], 600).map(p => p.kind),
    ['recovered', 'recovered-estimate']);
assert.deepEqual(T.segments({...recovered, recovery: now + 25}, now, [now - 30, now + 30], 600).map(p => p.kind),
    ['elapsed', 'recovered-estimate']);
assert.deepEqual(T.segments({clock: 0, recovery: 0, expiry: 60, state: 'closed'}, 30, [0, 60], 600).map(p => p.kind),
    ['recovered', 'recovered-estimate']);
assert(T.fitRange([{...recovered, recovery: now + 100}], now, 40, null)[1] >= now + 100);
// The final member may be on a different cursor page. Fit must still include the whole-window boundary.
assert(T.fitRange([recovered], now, 40, null, now + 10000)[1] > now + 10000);
assert(T.fitRange([recovered], now, 40, null, now - 10000)[0] < now - 10000);
assert.deepEqual(T.fitRange([recovered], now, 40, null, null), T.fitRange([recovered], now, 40, null));
assert(T.fitRange([], 60, 60, null, 0)[0] === 0);
console.log('PASS: whole-window expiry in fitted range, off-page last member, overdue boundary, missing estimate and epoch zero.');
console.log('PASS: recovery transitions across every scale, clipping, missing timestamps, recovery after expiry and epoch zero.');
console.log(`PASS: ${cases} range/width/timezone cases; calendar month boundaries, DST, zoom/pan, clipping, missing/overdue/future events and epoch bounds.`);
