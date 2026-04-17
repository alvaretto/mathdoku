// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * MathDoku (KenKen) interactive grid renderer.
 *
 * Entry point: render(data, containerId)
 *
 * @module    mod_mathdoku/mathdoku
 * @copyright 2026 Álvaro Ángel Martínez <alvaroangelm@iepedacitodecielo.edu.co>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Render an interactive or read-only MathDoku grid.
     *
     * @param {Object} D           Puzzle data (size, cages, studentGrid, readonly, confirmMsg).
     * @param {string} containerId ID of the target container element.
     */
    function render(D, containerId) {

        var SIZE     = D.size || 9;
        var cages    = D.cages || [];
        var readonly = D.readonly || false;
        var LS_KEY   = D.attemptId ? 'mathdoku_grid_' + D.attemptId : null;

        var CAGE_COLORS = [
            '#ffd6d6', '#d6f0ff', '#d6ffd6', '#fff0d6',
            '#f0d6ff', '#ffffd6', '#d6fff0', '#f0ffd6'
        ];

        // Build cell→cage index map.
        var cellCageIdx = {};
        cages.forEach(function(cage, idx) {
            cage.cells.forEach(function(cell) {
                cellCageIdx[cell[0] + ',' + cell[1]] = idx;
            });
        });

        function cageIdxAt(r, c) {
            return cellCageIdx[r + ',' + c];
        }

        function topLeftOf(cage) {
            return cage.cells.reduce(function(best, cell) {
                if (cell[0] < best[0] || (cell[0] === best[0] && cell[1] < best[1])) {
                    return cell;
                }
                return best;
            }, cage.cells[0]);
        }

        function opSymbol(op) {
            return {'+': '+', '-': '−', '*': '×', '/': '÷'}[op] || '';
        }

        function cageBorders(r, c) {
            var my = cageIdxAt(r, c);
            return {
                top:    r === 0        || cageIdxAt(r - 1, c) !== my,
                right:  c === SIZE - 1 || cageIdxAt(r, c + 1) !== my,
                bottom: r === SIZE - 1 || cageIdxAt(r + 1, c) !== my,
                left:   c === 0        || cageIdxAt(r, c - 1) !== my
            };
        }

        function buildGrid() {
            var container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            var table = document.createElement('table');
            table.className = 'mathdoku-grid';
            table.setAttribute('role', 'grid');

            for (var r = 0; r < SIZE; r++) {
                var tr = document.createElement('tr');
                for (var c = 0; c < SIZE; c++) {
                    var td  = document.createElement('td');
                    var idx = cageIdxAt(r, c);
                    var cage = cages[idx];
                    var b   = cageBorders(r, c);

                    td.dataset.row = r;
                    td.dataset.col = c;
                    td.style.backgroundColor = CAGE_COLORS[idx % CAGE_COLORS.length];
                    td.style.borderTop    = b.top    ? '3px solid #333' : '1px solid #bbb';
                    td.style.borderRight  = b.right  ? '3px solid #333' : '1px solid #bbb';
                    td.style.borderBottom = b.bottom ? '3px solid #333' : '1px solid #bbb';
                    td.style.borderLeft   = b.left   ? '3px solid #333' : '1px solid #bbb';

                    var isGiven = (cage.given === true);
                    var tl = topLeftOf(cage);
                    if (!isGiven && tl[0] === r && tl[1] === c) {
                        var label = document.createElement('span');
                        label.className = 'cage-label';
                        label.textContent = cage.op === null
                            ? cage.target
                            : (cage.show_op ? cage.target + opSymbol(cage.op) : cage.target + '?');
                        td.appendChild(label);
                    }

                    var savedVal = (D.studentGrid && D.studentGrid[r] && D.studentGrid[r][c])
                        ? D.studentGrid[r][c] : 0;

                    if (isGiven) {
                        var gs = document.createElement('span');
                        gs.className = 'cell-given';
                        gs.textContent = cage.target;
                        td.appendChild(gs);
                        if (!readonly) {
                            var hi = document.createElement('input');
                            hi.type  = 'hidden';
                            hi.name  = 'cell[r' + r + 'c' + c + ']';
                            hi.value = cage.target;
                            td.appendChild(hi);
                        }
                    } else if (readonly) {
                        var span = document.createElement('span');
                        span.className = 'cell-value';
                        span.textContent = savedVal > 0 ? savedVal : '';
                        td.appendChild(span);
                    } else {
                        td.appendChild(makeInput(r, c, savedVal));
                    }

                    tr.appendChild(td);
                }
                table.appendChild(tr);
            }
            container.appendChild(table);
        }

        function makeInput(r, c, savedVal) {
            var input = document.createElement('input');
            input.type         = 'tel';
            input.inputMode    = 'numeric';
            input.maxLength    = 1;
            input.name         = 'cell[r' + r + 'c' + c + ']';
            input.className    = 'cell-input';
            input.value        = savedVal > 0 ? savedVal : '';
            input.autocomplete = 'off';

            input.addEventListener('input', function() {
                var v = this.value.replace(/[^1-9]/g, '');
                this.value = v.length ? v[v.length - 1] : '';
                highlightDuplicates();
                saveToLS();
                scheduleAutoSave();
            });

            input.addEventListener('keydown', function(e) {
                var row = +this.closest('td').dataset.row;
                var col = +this.closest('td').dataset.col;
                var nr = row, nc = col;
                if      (e.key === 'ArrowRight') { nc += 1; }
                else if (e.key === 'ArrowLeft')  { nc -= 1; }
                else if (e.key === 'ArrowDown')  { nr += 1; }
                else if (e.key === 'ArrowUp')    { nr -= 1; }
                else if (e.key === 'Tab') {
                    nc = e.shiftKey ? nc - 1 : nc + 1;
                    if (nc < 0)     { nc = SIZE - 1; nr -= 1; }
                    if (nc >= SIZE) { nc = 0;         nr += 1; }
                    e.preventDefault();
                } else { return; }
                if (nr >= 0 && nr < SIZE && nc >= 0 && nc < SIZE) {
                    var next = document.querySelector(
                        '#' + containerId + ' input[name="cell[r' + nr + 'c' + nc + ']"]');
                    if (next) { next.focus(); }
                }
            });

            return input;
        }

        // ── localStorage helpers ──────────────────────────────────────────────

        function readGridFromDOM() {
            var g = {};
            for (var r = 0; r < SIZE; r++) {
                g[r] = {};
                for (var c = 0; c < SIZE; c++) {
                    var inp = document.querySelector(
                        '#' + containerId + ' input[name="cell[r' + r + 'c' + c + ']"]');
                    g[r][c] = inp ? (parseInt(inp.value, 10) || 0) : 0;
                }
            }
            return g;
        }

        function saveToLS() {
            if (!LS_KEY) { return; }
            try {
                localStorage.setItem(LS_KEY, JSON.stringify({
                    grid: readGridFromDOM(),
                    ts: Date.now()
                }));
            } catch (e) {}
        }

        function loadFromLS() {
            if (!LS_KEY) { return null; }
            try {
                var raw = localStorage.getItem(LS_KEY);
                return raw ? (JSON.parse(raw).grid || null) : null;
            } catch (e) { return null; }
        }

        function gridHasValues(grid) {
            if (!grid) { return false; }
            for (var r in grid) {
                for (var c in grid[r]) {
                    if (grid[r][c] > 0) { return true; }
                }
            }
            return false;
        }

        // Use localStorage as fallback when server has no saved progress.
        if (!gridHasValues(D.studentGrid)) {
            var lsGrid = loadFromLS();
            if (gridHasValues(lsGrid)) {
                D.studentGrid = lsGrid;
            }
        }

        // ── Server save ───────────────────────────────────────────────────────

        var autoSaveTimer = null;
        function doSave() {
            var form = document.getElementById('mathdoku-form');
            if (!form) { return; }
            var fd = new FormData(form);
            fd.set('action', 'save');
            fetch(form.action, {method: 'POST', body: fd}).catch(function() {});
        }

        function scheduleAutoSave() {
            if (autoSaveTimer) { clearTimeout(autoSaveTimer); }
            autoSaveTimer = setTimeout(doSave, 2000);
        }

        function setupBeforeUnload() {
            window.addEventListener('beforeunload', function() {
                if (autoSaveTimer) { clearTimeout(autoSaveTimer); }
                saveToLS();
                var form = document.getElementById('mathdoku-form');
                if (!form) { return; }
                var fd = new FormData(form);
                fd.set('action', 'save');
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(form.action, fd);
                }
            });
        }

        function setupSubmitButton() {
            var btn = document.getElementById('btn-submit');
            if (!btn) { return; }
            btn.addEventListener('click', function() {
                var msg = D.confirmMsg || '';
                if (msg && !window.confirm(msg)) { return; }
                if (autoSaveTimer) { clearTimeout(autoSaveTimer); }
                if (LS_KEY) { try { localStorage.removeItem(LS_KEY); } catch(e) {} }
                document.getElementById('mathdoku-action').value = 'submit';
                document.getElementById('mathdoku-form').submit();
            });
        }

        function highlightDuplicates() {
            var vals = {};
            for (var r = 0; r < SIZE; r++) {
                for (var c = 0; c < SIZE; c++) {
                    var td = document.querySelector(
                        '#' + containerId + ' td[data-row="' + r + '"][data-col="' + c + '"]');
                    if (!td) { continue; }
                    var inp = td.querySelector('.cell-input');
                    var v = inp ? (parseInt(inp.value, 10) || 0) : 0;
                    if (v === 0) {
                        var hi = td.querySelector('input[type="hidden"]');
                        if (hi) { v = parseInt(hi.value, 10) || 0; }
                    }
                    vals[r + ',' + c] = v;
                }
            }
            for (var r = 0; r < SIZE; r++) {
                for (var c = 0; c < SIZE; c++) {
                    var td = document.querySelector(
                        '#' + containerId + ' td[data-row="' + r + '"][data-col="' + c + '"]');
                    if (!td) { continue; }
                    var inp = td.querySelector('.cell-input');
                    if (!inp) { continue; }
                    var v = vals[r + ',' + c];
                    inp.classList.remove('cell-error');
                    if (!v) { continue; }
                    for (var i = 0; i < SIZE; i++) {
                        if (i !== c && vals[r + ',' + i] === v) { inp.classList.add('cell-error'); break; }
                        if (i !== r && vals[i + ',' + c] === v) { inp.classList.add('cell-error'); break; }
                    }
                }
            }
        }

        buildGrid();
        if (!readonly) {
            setupSubmitButton();
            setupBeforeUnload();
            highlightDuplicates();
        }
    }

    return {render: render};
});
