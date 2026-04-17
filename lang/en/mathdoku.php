<?php
$string['modulename']           = 'MathDoku';
$string['modulenameplural']     = 'MathDokus';
$string['modulename_help']      = 'MathDoku (KenKen) is a 9×9 numeric puzzle where each row and column must contain the digits 1–9 without repetition. Cells are grouped into cages marked with a thick border; each cage shows a target number and an arithmetic operation that its cells must satisfy.';
$string['pluginadministration'] = 'MathDoku administration';
$string['pluginname']           = 'MathDoku';

$string['puzzlesettings']       = 'Puzzle settings';
$string['difficulty']           = 'Difficulty';
$string['easy']                 = 'Easy — additions and subtractions, cages of 1–3 cells';
$string['medium']               = 'Medium — all operations, cages up to 4 cells';
$string['hard']                 = 'Hard — some operations hidden, cages up to 5 cells';

$string['attemptsettings']      = 'Attempts';
$string['maxattempts']          = 'Attempts allowed';
$string['maxattempts_help']     = 'Maximum number of attempts the student can make. "Unlimited" allows them to retry as many times as they like.';
$string['grademethod']          = 'Grade to report';
$string['grademethod_help']     = 'When multiple attempts exist, determines which grade is reported to the gradebook.';
$string['grademethod_best']     = 'Highest grade';
$string['grademethod_average']  = 'Average of all attempts';
$string['grademethod_last']     = 'Last attempt';
$string['grademethod_first']    = 'First attempt';
$string['showsolution']         = 'Show solution to student after grading';
$string['showsolution_help']    = 'When enabled, the student can see the full correct solution once their attempt has been graded.';

$string['submitpuzzle']         = 'Submit & Grade';
$string['confirmsubmit']        = 'Are you sure you want to submit now? You cannot change your answers afterwards.';
$string['correct']              = 'Correct! You solved the MathDoku.';
$string['incorrect']            = 'Incorrect. The solution does not match.';
$string['youscored']            = 'Your score: {$a} / 100';
$string['solution']             = 'Correct solution';
$string['yoursubmission']       = 'Your submission';
$string['newattempt']           = 'Try again';
$string['attempt']              = 'Attempt';
$string['attemptof']            = 'Attempt {$a->current} of {$a->max}';
$string['attemptunlimited']     = 'Attempt {$a} (unlimited)';
$string['noattemptsremaining']  = 'You have used all your attempts on this activity.';
$string['alreadygraded']        = 'You have already submitted this puzzle.';

$string['attemptssummary']      = 'Finished attempts: {$a->total} — Correct: {$a->correct}';

// Privacy API.
$string['privacy:metadata:mathdoku_attempts']              = 'Stores each attempt a student makes on a MathDoku puzzle.';
$string['privacy:metadata:mathdoku_attempts:userid']       = 'The ID of the user who made the attempt.';
$string['privacy:metadata:mathdoku_attempts:attempt']      = 'The attempt number for this user.';
$string['privacy:metadata:mathdoku_attempts:studentgrid']  = 'The student\'s submitted or in-progress grid values.';
$string['privacy:metadata:mathdoku_attempts:timecreated']  = 'The time the attempt was started.';
$string['privacy:metadata:mathdoku_attempts:timefinished'] = 'The time the attempt was submitted.';
$string['privacy:metadata:mathdoku_attempts:state']        = 'Whether the attempt is in progress or finished.';
$string['privacy:metadata:mathdoku_attempts:grade']        = 'The grade awarded for this attempt (0 or 100).';
$string['privacy:metadata:mathdoku_attempts:correct']      = 'Whether the student\'s solution was correct.';

$string['mathdoku:view']        = 'View MathDoku';
$string['mathdoku:submit']      = 'Submit MathDoku';
$string['mathdoku:viewreports'] = 'View MathDoku reports';
