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
 * Activity settings form for mod_mathdoku.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Molina <luisernestomarceloberni@gmail.com>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_mathdoku_mod_form extends moodleform_mod {

    public function definition(): void {
        $mform = $this->_form;

        // ── General ───────────────────────────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ── Puzzle settings ───────────────────────────────────────────────────
        $mform->addElement('header', 'puzzlesettings', get_string('puzzlesettings', 'mathdoku'));

        $difficulties = [
            1 => get_string('easy',   'mathdoku'),
            2 => get_string('medium', 'mathdoku'),
            3 => get_string('hard',   'mathdoku'),
        ];
        $mform->addElement('select', 'difficulty', get_string('difficulty', 'mathdoku'), $difficulties);
        $mform->setDefault('difficulty', 1);

        // ── Attempts ──────────────────────────────────────────────────────────
        $mform->addElement('header', 'attemptsettings', get_string('attemptsettings', 'mathdoku'));

        // maxattempts: 0 = unlimited, 1..10
        $attempt_options = [0 => get_string('unlimited')] + array_combine(range(1, 10), range(1, 10));
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'mathdoku'), $attempt_options);
        $mform->setDefault('maxattempts', 1);
        $mform->addHelpButton('maxattempts', 'maxattempts', 'mathdoku');

        $grade_methods = [
            MATHDOKU_GRADE_BEST    => get_string('grademethod_best',    'mathdoku'),
            MATHDOKU_GRADE_AVERAGE => get_string('grademethod_average', 'mathdoku'),
            MATHDOKU_GRADE_LAST    => get_string('grademethod_last',    'mathdoku'),
            MATHDOKU_GRADE_FIRST   => get_string('grademethod_first',   'mathdoku'),
        ];
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'mathdoku'), $grade_methods);
        $mform->setDefault('grademethod', MATHDOKU_GRADE_BEST);
        $mform->addHelpButton('grademethod', 'grademethod', 'mathdoku');

        // Disable grademethod if only one attempt is allowed
        $mform->hideIf('grademethod', 'maxattempts', 'eq', '1');

        $mform->addElement('advcheckbox', 'showsolution', get_string('showsolution', 'mathdoku'));
        $mform->setDefault('showsolution', 0);
        $mform->addHelpButton('showsolution', 'showsolution', 'mathdoku');

        // ── Grade / standard elements ─────────────────────────────────────────
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
