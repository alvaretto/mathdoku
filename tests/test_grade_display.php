<?php
/**
 * Tests for grade display conversion (0-100 internal → 0-5 display).
 * Run: php tests/test_grade_display.php
 */

$pass = 0;
$fail = 0;

function ok(bool $cond, string $label): void {
    global $pass, $fail;
    if ($cond) {
        echo "  PASS  $label\n";
        $pass++;
    } else {
        echo "  FAIL  $label\n";
        $fail++;
    }
}

function grade_to_display(float $grade): string {
    return number_format($grade * 5 / 100, 1);
}

function summary_final(array $grades, int $method): float {
    // Mirrors locallib.php mathdoku_render_attempts_summary logic.
    // method: 0=best, 1=average, 2=last, 3=first
    return match ($method) {
        1 => array_sum($grades) / count($grades),
        2 => (float) end($grades),
        3 => (float) reset($grades),
        default => max($grades),
    };
}

echo "\n── Grade conversion: single attempt ─────────────────\n";

ok(grade_to_display(0.0)   === '0.0', 'grade 0   → "0.0"');
ok(grade_to_display(100.0) === '5.0', 'grade 100 → "5.0"');
ok(grade_to_display(0.0)   . ' / 5' === '0.0 / 5', 'display 0   → "0.0 / 5"');
ok(grade_to_display(100.0) . ' / 5' === '5.0 / 5', 'display 100 → "5.0 / 5"');

echo "\n── Grade conversion: attempt summary table ──────────\n";

$attempts = [
    ['grade' => 0.0,   'expected' => '0.0 / 5'],
    ['grade' => 100.0, 'expected' => '5.0 / 5'],
];
foreach ($attempts as $a) {
    $display = grade_to_display($a['grade']) . ' / 5';
    ok($display === $a['expected'], "grade {$a['grade']} in table → \"{$a['expected']}\"");
}

echo "\n── Grade conversion: final grade (multiple attempts) ─\n";

$grades = [0.0, 100.0];

$best    = summary_final($grades, 0); // 100
$avg     = summary_final($grades, 1); // 50
$last    = summary_final($grades, 2); // 100
$first   = summary_final($grades, 3); // 0

ok(number_format($best  * 5 / 100, 1) . ' / 5' === '5.0 / 5', 'best  → "5.0 / 5"');
ok(number_format($avg   * 5 / 100, 1) . ' / 5' === '2.5 / 5', 'avg   → "2.5 / 5"');
ok(number_format($last  * 5 / 100, 1) . ' / 5' === '5.0 / 5', 'last  → "5.0 / 5"');
ok(number_format($first * 5 / 100, 1) . ' / 5' === '0.0 / 5', 'first → "0.0 / 5"');

echo "\n── Language strings ─────────────────────────────────\n";

$es_strings = [];
$en_strings = [];
foreach (file(__DIR__ . '/../lang/es/mathdoku.php') as $line) {
    if (preg_match("/\\\$string\['(\w+)'\]\s*=\s*'(.+)';/", $line, $m)) {
        $es_strings[$m[1]] = $m[2];
    }
}
foreach (file(__DIR__ . '/../lang/en/mathdoku.php') as $line) {
    if (preg_match("/\\\$string\['(\w+)'\]\s*=\s*'(.+)';/", $line, $m)) {
        $en_strings[$m[1]] = $m[2];
    }
}

ok(str_contains($es_strings['youscored'] ?? '', '/ 5'),  'ES youscored contiene "/ 5"');
ok(!str_contains($es_strings['youscored'] ?? '', '/ 100'), 'ES youscored NO contiene "/ 100"');
ok(str_contains($en_strings['youscored'] ?? '', '/ 5'),  'EN youscored contiene "/ 5"');
ok(!str_contains($en_strings['youscored'] ?? '', '/ 100'), 'EN youscored NO contiene "/ 100"');

echo "\n── locallib.php uses number_format conversion ───────\n";

$locallib = file_get_contents(__DIR__ . '/../locallib.php');
ok(str_contains($locallib, 'grade * 5 / 100'),       'locallib usa grade * 5 / 100');
ok(!str_contains($locallib, '(int) $attempt->grade . \' / 100\''), 'locallib NO tiene viejo "/ 100"');
ok(str_contains($locallib, '/ 5'),                   'locallib tiene "/ 5" en strings de tabla');

echo "\n── attemptId se pasa al JS ───────────────────────────\n";

ok(str_contains($locallib, "'attemptId'"), 'locallib pasa attemptId al JS');

echo "\n── Resultado: $pass passed, $fail failed ────────────\n";
exit($fail > 0 ? 1 : 0);
