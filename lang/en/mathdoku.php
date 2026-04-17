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
 * Language strings for mod_mathdoku.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Molina <luisernestomarceloberni@gmail.com>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

$string['alreadygraded']        = 'You have already submitted this puzzle.';
$string['attempt']              = 'Attempt';
$string['attemptof']            = 'Attempt {$a->current} of {$a->max}';
$string['attemptsettings']      = 'Attempts';
$string['attemptssummary']      = 'Finished attempts: {$a->total} — Correct: {$a->correct}';
$string['attemptunlimited']     = 'Attempt {$a} (unlimited)';
$string['confirmsubmit']        = 'Are you sure you want to submit now? You cannot change your answers afterwards.';
$string['correct']              = 'Correct! You solved the MathDoku.';
$string['difficulty']           = 'Difficulty';
$string['easy']                 = 'Easy — additions and subtractions, cages of 1–3 cells';
$string['grademethod']          = 'Grade to report';
$string['grademethod_average']  = 'Average of all attempts';
$string['grademethod_best']     = 'Highest grade';
$string['grademethod_first']    = 'First attempt';
$string['grademethod_help']     = 'When multiple attempts exist, determines which grade is reported to the gradebook.';
$string['grademethod_last']     = 'Last attempt';
$string['hard']                 = 'Hard — some operations hidden, cages up to 5 cells';
$string['mathdoku:submit']      = 'Submit MathDoku';
$string['mathdoku:view']        = 'View MathDoku';
$string['mathdoku:viewreports'] = 'View MathDoku reports';
$string['maxattempts']          = 'Attempts allowed';
$string['maxattempts_help']     = 'Maximum number of attempts the student can make. "Unlimited" allows them to retry as many times as they like.';
$string['medium']               = 'Medium — all operations, cages up to 4 cells';
$string['modulename']           = 'MathDoku';
$string['modulename_help']      = 'MathDoku (KenKen) is a 9×9 numeric puzzle where each row and column must contain the digits 1–9 without repetition. Cells are grouped into cages marked with a thick border; each cage shows a target number and an arithmetic operation that its cells must satisfy.';
$string['modulenameplural']     = 'MathDokus';
$string['newattempt']           = 'Try again';
$string['noattemptsremaining']  = 'You have used all your attempts on this activity.';
$string['pluginadministration'] = 'MathDoku administration';
$string['pluginname']           = 'MathDoku';
$string['privacy:metadata:mathdoku_attempts']              = 'Stores each attempt a student makes on a MathDoku puzzle.';
$string['privacy:metadata:mathdoku_attempts:attempt']      = 'The attempt number for this user.';
$string['privacy:metadata:mathdoku_attempts:correct']      = 'Whether the student\'s solution was correct.';
$string['privacy:metadata:mathdoku_attempts:grade']        = 'The grade awarded for this attempt (0 or 100).';
$string['privacy:metadata:mathdoku_attempts:state']        = 'Whether the attempt is in progress or finished.';
$string['privacy:metadata:mathdoku_attempts:timecreated']  = 'The time the attempt was started.';
$string['privacy:metadata:mathdoku_attempts:timefinished'] = 'The time the attempt was submitted.';
$string['privacy:metadata:mathdoku_attempts:userid']       = 'The ID of the user who made the attempt.';
$string['puzzlesettings']       = 'Puzzle settings';
$string['showsolution']         = 'Show solution to student after grading';
$string['showsolution_help']    = 'When enabled, the student can see the full correct solution once their attempt has been graded.';
$string['solution']             = 'Correct solution';
$string['submitpuzzle']         = 'Submit & Grade';
$string['yoursubmission']       = 'Your submission';
$string['youscored']            = 'Your score: {$a} / 5';
