/* MathDoku (KenKen) interactive grid — no dependencies
 *
 * Exposes window.mathdokuRender(data, containerId) so the PHP page can
 * call it inline after the DOM element exists, regardless of when this
 * file was loaded by the browser.
 */

/**
 * Render an interactive or read-only MathDoku grid.
 *
 * @param {Object} D           Puzzle data (size, cages, studentGrid, readonly).
 * @param {string} containerId ID of the target container element.
 */
window.mathdokuRender = function (D, containerId) {
    'use strict';

    var SIZE = D.size || 9;
    var cages = D.cages || [];
    var readonly = D.readonly || false;

    var CAGE_COLORS = [
        '#ffd6d6', '#d6f0ff', '#d6ffd6', '#fff0d6',
        '#f0d6ff', '#ffffd6', '#d6fff0', '#f0ffd6'
    ];

    // Build cell→cage index map
    var cellCageIdx = {};
    cages.forEach(function (cage, idx) {
        cage.cells.forEach(function (cell) {
            cellCageIdx[cell[0] + ',' + cell[1]] = idx;
        });
    });

    /**
     * Return the cage index at row r, column c.
     *
     * @param {number} r Row index.
     * @param {number} c Column index.
     * @returns {number} Cage index.
     */
    function cageIdxAt(r, c) {
        return cellCageIdx[r + ',' + c];
    }

    /**
     * Return the top-left cell of a cage.
     *
     * @param {Object} cage Cage object with a cells array.
     * @returns {Array} The [row, col] pair of the top-left cell.
     */
    function topLeftOf(cage) {
        return cage.cells.reduce(function (best, cell) {
            if (cell[0] < best[0] || (cell[0] === best[0] && cell[1] < best[1])) {
                return cell;
            }
            return best;
        }, cage.cells[0]);
    }

    /**
     * Return the display symbol for a cage operator.
     *
     * @param {string} op Operator character (+, -, *, /).
     * @returns {string} Unicode display symbol.
     */
    function opSymbol(op) {
        return {'+': '+', '-': '−', '*': '×', '/': '÷'}[op] || '';
    }

    /**
     * Determine which borders of cell (r, c) should be thick cage borders.
     *
     * @param {number} r Row index.
     * @param {number} c Column index.
     * @returns {Object} Object with boolean properties top, right, bottom, left.
     */
    function cageBorders(r, c) {
        var my = cageIdxAt(r, c);
        return {
            top: r === 0 || cageIdxAt(r - 1, c) !== my,
            right: c === SIZE - 1 || cageIdxAt(r, c + 1) !== my,
            bottom: r === SIZE - 1 || cageIdxAt(r + 1, c) !== my,
            left: c === 0 || cageIdxAt(r, c - 1) !== my
        };
    }

    /**
     * Build and insert the grid table into the container element.
     */
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
                var td = document.createElement('td');
                var idx = cageIdxAt(r, c);
                var cage = cages[idx];
                var b = cageBorders(r, c);

                td.dataset.row = r;
                td.dataset.col = c;
                td.style.backgroundColor = CAGE_COLORS[idx % CAGE_COLORS.length];
                td.style.borderTop = b.top ? '3px solid #333' : '1px solid #bbb';
                td.style.borderRight = b.right ? '3px solid #333' : '1px solid #bbb';
                td.style.borderBottom = b.bottom ? '3px solid #333' : '1px solid #bbb';
                td.style.borderLeft = b.left ? '3px solid #333' : '1px solid #bbb';

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
                        hi.type = 'hidden';
                        hi.name = 'cell[r' + r + 'c' + c + ']';
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

    /**
     * Create an editable input element for a grid cell.
     *
     * @param {number} r        Row index.
     * @param {number} c        Column index.
     * @param {number} savedVal Previously saved value (0 if empty).
     * @returns {HTMLElement} The configured input element.
     */
    function makeInput(r, c, savedVal) {
        var input = document.createElement('input');
        input.type = 'tel';
        input.inputMode = 'numeric';
        input.maxLength = 1;
        input.name = 'cell[r' + r + 'c' + c + ']';
        input.className = 'cell-input';
        input.value = savedVal > 0 ? savedVal : '';
        input.autocomplete = 'off';

        input.addEventListener('input', function (e) {
            var v = e.target.value.replace(/[^1-9]/g, '');
            e.target.value = v.length ? v[v.length - 1] : '';
            highlightDuplicates();
            scheduleAutoSave();
        });

        input.addEventListener('keydown', function (e) {
            var row = +e.target.closest('td').dataset.row;
            var col = +e.target.closest('td').dataset.col;
            var nr = row;
            var nc = col;
            if (e.key === 'ArrowRight') {
                nc += 1;
            } else if (e.key === 'ArrowLeft') {
                nc -= 1;
            } else if (e.key === 'ArrowDown') {
                nr += 1;
            } else if (e.key === 'ArrowUp') {
                nr -= 1;
            } else if (e.key === 'Tab') {
                nc = e.shiftKey ? nc - 1 : nc + 1;
                if (nc < 0) {
                    nc = SIZE - 1;
                    nr -= 1;
                }
                if (nc >= SIZE) {
                    nc = 0;
                    nr += 1;
                }
                e.preventDefault();
            } else {
                return;
            }
            if (nr >= 0 && nr < SIZE && nc >= 0 && nc < SIZE) {
                var next = document.querySelector('input[name="cell[r' + nr + 'c' + nc + ']"]');
                if (next) {
                    next.focus();
                }
            }
        });

        return input;
    }

    var autoSaveTimer = null;

    /**
     * Schedule a debounced auto-save 5 seconds after the last change.
     */
    function scheduleAutoSave() {
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
        }
        autoSaveTimer = setTimeout(function () {
            var form = document.getElementById('mathdoku-form');
            if (!form) {
                return;
            }
            var fd = new FormData(form);
            fd.set('action', 'save');
            fetch(form.action, {method: 'POST', body: fd}).catch(function () {
                // Ignore save errors silently.
            });
        }, 5000);
    }

    /**
     * Attach the submit-button click handler, including a confirmation prompt.
     */
    function setupSubmitButton() {
        var btn = document.getElementById('btn-submit');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            var msg = '¿Confirmas calificar ahora? No podrás modificar tus respuestas.';
            // eslint-disable-next-line no-alert
            if (!window.confirm(msg)) {
                return;
            }
            if (autoSaveTimer) {
                clearTimeout(autoSaveTimer);
            }
            document.getElementById('mathdoku-action').value = 'submit';
            document.getElementById('mathdoku-form').submit();
        });
    }

    /**
     * Scan every row and column for duplicate values and toggle cell-error class.
     */
    function highlightDuplicates() {
        // Collect current value for every cell (input or given hidden)
        var vals = {};
        for (var r = 0; r < SIZE; r++) {
            for (var c = 0; c < SIZE; c++) {
                var td = document.querySelector(
                    '#' + containerId + ' td[data-row="' + r + '"][data-col="' + c + '"]');
                if (!td) {
                    continue;
                }
                var inp = td.querySelector('.cell-input');
                var v = inp ? (parseInt(inp.value, 10) || 0) : 0;
                if (v === 0) {
                    var hi = td.querySelector('input[type="hidden"]');
                    if (hi) {
                        v = parseInt(hi.value, 10) || 0;
                    }
                }
                vals[r + ',' + c] = v;
            }
        }
        // Mark / unmark each editable cell
        for (var r2 = 0; r2 < SIZE; r2++) {
            for (var c2 = 0; c2 < SIZE; c2++) {
                var td2 = document.querySelector(
                    '#' + containerId + ' td[data-row="' + r2 + '"][data-col="' + c2 + '"]');
                if (!td2) {
                    continue;
                }
                var inp2 = td2.querySelector('.cell-input');
                if (!inp2) {
                    continue;
                }
                var v2 = vals[r2 + ',' + c2];
                inp2.classList.remove('cell-error');
                if (!v2) {
                    continue;
                }
                for (var i = 0; i < SIZE; i++) {
                    if (i !== c2 && vals[r2 + ',' + i] === v2) {
                        inp2.classList.add('cell-error');
                        break;
                    }
                    if (i !== r2 && vals[i + ',' + c2] === v2) {
                        inp2.classList.add('cell-error');
                        break;
                    }
                }
            }
        }
    }

    // Run immediately — the DOM element exists because this call comes from
    // an inline <script> placed AFTER the element in the HTML.
    buildGrid();
    if (!readonly) {
        setupSubmitButton();
        highlightDuplicates(); // Mark any conflicts already in saved progress
    }
};
