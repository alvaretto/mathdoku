<?php
$string['modulename']           = 'MathDoku';
$string['modulenameplural']     = 'MathDokus';
$string['modulename_help']      = 'MathDoku (KenKen) es un puzzle numérico de 9×9 donde cada fila y columna debe contener los dígitos 1 al 9 sin repetir. Las celdas están agrupadas en "jaulas" marcadas con borde grueso; cada jaula muestra un número objetivo y una operación aritmética que sus celdas deben satisfacer.';
$string['pluginadministration'] = 'Administración de MathDoku';
$string['pluginname']           = 'MathDoku';

// Configuración del puzzle
$string['puzzlesettings']       = 'Configuración del puzzle';
$string['difficulty']           = 'Dificultad';
$string['easy']                 = 'Fácil — sumas y restas, jaulas de 1–3 celdas';
$string['medium']               = 'Medio — todas las operaciones, jaulas de hasta 4 celdas';
$string['hard']                 = 'Difícil — algunas operaciones ocultas, jaulas de hasta 5 celdas';

// Configuración de intentos
$string['attemptsettings']      = 'Intentos';
$string['maxattempts']          = 'Intentos permitidos';
$string['maxattempts_help']     = 'Número máximo de intentos que puede hacer el estudiante. "Sin límite" permite repetirlo indefinidamente.';
$string['grademethod']          = 'Calificación final';
$string['grademethod_help']     = 'Cuando hay varios intentos, define cuál se usa como calificación final en el libro de notas.';
$string['grademethod_best']     = 'Calificación más alta';
$string['grademethod_average']  = 'Promedio de todos los intentos';
$string['grademethod_last']     = 'Último intento';
$string['grademethod_first']    = 'Primer intento';
$string['showsolution']         = 'Mostrar solución al estudiante después de calificar';
$string['showsolution_help']    = 'Si está activado, el estudiante podrá ver la solución correcta completa una vez que su intento haya sido calificado.';

// Interfaz del estudiante
$string['submitpuzzle']         = 'Calificar';
$string['confirmsubmit']        = '¿Confirmas que quieres calificar ahora? No podrás modificar tus respuestas.';
$string['correct']              = '¡Correcto! Resolviste el MathDoku.';
$string['incorrect']            = 'Incorrecto. La solución no es la esperada.';
$string['youscored']            = 'Tu calificación: {$a} / 100';
$string['solution']             = 'Solución correcta';
$string['yoursubmission']       = 'Tu respuesta';
$string['newattempt']           = 'Intentar de nuevo';
$string['attempt']              = 'Intento';
$string['attemptof']            = 'Intento {$a->current} de {$a->max}';
$string['attemptunlimited']     = 'Intento {$a} (sin límite)';
$string['noattemptsremaining']  = 'Ya has usado todos tus intentos en esta actividad.';
$string['alreadygraded']        = 'Ya calificaste este puzzle.';

// Vista del profesor
$string['attemptssummary']      = 'Intentos terminados: {$a->total} — Correctos: {$a->correct}';

// Capacidades
$string['mathdoku:view']        = 'Ver MathDoku';
$string['mathdoku:submit']      = 'Entregar MathDoku';
$string['mathdoku:viewreports'] = 'Ver reportes de MathDoku';
