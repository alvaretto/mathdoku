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
 * Library of interface functions and constants for mod_mathdoku.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Martínez <alvaroangelm@iepedacitodecielo.edu.co>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Grade is the highest across all attempts. */
define('MATHDOKU_GRADE_BEST',    1);
/** Grade is the average across all attempts. */
define('MATHDOKU_GRADE_AVERAGE', 2);
/** Grade is taken from the most recent attempt. */
define('MATHDOKU_GRADE_LAST',    3);
/** Grade is taken from the first attempt. */
define('MATHDOKU_GRADE_FIRST',   4);

/**
 * Add a new mathdoku instance to the database.
 *
 * @param stdClass $data  Form data from mod_form.
 * @param mixed    $mform The submitted form (unused).
 * @return int The new instance id.
 */
function mathdoku_add_instance(stdClass $data, $mform = null): int {
    global $DB;
    $data->timemodified = time();
    if (!isset($data->grade))       { $data->grade       = 100; }
    if (!isset($data->maxattempts)) { $data->maxattempts = 1;   }
    if (!isset($data->grademethod)) { $data->grademethod = MATHDOKU_GRADE_BEST; }
    $id = $DB->insert_record('mathdoku', $data);
    $data->id = $id;
    mathdoku_grade_item_update($data);
    return $id;
}

/**
 * Update an existing mathdoku instance.
 *
 * @param stdClass $data Form data including the instance id.
 * @param mixed    $mform The submitted form (unused).
 * @return bool True on success.
 */
function mathdoku_update_instance(stdClass $data, $mform = null): bool {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    $result = $DB->update_record('mathdoku', $data);
    mathdoku_grade_item_update($data);
    return $result;
}

/**
 * Delete a mathdoku instance and all its attempt data.
 *
 * @param int $id The instance id.
 * @return bool True on success.
 */
function mathdoku_delete_instance(int $id): bool {
    global $DB;
    if (!$mathdoku = $DB->get_record('mathdoku', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('mathdoku_attempts', ['mathdokuid' => $id]);
    $DB->delete_records('mathdoku', ['id' => $id]);
    mathdoku_grade_item_delete($mathdoku);
    return true;
}

/**
 * Return the feature support flags for this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return bool|null True/false/null.
 */
function mathdoku_supports(string $feature): ?bool {
    return match ($feature) {
        FEATURE_MOD_INTRO               => true,
        FEATURE_SHOW_DESCRIPTION        => true,
        FEATURE_GRADE_HAS_GRADE         => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_BACKUP_MOODLE2          => null, // Not yet implemented.
        default                         => null,
    };
}

/**
 * Create or update the grade item in the gradebook.
 *
 * @param stdClass $mathdoku The mathdoku instance.
 * @param mixed    $grades   Grade data, 'reset', or null.
 * @return int GRADE_UPDATE_OK or error code.
 */
function mathdoku_grade_item_update(stdClass $mathdoku, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname'  => $mathdoku->name,
        'idnumber'  => $mathdoku->cmidnumber ?? '',
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => $mathdoku->grade ?? 100,
        'grademin'  => 0,
    ];

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/mathdoku', $mathdoku->course, 'mod', 'mathdoku',
                        $mathdoku->id, 0, $grades, $params);
}

/**
 * Delete the grade item from the gradebook.
 *
 * @param stdClass $mathdoku The mathdoku instance.
 * @return int GRADE_UPDATE_OK or error code.
 */
function mathdoku_grade_item_delete(stdClass $mathdoku): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/mathdoku', $mathdoku->course, 'mod', 'mathdoku',
                        $mathdoku->id, 0, null, ['deleted' => 1]);
}

/**
 * Recompute and push grades to the gradebook for one or all users.
 *
 * Applies the configured grade method (best / average / last / first).
 *
 * @param stdClass $mathdoku The mathdoku instance.
 * @param int      $userid   Specific user, or 0 for all users.
 */
function mathdoku_update_grades(stdClass $mathdoku, int $userid = 0): void {
    global $DB;

    $sql    = "SELECT id, userid, grade, timefinished
               FROM {mathdoku_attempts}
               WHERE mathdokuid = ? AND state = 'finished'
               ORDER BY timefinished ASC";
    $params = [$mathdoku->id];

    if ($userid > 0) {
        $sql    = "SELECT id, userid, grade, timefinished
                   FROM {mathdoku_attempts}
                   WHERE mathdokuid = ? AND userid = ? AND state = 'finished'
                   ORDER BY timefinished ASC";
        $params = [$mathdoku->id, $userid];
    }

    $rows = $DB->get_records_sql($sql, $params);

    $by_user = [];
    foreach ($rows as $row) {
        $by_user[$row->userid][] = (float) $row->grade;
    }

    $grades = [];
    foreach ($by_user as $uid => $attempt_grades) {
        $final = match ((int) $mathdoku->grademethod) {
            MATHDOKU_GRADE_AVERAGE => array_sum($attempt_grades) / count($attempt_grades),
            MATHDOKU_GRADE_LAST    => end($attempt_grades),
            MATHDOKU_GRADE_FIRST   => reset($attempt_grades),
            default                => max($attempt_grades),
        };
        $grades[$uid] = ['userid' => (int) $uid, 'rawgrade' => $final];
    }

    mathdoku_grade_item_update($mathdoku, empty($grades) ? null : $grades);
}
