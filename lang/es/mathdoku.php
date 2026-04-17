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

$string['alreadygraded']        = 'Ya calificaste este puzzle.';
$string['attempt']              = 'Intento';
$string['attemptof']            = 'Intento {$a->current} de {$a->max}';
$string['attemptsettings']      = 'Intentos';
$string['attemptssummary']      = 'Intentos terminados: {$a->total} — Correctos: {$a->correct}';
$string['attemptunlimited']     = 'Intento {$a} (sin límite)';
$string['confirmsubmit']        = '¿Confirmas que quieres calificar ahora? No podrás modificar tus respuestas.';
$string['correct']              = '¡Correcto! Resolviste el MathDoku.';
$string['difficulty']           = 'Dificultad';
$string['easy']                 = 'Fácil — sumas y restas, jaulas de 1–3 celdas';
$string['grademethod']          = 'Calificación final';
$string['grademethod_average']  = 'Promedio de todos los intentos';
$string['grademethod_best']     = 'Calificación más alta';
$string['grademethod_first']    = 'Primer intento';
$string['grademethod_help']     = 'Cuando hay varios intentos, define cuál se usa como calificación final en el libro de notas.';
$string['grademethod_last']     = 'Último intento';
$string['hard']                 = 'Difícil — algunas operaciones ocultas, jaulas de hasta 5 celdas';
$string['mathdoku:submit']      = 'Entregar MathDoku';
$string['mathdoku:view']        = 'Ver MathDoku';
$string['mathdoku:viewreports'] = 'Ver reportes de MathDoku';
$string['maxattempts']          = 'Intentos permitidos';
$string['maxattempts_help']     = 'Número máximo de intentos que puede hacer el estudiante. "Sin límite" permite repetirlo indefinidamente.';
$string['medium']               = 'Medio — todas las operaciones, jaulas de hasta 4 celdas';
$string['modulename']           = 'MathDoku';
$string['modulename_help']      = 'MathDoku (KenKen) es un puzzle numérico de 9×9 donde cada fila y columna debe contener los dígitos 1 al 9 sin repetir. Las celdas están agrupadas en "jaulas" marcadas con borde grueso; cada jaula muestra un número objetivo y una operación aritmética que sus celdas deben satisfacer.';
$string['modulenameplural']     = 'MathDokus';
$string['newattempt']           = 'Intentar de nuevo';
$string['noattemptsremaining']  = 'Ya has usado todos tus intentos en esta actividad.';
$string['pluginadministration'] = 'Administración de MathDoku';
$string['pluginname']           = 'MathDoku';
$string['privacy:metadata:mathdoku_attempts']              = 'Almacena cada intento que un estudiante realiza en un puzzle MathDoku.';
$string['privacy:metadata:mathdoku_attempts:attempt']      = 'El número de intento para este usuario.';
$string['privacy:metadata:mathdoku_attempts:correct']      = 'Si la solución del estudiante fue correcta.';
$string['privacy:metadata:mathdoku_attempts:grade']        = 'La calificación otorgada por este intento (0 o 100).';
$string['privacy:metadata:mathdoku_attempts:state']        = 'Si el intento está en progreso o terminado.';
$string['privacy:metadata:mathdoku_attempts:timecreated']  = 'La hora en que se inició el intento.';
$string['privacy:metadata:mathdoku_attempts:timefinished'] = 'La hora en que se envió el intento.';
$string['privacy:metadata:mathdoku_attempts:userid']       = 'El ID del usuario que realizó el intento.';
$string['puzzlesettings']       = 'Configuración del puzzle';
$string['showsolution']         = 'Mostrar solución al estudiante después de calificar';
$string['showsolution_help']    = 'Si está activado, el estudiante podrá ver la solución correcta completa una vez que su intento haya sido calificado.';
$string['solution']             = 'Solución correcta';
$string['submitpuzzle']         = 'Calificar';
$string['yoursubmission']       = 'Tu respuesta';
$string['youscored']            = 'Tu calificación: {$a} / 5';
