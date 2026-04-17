<?php
/**
 * Tests for mod_mathdoku puzzle_generator.
 * Run: php8.4 tests/test_generator.php
 */

require_once __DIR__ . '/../classes/puzzle_generator.php';

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

function check_puzzle(array $p, int $difficulty, int $seed): array {
    $errors = [];
    $size   = $p['size'];
    $cages  = $p['cages'];
    $sol    = $p['solution'];

    // Hard allows max_size+1 (6) as a last-resort edge case when steal is impossible
    $max_cage = match ($difficulty) { 1 => 3, 2 => 4, 3 => 6 };
    [$given_min, $given_max] = match ($difficulty) {
        1 => [2, 3], 2 => [0, 1], 3 => [0, 0]
    };

    // 1. Latin square: no repeated digits in any row or column
    for ($r = 0; $r < $size; $r++) {
        if (count(array_unique($sol[$r])) !== $size) {
            $errors[] = "Fila $r tiene dígitos repetidos";
        }
    }
    for ($c = 0; $c < $size; $c++) {
        $col = array_column($sol, $c);
        if (count(array_unique($col)) !== $size) {
            $errors[] = "Columna $c tiene dígitos repetidos";
        }
    }

    // 2. All cells covered exactly once
    $covered = [];
    foreach ($cages as $cage) {
        foreach ($cage['cells'] as [$r, $c]) {
            $key = "$r,$c";
            if (isset($covered[$key])) {
                $errors[] = "Celda [$r,$c] aparece en más de una jaula";
            }
            $covered[$key] = true;
        }
    }
    if (count($covered) !== $size * $size) {
        $errors[] = "Celdas cubiertas: " . count($covered) . " (esperado $size×$size=" . ($size*$size) . ")";
    }

    // 3. Max cage size respected
    foreach ($cages as $i => $cage) {
        $n = count($cage['cells']);
        if ($n > $max_cage) {
            $errors[] = "Jaula $i tiene $n celdas (max $max_cage para difficulty $difficulty)";
        }
    }

    // 4. No non-given single-cell cages (op must be null only for given cells)
    foreach ($cages as $i => $cage) {
        if (count($cage['cells']) === 1 && empty($cage['given'])) {
            $errors[] = "Jaula $i es singleton no-dado (celda {$cage['cells'][0][0]},{$cage['cells'][0][1]})";
        }
    }

    // 5. Given cells: count within allowed range
    $given_cells = array_filter($cages, fn($c) => !empty($c['given']));
    $gc = count($given_cells);
    if ($gc < $given_min || $gc > $given_max) {
        $errors[] = "Given cells: $gc (esperado $given_min–$given_max)";
    }

    // 6. Given cells are always single-cell
    foreach ($given_cells as $i => $cage) {
        if (count($cage['cells']) !== 1) {
            $errors[] = "Jaula dada $i tiene " . count($cage['cells']) . " celdas (debe ser 1)";
        }
    }

    // 7. No two given cells in same row or column with same value
    $glist = [];
    foreach ($given_cells as $cage) {
        [$r, $c] = $cage['cells'][0];
        $v = $cage['target'];
        foreach ($glist as [$pr, $pc, $pv]) {
            if ($pr === $r && $pv === $v) {
                $errors[] = "Dos given en fila $r con valor $v";
            }
            if ($pc === $c && $pv === $v) {
                $errors[] = "Dos given en columna $c con valor $v";
            }
        }
        $glist[] = [$r, $c, $v];
    }

    // 8. Cage targets are arithmetically correct
    foreach ($cages as $i => $cage) {
        $vals = array_map(fn($cell) => $sol[$cell[0]][$cell[1]], $cage['cells']);
        $op   = $cage['op'];
        $tgt  = $cage['target'];
        $ok   = false;
        if ($op === null) {
            $ok = ($tgt === $vals[0]);
        } elseif ($op === '+') {
            $ok = (array_sum($vals) === $tgt);
        } elseif ($op === '*') {
            $ok = (array_product($vals) === $tgt);
        } elseif ($op === '-') {
            $ok = count($vals) === 2 && abs($vals[0] - $vals[1]) === $tgt;
        } elseif ($op === '/') {
            $ok = count($vals) === 2 &&
                  min($vals) > 0 &&
                  intdiv(max($vals), min($vals)) === $tgt &&
                  max($vals) % min($vals) === 0;
        }
        if (!$ok) {
            $errors[] = "Jaula $i: target $tgt con op '$op' no coincide con valores " . implode(',', $vals);
        }
    }

    return $errors;
}

// ── Run tests ──────────────────────────────────────────────────────────────────

$seeds = [1, 42, 999, 12345, 99999, 314159, 271828, 777777, 123456789, 987654321];

foreach ([1 => 'EASY', 2 => 'MEDIUM', 3 => 'HARD'] as $diff => $label) {
    echo "\n── Difficulty $label ─────────────────────────────────\n";
    $all_ok = true;
    foreach ($seeds as $seed) {
        $gen    = new mod_mathdoku\puzzle_generator($diff);
        $puzzle = $gen->generate($seed);
        $errors = check_puzzle($puzzle, $diff, $seed);
        if ($errors) {
            $all_ok = false;
            foreach ($errors as $e) {
                ok(false, "seed=$seed: $e");
            }
        }
    }
    if ($all_ok) {
        ok(true, "10 seeds sin errores");
    }
}

echo "\n── Resultado: $pass passed, $fail failed ─────────────\n";
exit($fail > 0 ? 1 : 0);
