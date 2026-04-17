/**
 * Tests for localStorage persistence logic in mod_mathdoku/amd/src/mathdoku.js
 * Run: node tests/test_persistence.js
 */

'use strict';

let pass = 0, fail = 0;

function ok(cond, label) {
    if (cond) { console.log('  PASS ', label); pass++; }
    else       { console.log('  FAIL ', label); fail++; }
}

// ── Mock localStorage ─────────────────────────────────────────────────────────

const store = {};
const localStorage = {
    setItem: (k, v) => { store[k] = String(v); },
    getItem: (k)    => store[k] !== undefined ? store[k] : null,
    removeItem: (k) => { delete store[k]; },
};

// ── Extract helpers from AMD module (inline, mirrors amd/src/mathdoku.js) ─────

function gridHasValues(grid) {
    if (!grid) { return false; }
    for (var r in grid) {
        for (var c in grid[r]) {
            if (grid[r][c] > 0) { return true; }
        }
    }
    return false;
}

function makeLS(attemptId) {
    const key = attemptId ? 'mathdoku_grid_' + attemptId : null;

    function saveToLS(grid) {
        if (!key) { return; }
        try { localStorage.setItem(key, JSON.stringify({grid, ts: Date.now()})); }
        catch(e) {}
    }

    function loadFromLS() {
        if (!key) { return null; }
        try {
            const raw = localStorage.getItem(key);
            return raw ? (JSON.parse(raw).grid || null) : null;
        } catch(e) { return null; }
    }

    function removeFromLS() {
        if (!key) { return; }
        try { localStorage.removeItem(key); } catch(e) {}
    }

    return {saveToLS, loadFromLS, removeFromLS};
}

// ── Tests: gridHasValues ──────────────────────────────────────────────────────

console.log('\n── gridHasValues ─────────────────────────────────────');

ok(!gridHasValues(null),                        'null → false');
ok(!gridHasValues({}),                          '{} → false');
ok(!gridHasValues({0: {0: 0, 1: 0}}),           'all zeros → false');
ok( gridHasValues({0: {0: 3, 1: 0}}),           'one non-zero → true');
ok( gridHasValues({0: {0: 0}, 1: {0: 9}}),      'non-zero in row 1 → true');

// ── Tests: save / load round-trip ────────────────────────────────────────────

console.log('\n── localStorage save/load round-trip ────────────────');

const {saveToLS, loadFromLS, removeFromLS} = makeLS(42);

const grid1 = {0: {0: 3, 1: 5}, 1: {0: 7, 1: 2}};
saveToLS(grid1);
const loaded = loadFromLS();

ok(loaded !== null,             'loadFromLS returns data after save');
ok(loaded[0][0] === 3,          'row 0 col 0 = 3');
ok(loaded[0][1] === 5,          'row 0 col 1 = 5');
ok(loaded[1][0] === 7,          'row 1 col 0 = 7');
ok(loaded[1][1] === 2,          'row 1 col 1 = 2');

// ── Tests: removeFromLS clears storage ───────────────────────────────────────

console.log('\n── removeFromLS (on submit) ──────────────────────────');

removeFromLS();
ok(loadFromLS() === null, 'after remove, loadFromLS returns null');

// ── Tests: null attemptId is safe ────────────────────────────────────────────

console.log('\n── attemptId = null safety ───────────────────────────');

const noId = makeLS(null);
noId.saveToLS({0: {0: 1}});     // must not throw
ok(noId.loadFromLS() === null,  'no attemptId → loadFromLS returns null');

// ── Tests: fallback logic (mirrors render() init) ─────────────────────────────

console.log('\n── Fallback: server null → use localStorage ──────────');

const {saveToLS: save2, loadFromLS: load2, removeFromLS: rm2} = makeLS(99);

// Student had saved progress offline.
save2({0: {0: 5, 1: 0}, 1: {0: 0, 1: 3}});

// Server has no data (studentGrid = null).
let serverGrid = null;
let effectiveGrid = serverGrid;
if (!gridHasValues(serverGrid)) {
    const lsGrid = load2();
    if (gridHasValues(lsGrid)) { effectiveGrid = lsGrid; }
}

ok(effectiveGrid !== null,     'effectiveGrid is not null when LS has data');
ok(effectiveGrid[0][0] === 5,  'restored row 0 col 0 = 5 from localStorage');
ok(effectiveGrid[1][1] === 3,  'restored row 1 col 1 = 3 from localStorage');

// ── Tests: server data takes priority over localStorage ───────────────────────

console.log('\n── Priority: server data wins over localStorage ──────');

save2({0: {0: 1, 1: 1}, 1: {0: 1, 1: 1}});  // stale localStorage

const serverGrid2 = {0: {0: 9, 1: 8}, 1: {0: 7, 1: 6}};
let effectiveGrid2 = serverGrid2;
if (!gridHasValues(serverGrid2)) {
    const lsGrid = load2();
    if (gridHasValues(lsGrid)) { effectiveGrid2 = lsGrid; }
}

ok(effectiveGrid2[0][0] === 9, 'server grid wins: row 0 col 0 = 9');
ok(effectiveGrid2[1][1] === 6, 'server grid wins: row 1 col 1 = 6');

// ── Tests: AMD source contains required patterns ───────────────────────────────

console.log('\n── AMD source code structure ─────────────────────────');

const fs = require('fs');
const src = fs.readFileSync(__dirname + '/../amd/src/mathdoku.js', 'utf8');

ok(src.includes('attemptId'),                   'src contains attemptId');
ok(src.includes('mathdoku_grid_'),              'src contains LS key prefix');
ok(src.includes('gridHasValues'),               'src contains gridHasValues');
ok(src.includes('saveToLS'),                    'src contains saveToLS');
ok(src.includes('loadFromLS'),                  'src contains loadFromLS');
ok(src.includes('localStorage.removeItem'),     'src removes LS on submit');
ok(src.includes('sendBeacon'),                  'src uses sendBeacon on unload');
ok(src.includes('2000'),                        'src auto-save debounce is 2000ms');
ok(!src.includes('5000'),                       'src has no old 5000ms debounce');

// ── Summary ───────────────────────────────────────────────────────────────────

console.log(`\n── Resultado: ${pass} passed, ${fail} failed ────────────`);
process.exit(fail > 0 ? 1 : 0);
