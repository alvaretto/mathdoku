<?php
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
 * Internal library functions for mod_mathdoku.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Molina <luisernestomarceloberni@gmail.com>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/puzzle_generator.php');

// ── Attempt management ────────────────────────────────────────────────────────

/**
 * Return the current in-progress attempt for a user, or null.
 *
 * @param stdClass $mathdoku The mathdoku instance.
 * @param int      $userid
 * @return stdClass|null
 */
function mathdoku_get_inprogress_attempt(stdClass $mathdoku, int $userid): ?stdClass {
    global $DB;
    return $DB->get_record('mathdoku_attempts', [
        'mathdokuid' => $mathdoku->id,
        'userid'     => $userid,
        'state'      => 'inprogress',
    ]) ?: null;
}

/**
 * Return the most recent finished attempt for a user, or null.
 *
 * @param stdClass $mathdoku The mathdoku instance.
 * @param int      $userid
 * @return stdClass|null
 */
function mathdoku_get_latest_finished(stdClass $mathdoku, int $userid): ?stdClass {
    global $DB;
    $rows = $DB->get_records('mathdoku_attempts', [
        'mathdokuid' => $mathdoku->id,
        'userid'     => $userid,
        'state'      => 'finished',
    ], 'attempt DESC', '*', 0, 1);
    return $rows ? reset($rows) : null;
}

/**
 * Return the total number of attempts (any state) for a user.
 *
 * @param stdClass $mathdoku
 * @param int      $userid
 * @return int
 */
function mathdoku_count_attempts(stdClass $mathdoku, int $userid): int {
    global $DB;
    return (int) $DB->count_records('mathdoku_attempts', [
        'mathdokuid' => $mathdoku->id,
        'userid'     => $userid,
    ]);
}

/**
 * Return true if the user is allowed to start a new attempt.
 *
 * @param stdClass $mathdoku
 * @param int      $userid
 * @return bool
 */
function mathdoku_can_start_new(stdClass $mathdoku, int $userid): bool {
    if (mathdoku_get_inprogress_attempt($mathdoku, $userid)) {
        return false;
    }
    $max = (int) $mathdoku->maxattempts;
    return $max === 0 || mathdoku_count_attempts($mathdoku, $userid) < $max;
}

/**
 * Create and persist a new attempt, generating a fresh puzzle for the user.
 *
 * @param stdClass $mathdoku
 * @param int      $userid
 * @return stdClass The newly created attempt record.
 */
function mathdoku_start_attempt(stdClass $mathdoku, int $userid): stdClass {
    global $DB;

    $attempt_number = mathdoku_count_attempts($mathdoku, $userid) + 1;
    $seed = abs(crc32($mathdoku->id . '_' . $userid . '_' . $mathdoku->timemodified . '_' . $attempt_number));

    $generator = new \mod_mathdoku\puzzle_generator((int) $mathdoku->difficulty);
    $puzzle    = $generator->generate($seed);

    $cage_data = array_map(fn($cage) => [
        'cells'   => $cage['cells'],
        'op'      => $cage['op'],
        'target'  => $cage['target'],
        'show_op' => $cage['show_op'],
        'given'   => $cage['given'] ?? false,
    ], $puzzle['cages']);

    $attempt               = new stdClass();
    $attempt->mathdokuid   = $mathdoku->id;
    $attempt->userid       = $userid;
    $attempt->attempt      = $attempt_number;
    $attempt->puzzleseed   = $seed;
    $attempt->puzzledata   = json_encode(['size' => $puzzle['size'], 'cages' => $cage_data]);
    $attempt->solution     = json_encode($puzzle['solution']);
    $attempt->studentgrid  = null;
    $attempt->timecreated  = time();
    $attempt->timefinished = 0;
    $attempt->state        = 'inprogress';
    $attempt->grade        = null;
    $attempt->correct      = null;

    $attempt->id = $DB->insert_record('mathdoku_attempts', $attempt);
    return $attempt;
}

/**
 * Grade and finalise the current in-progress attempt from POST data.
 *
 * Precondition: require_sesskey() must have been called by the caller.
 *
 * @param stdClass $mathdoku
 * @param int      $userid
 */
function mathdoku_process_submission(stdClass $mathdoku, int $userid): void {
    global $DB;

    $attempt = mathdoku_get_inprogress_attempt($mathdoku, $userid);
    if (!$attempt) {
        return;
    }

    $puzzle_data  = json_decode($attempt->puzzledata, true);
    $size         = (int) $puzzle_data['size'];
    $raw          = optional_param_array('cell', [], PARAM_INT);
    $student_grid = mathdoku_rebuild_grid($raw, $size);
    $solution     = json_decode($attempt->solution, true);
    $correct      = ($student_grid === $solution);

    $attempt->studentgrid  = json_encode($student_grid);
    $attempt->timefinished = time();
    $attempt->state        = 'finished';
    $attempt->grade        = $correct ? 100.0 : 0.0;
    $attempt->correct      = $correct ? 1 : 0;

    $DB->update_record('mathdoku_attempts', $attempt);
    mathdoku_update_grades($mathdoku, $userid);
}

/**
 * Save the student's current grid progress without grading.
 *
 * Precondition: require_sesskey() must have been called by the caller.
 *
 * @param stdClass $mathdoku
 * @param int      $userid
 */
function mathdoku_save_progress(stdClass $mathdoku, int $userid): void {
    global $DB;

    $attempt = mathdoku_get_inprogress_attempt($mathdoku, $userid);
    if (!$attempt) {
        return;
    }

    $puzzle_data          = json_decode($attempt->puzzledata, true);
    $size                 = (int) $puzzle_data['size'];
    $raw                  = optional_param_array('cell', [], PARAM_INT);
    $attempt->studentgrid = json_encode(mathdoku_rebuild_grid($raw, $size));
    $DB->update_record('mathdoku_attempts', $attempt);
}

/**
 * Rebuild a 2D grid array from the flat POST cell array.
 *
 * @param array $raw  Associative array with keys like "r0c0".
 * @param int   $size Grid dimension.
 * @return array 2D array [row][col] => digit (0 if not set).
 */
function mathdoku_rebuild_grid(array $raw, int $size): array {
    $grid = [];
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            $key          = "r{$r}c{$c}";
            $grid[$r][$c] = isset($raw[$key]) ? (int) $raw[$key] : 0;
        }
    }
    return $grid;
}

// ── Shared grid helpers ───────────────────────────────────────────────────────

/**
 * Derive a cell→cageIndex lookup from the cages array.
 *
 * @param array $cages
 * @return array  Key "r,c" => cage index.
 */
function mathdoku_build_cage_index(array $cages): array {
    $idx = [];
    foreach ($cages as $i => $cage) {
        foreach ($cage['cells'] as [$r, $c]) {
            $idx["$r,$c"] = $i;
        }
    }
    return $idx;
}

/**
 * Return the top-left cell of a cage (smallest row, then smallest column).
 *
 * @param array $cage
 * @return array [row, col]
 */
function mathdoku_cage_topleft(array $cage): array {
    $best = $cage['cells'][0];
    foreach ($cage['cells'] as $cell) {
        if ($cell[0] < $best[0] || ($cell[0] === $best[0] && $cell[1] < $best[1])) {
            $best = $cell;
        }
    }
    return $best;
}

/**
 * Return the Unicode symbol for a cage operation.
 *
 * @param string|null $op
 * @return string
 */
function mathdoku_op_symbol(?string $op): string {
    return match ($op) { '+' => '+', '-' => '−', '*' => '×', '/' => '÷', default => '' };
}

// ── Interactive puzzle (AMD-rendered) ─────────────────────────────────────────

/**
 * Output the HTML scaffold for the interactive puzzle and schedule the AMD render call.
 *
 * @param stdClass $mathdoku
 * @param stdClass $attempt
 * @param stdClass $cm       Course-module record.
 */
function mathdoku_render_puzzle(stdClass $mathdoku, stdClass $attempt, stdClass $cm): void {
    global $PAGE, $OUTPUT;

    $puzzle_data  = json_decode($attempt->puzzledata, true);
    $student_grid = $attempt->studentgrid ? json_decode($attempt->studentgrid, true) : null;
    $action_url   = (new moodle_url('/mod/mathdoku/view.php', ['id' => $cm->id]))->out(false);

    $max           = (int) $mathdoku->maxattempts;
    $attempt_label = $max === 0
        ? get_string('attemptunlimited', 'mathdoku', $attempt->attempt)
        : get_string('attemptof', 'mathdoku', ['current' => $attempt->attempt, 'max' => $max]);

    // Pass confirmMsg so JS does not contain hardcoded strings.
    $js_data = [
        'size'        => $puzzle_data['size'],
        'cages'       => $puzzle_data['cages'],
        'studentGrid' => $student_grid,
        'readonly'    => false,
        'confirmMsg'  => get_string('confirmsubmit', 'mathdoku'),
        'attemptId'   => (int) $attempt->id,
    ];

    $PAGE->requires->js_call_amd('mod_mathdoku/mathdoku', 'render',
        [$js_data, 'mathdoku-grid-container']);

    echo '<p class="mathdoku-attempt-info text-muted">' . $attempt_label . '</p>';
    echo '<div id="mathdoku-wrapper">';
    echo '<form id="mathdoku-form" method="post" action="' . s($action_url) . '">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" id="mathdoku-action" value="save">';
    echo '<div id="mathdoku-grid-container"></div>';
    echo '<div class="mathdoku-controls">';
    echo '<button type="button" id="btn-submit" class="btn btn-primary btn-lg">'
        . get_string('submitpuzzle', 'mathdoku') . '</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}

// ── Read-only grid (PHP-rendered, no JS required) ─────────────────────────────

/**
 * Render a complete read-only grid as an HTML table.
 *
 * Used on the result page to show the student's submission or the solution.
 *
 * @param array $puzzle_data Decoded puzzle JSON (size, cages).
 * @param array $value_grid  2D array [row][col] => digit.
 */
function mathdoku_render_readonly_grid(array $puzzle_data, array $value_grid): void {
    $size   = (int) $puzzle_data['size'];
    $cages  = $puzzle_data['cages'];
    $ci     = mathdoku_build_cage_index($cages);

    $colors = ['#ffd6d6','#d6f0ff','#d6ffd6','#fff0d6','#f0d6ff','#ffffd6','#d6fff0','#f0ffd6'];

    echo '<table class="mathdoku-grid" role="grid">';
    for ($r = 0; $r < $size; $r++) {
        echo '<tr>';
        for ($c = 0; $c < $size; $c++) {
            $idx   = $ci["$r,$c"] ?? 0;
            $cage  = $cages[$idx];
            $color = $colors[$idx % count($colors)];

            $top    = ($r === 0 || ($ci["$r,$c"] ?? -1) !== ($ci[($r-1).",$c"] ?? -2)) ? '3px solid #333' : '1px solid #bbb';
            $right  = ($c === $size-1 || ($ci["$r,$c"] ?? -1) !== ($ci["$r,".($c+1)] ?? -2)) ? '3px solid #333' : '1px solid #bbb';
            $bottom = ($r === $size-1 || ($ci["$r,$c"] ?? -1) !== ($ci[($r+1).",$c"] ?? -2)) ? '3px solid #333' : '1px solid #bbb';
            $left   = ($c === 0 || ($ci["$r,$c"] ?? -1) !== ($ci["$r,".($c-1)] ?? -2)) ? '3px solid #333' : '1px solid #bbb';

            $style = "background:{$color};border-top:{$top};border-right:{$right};border-bottom:{$bottom};border-left:{$left};";

            $isGiven = !empty($cage['given']);
            $tl      = mathdoku_cage_topleft($cage);
            $label   = '';
            if (!$isGiven && $tl[0] === $r && $tl[1] === $c) {
                $label = $cage['show_op']
                    ? $cage['target'] . mathdoku_op_symbol($cage['op'])
                    : $cage['target'] . '?';
            }

            $val = ($value_grid[$r][$c] ?? 0) > 0 ? $value_grid[$r][$c] : '';

            echo '<td style="' . $style . '">';
            if ($isGiven) {
                echo '<span class="cell-given">' . (int) $cage['target'] . '</span>';
            } else {
                if ($label !== '') {
                    echo '<span class="cage-label">' . htmlspecialchars($label) . '</span>';
                }
                echo '<span class="cell-value">' . htmlspecialchars((string)$val) . '</span>';
            }
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
}

// ── Result page ───────────────────────────────────────────────────────────────

/**
 * Render the result page after a student submits their attempt.
 *
 * Shows correctness, score, attempt history, submitted grid, optional solution,
 * and a retry button if more attempts are allowed.
 *
 * @param stdClass $mathdoku
 * @param stdClass $attempt
 * @param stdClass $cm
 */
function mathdoku_render_result(stdClass $mathdoku, stdClass $attempt, stdClass $cm): void {
    global $OUTPUT, $DB;

    if ($attempt->correct) {
        echo $OUTPUT->notification(get_string('correct', 'mathdoku'), 'success');
    } else {
        echo $OUTPUT->notification(get_string('incorrect', 'mathdoku'), 'warning');
    }

    echo '<p class="mathdoku-score">'
        . get_string('youscored', 'mathdoku', number_format($attempt->grade * 5 / 100, 1))
        . '</p>';

    $all_finished = $DB->get_records('mathdoku_attempts', [
        'mathdokuid' => $mathdoku->id,
        'userid'     => $attempt->userid,
        'state'      => 'finished',
    ], 'attempt ASC');

    if (count($all_finished) > 1) {
        echo mathdoku_render_attempts_summary($mathdoku, array_values($all_finished));
    }

    if ($attempt->studentgrid) {
        $puzzle_data  = json_decode($attempt->puzzledata, true);
        $student_grid = json_decode($attempt->studentgrid, true);
        echo $OUTPUT->heading(get_string('yoursubmission', 'mathdoku'), 4);
        mathdoku_render_readonly_grid($puzzle_data, $student_grid);
    }

    if ($mathdoku->showsolution && !$attempt->correct) {
        $puzzle_data = json_decode($attempt->puzzledata, true);
        $solution    = json_decode($attempt->solution, true);
        echo $OUTPUT->heading(get_string('solution', 'mathdoku'), 4);
        mathdoku_render_readonly_grid($puzzle_data, $solution);
    }

    if (mathdoku_can_start_new($mathdoku, $attempt->userid)) {
        echo '<div class="mathdoku-controls">';
        echo '<form method="post" action="' . s((new moodle_url('/mod/mathdoku/view.php', ['id' => $cm->id]))->out(false)) . '">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="action" value="newattempt">';
        echo '<button type="submit" class="btn btn-secondary">'
            . get_string('newattempt', 'mathdoku') . '</button>';
        echo '</form>';
        echo '</div>';
    }
}

/**
 * Build and return an HTML summary table of all finished attempts.
 *
 * @param stdClass $mathdoku
 * @param array    $finished_attempts Ordered array of finished attempt records.
 * @return string HTML fragment.
 */
function mathdoku_render_attempts_summary(stdClass $mathdoku, array $finished_attempts): string {
    $method_labels = [
        MATHDOKU_GRADE_BEST    => get_string('grademethod_best',    'mathdoku'),
        MATHDOKU_GRADE_AVERAGE => get_string('grademethod_average', 'mathdoku'),
        MATHDOKU_GRADE_LAST    => get_string('grademethod_last',    'mathdoku'),
        MATHDOKU_GRADE_FIRST   => get_string('grademethod_first',   'mathdoku'),
    ];

    $grades = array_column($finished_attempts, 'grade');
    $method = (int) $mathdoku->grademethod;
    $final  = match ($method) {
        MATHDOKU_GRADE_AVERAGE => array_sum($grades) / count($grades),
        MATHDOKU_GRADE_LAST    => (float) end($grades),
        MATHDOKU_GRADE_FIRST   => (float) reset($grades),
        default                => max($grades),
    };

    $html  = '<div class="mathdoku-summary"><table class="generaltable">';
    $html .= '<thead><tr>'
           . '<th>' . get_string('attempt', 'mathdoku') . '</th>'
           . '<th>' . get_string('grade') . '</th>'
           . '</tr></thead><tbody>';
    foreach ($finished_attempts as $a) {
        $html .= '<tr><td>' . $a->attempt . '</td><td>' . number_format($a->grade * 5 / 100, 1) . ' / 5</td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<p><strong>' . ($method_labels[$method] ?? '') . ':</strong> ' . number_format($final * 5 / 100, 1) . ' / 5</p>';
    $html .= '</div>';
    return $html;
}
