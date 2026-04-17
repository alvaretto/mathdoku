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
 * Privacy API implementation for mod_mathdoku.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Molina <luisernestomarceloberni@gmail.com>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace mod_mathdoku\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_mathdoku.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $items
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table(
            'mathdoku_attempts',
            [
                'userid'       => 'privacy:metadata:mathdoku_attempts:userid',
                'attempt'      => 'privacy:metadata:mathdoku_attempts:attempt',
                'studentgrid'  => 'privacy:metadata:mathdoku_attempts:studentgrid',
                'timecreated'  => 'privacy:metadata:mathdoku_attempts:timecreated',
                'timefinished' => 'privacy:metadata:mathdoku_attempts:timefinished',
                'state'        => 'privacy:metadata:mathdoku_attempts:state',
                'grade'        => 'privacy:metadata:mathdoku_attempts:grade',
                'correct'      => 'privacy:metadata:mathdoku_attempts:correct',
            ],
            'privacy:metadata:mathdoku_attempts'
        );
        return $items;
    }

    /**
     * Get the list of contexts that contain user data for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {mathdoku} m ON m.id = cm.instance
                  JOIN {mathdoku_attempts} a ON a.mathdokuid = m.id
                 WHERE a.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT a.userid
                  FROM {mathdoku_attempts} a
                  JOIN {course_modules} cm ON cm.instance = a.mathdokuid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Export personal data for the given approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('mathdoku', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $attempts = $DB->get_records('mathdoku_attempts', [
                'mathdokuid' => $cm->instance,
                'userid'     => $userid,
            ], 'attempt ASC');

            foreach ($attempts as $attempt) {
                $data = [
                    'attempt'      => $attempt->attempt,
                    'state'        => $attempt->state,
                    'grade'        => $attempt->grade,
                    'correct'      => transform::yesno($attempt->correct),
                    'timecreated'  => transform::datetime($attempt->timecreated),
                    'timefinished' => $attempt->timefinished
                        ? transform::datetime($attempt->timefinished) : '-',
                ];
                writer::with_context($context)->export_data(
                    [get_string('attempt', 'mathdoku') . ' ' . $attempt->attempt],
                    (object) $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the given context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('mathdoku', $context->instanceid);
        if ($cm) {
            $DB->delete_records('mathdoku_attempts', ['mathdokuid' => $cm->instance]);
        }
    }

    /**
     * Delete all data for the specified user in the given approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('mathdoku', $context->instanceid);
            if ($cm) {
                $DB->delete_records('mathdoku_attempts', [
                    'mathdokuid' => $cm->instance,
                    'userid'     => $userid,
                ]);
            }
        }
    }

    /**
     * Delete data for multiple users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('mathdoku', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['mathdokuid'] = $cm->instance;
        $DB->delete_records_select('mathdoku_attempts',
            "mathdokuid = :mathdokuid AND userid $insql", $params);
    }
}
