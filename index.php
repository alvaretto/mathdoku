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
 * List all mathdoku instances in a course.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Martínez <alvaroangelm@iepedacitodecielo.edu.co>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id     = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/mathdoku/index.php', ['id' => $id]);
$PAGE->set_title($course->shortname . ': ' . get_string('modulenameplural', 'mathdoku'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mathdoku'));

$modinfo   = get_fast_modinfo($course);
$instances = $modinfo->get_instances_of('mathdoku');

if (empty($instances)) {
    echo $OUTPUT->notification(
        get_string('thereareno', 'moodle', get_string('modulenameplural', 'mathdoku'))
    );
} else {
    $table         = new html_table();
    $table->head   = ['#', get_string('name'), get_string('difficulty', 'mathdoku')];
    $table->align  = ['center', 'left', 'center'];

    $diff_labels = [
        1 => get_string('easy',   'mathdoku'),
        2 => get_string('medium', 'mathdoku'),
        3 => get_string('hard',   'mathdoku'),
    ];

    foreach ($instances as $cm) {
        if (!$cm->uservisible) {
            continue;
        }
        $instance = $DB->get_record('mathdoku', ['id' => $cm->instance]);
        $link     = html_writer::link(
            new moodle_url('/mod/mathdoku/view.php', ['id' => $cm->id]),
            $cm->name
        );
        $table->data[] = [
            $cm->sectionnum,
            $link,
            $diff_labels[$instance->difficulty] ?? '-',
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
