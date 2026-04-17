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
 * Main view for mod_mathdoku.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Molina <luisernestomarceloberni@gmail.com>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once('locallib.php');

$id = required_param('id', PARAM_INT);

$cm       = get_coursemodule_from_id('mathdoku', $id, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$mathdoku = $DB->get_record('mathdoku', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context    = context_module::instance($cm->id);
$can_submit = has_capability('mod/mathdoku:submit', $context);
$can_report = has_capability('mod/mathdoku:viewreports', $context);
require_capability('mod/mathdoku:view', $context);

$PAGE->set_url('/mod/mathdoku/view.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . $mathdoku->name);
$PAGE->set_heading($course->fullname);
$PAGE->add_body_class('path-mod-mathdoku');

// ── Handle POST actions ───────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);

    if ($action === 'submit' && $can_submit) {
        mathdoku_process_submission($mathdoku, $USER->id);
        redirect(new moodle_url('/mod/mathdoku/view.php', ['id' => $cm->id]));
    }

    if ($action === 'save' && $can_submit) {
        mathdoku_save_progress($mathdoku, $USER->id);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'newattempt' && $can_submit) {
        if (mathdoku_can_start_new($mathdoku, $USER->id)) {
            mathdoku_start_attempt($mathdoku, $USER->id);
        }
        redirect(new moodle_url('/mod/mathdoku/view.php', ['id' => $cm->id]));
    }
}

// ── Completion tracking ───────────────────────────────────────────────────────

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// ── Page output ───────────────────────────────────────────────────────────────

$PAGE->requires->css('/mod/mathdoku/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading($mathdoku->name);

if ($mathdoku->intro) {
    echo $OUTPUT->box(
        format_module_intro('mathdoku', $mathdoku, $cm->id),
        'generalbox mod_introbox'
    );
}

if (!$can_submit && $can_report) {
    $total   = $DB->count_records('mathdoku_attempts', ['mathdokuid' => $mathdoku->id, 'state' => 'finished']);
    $correct = $DB->count_records('mathdoku_attempts', ['mathdokuid' => $mathdoku->id, 'state' => 'finished', 'correct' => 1]);
    echo '<div class="mathdoku-teacher-summary">';
    echo '<p>' . get_string('attemptssummary', 'mathdoku',
            ['total' => $total, 'correct' => $correct]) . '</p>';

    $method_label = [
        MATHDOKU_GRADE_BEST    => get_string('grademethod_best',    'mathdoku'),
        MATHDOKU_GRADE_AVERAGE => get_string('grademethod_average', 'mathdoku'),
        MATHDOKU_GRADE_LAST    => get_string('grademethod_last',    'mathdoku'),
        MATHDOKU_GRADE_FIRST   => get_string('grademethod_first',   'mathdoku'),
    ][(int) $mathdoku->grademethod] ?? '';
    echo '<p>' . get_string('grademethod', 'mathdoku') . ': <strong>' . $method_label . '</strong></p>';
    echo '</div>';

} elseif ($can_submit) {
    $inprogress = mathdoku_get_inprogress_attempt($mathdoku, $USER->id);

    if ($inprogress) {
        mathdoku_render_puzzle($mathdoku, $inprogress, $cm);

    } else {
        $latest = mathdoku_get_latest_finished($mathdoku, $USER->id);

        if ($latest) {
            mathdoku_render_result($mathdoku, $latest, $cm);

        } elseif (mathdoku_can_start_new($mathdoku, $USER->id)) {
            $attempt = mathdoku_start_attempt($mathdoku, $USER->id);
            mathdoku_render_puzzle($mathdoku, $attempt, $cm);

        } else {
            echo $OUTPUT->notification(get_string('noattemptsremaining', 'mathdoku'), 'info');
        }
    }
}

echo $OUTPUT->footer();
