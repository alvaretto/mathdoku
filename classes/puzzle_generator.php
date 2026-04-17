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
 * Puzzle generator class for mod_mathdoku.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Martínez <alvaroangelm@iepedacitodecielo.edu.co>
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace mod_mathdoku;

/**
 * Generates a random 9×9 MathDoku (KenKen) puzzle.
 *
 * The puzzle is derived from a randomly shuffled Latin square.
 * Cells are grouped into "cages" with an arithmetic target.
 * The solution is returned separately from the cage layout so the
 * server can store it without sending it to the client.
 */
class puzzle_generator {

    public const EASY   = 1;
    public const MEDIUM = 2;
    public const HARD   = 3;

    private int $size = 9;
    private int $difficulty;
    private array $grid = []; // [row][col] => 1-9

    public function __construct(int $difficulty) {
        $this->difficulty = max(1, min(3, $difficulty));
    }

    /**
     * Generate a puzzle deterministically from the given seed.
     *
     * Returns:
     *   size     => 9
     *   cages    => array of cage objects (cells, op, target, show_op)
     *   solution => 2D array [row][col] => digit
     */
    public function generate(int $seed): array {
        mt_srand($seed);
        $this->generate_latin_square();
        $cages = $this->create_cages();
        return [
            'size'     => $this->size,
            'cages'    => $cages,
            'solution' => $this->grid,
        ];
    }

    // ── Latin square ──────────────────────────────────────────────────────────

    private function generate_latin_square(): void {
        $n = $this->size;

        // Base: grid[r][c] = (r + c) % n + 1  (valid Latin square)
        $base = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $base[$r][$c] = ($r + $c) % $n + 1;
            }
        }

        $rows   = range(0, $n - 1); $this->fisherYates($rows);
        $cols   = range(0, $n - 1); $this->fisherYates($cols);
        $digits = range(1, $n);     $this->fisherYates($digits);

        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $orig = $base[$rows[$r]][$cols[$c]];
                $this->grid[$r][$c] = $digits[$orig - 1];
            }
        }
    }

    private function fisherYates(array &$arr): void {
        $n = count($arr);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
    }

    // ── Cage creation ─────────────────────────────────────────────────────────

    private function create_cages(): array {
        $n      = $this->size;
        $caged  = array_fill(0, $n, array_fill(0, $n, false));
        $cages  = [];

        [$max_size, $ops, $hide_op_prob] = $this->difficulty_params();

        $all_cells = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $all_cells[] = [$r, $c];
            }
        }
        $this->fisherYates($all_cells);

        [$given_min, $given_max_n] = $this->given_range();
        $given_target = ($given_min < $given_max_n) ? mt_rand($given_min, $given_max_n) : $given_min;
        $given_count  = 0;

        foreach ($all_cells as [$sr, $sc]) {
            if ($caged[$sr][$sc]) {
                continue;
            }

            $cage_cells          = [[$sr, $sc]];
            $caged[$sr][$sc]     = true;

            if ($given_count < $given_target) {
                $cage          = $this->assign_operation($cage_cells, $ops, $hide_op_prob);
                $cage['given'] = true;
                $cages[]       = $cage;
                $given_count++;
                continue;
            }

            for ($exp = 1; $exp < $max_size; $exp++) {
                if (mt_rand(0, 99) >= $this->expand_prob()) {
                    break;
                }

                $adjacent = [];
                foreach ($cage_cells as [$r, $c]) {
                    foreach ([[0, 1], [0, -1], [1, 0], [-1, 0]] as [$dr, $dc]) {
                        $nr = $r + $dr;
                        $nc = $c + $dc;
                        if ($nr >= 0 && $nr < $n && $nc >= 0 && $nc < $n && !$caged[$nr][$nc]) {
                            $adjacent["$nr,$nc"] = [$nr, $nc];
                        }
                    }
                }

                if (empty($adjacent)) {
                    break;
                }

                $adjacent       = array_values($adjacent);
                $pick           = $adjacent[mt_rand(0, count($adjacent) - 1)];
                $cage_cells[]   = $pick;
                $caged[$pick[0]][$pick[1]] = true;
            }

            $cage          = $this->assign_operation($cage_cells, $ops, $hide_op_prob);
            $cage['given'] = false;
            $cages[]       = $cage;
        }

        return $this->merge_singletons($cages, $ops, $max_size);
    }

    /**
     * Merge any non-given 1-cell cage into an adjacent cage.
     * A lone cell whose answer is printed as a label gives away the solution trivially.
     */
    private function merge_singletons(array $cages, array $ops, int $max_size): array {
        $n = $this->size;

        $build_index = function (array $cages): array {
            $idx = [];
            foreach ($cages as $i => $cage) {
                foreach ($cage['cells'] as [$r, $c]) {
                    $idx["$r,$c"] = $i;
                }
            }
            return $idx;
        };

        $changed = true;
        while ($changed) {
            $changed   = false;
            $cell_idx  = $build_index($cages);

            foreach ($cages as $i => $cage) {
                if (count($cage['cells']) !== 1 || !empty($cage['given'])) {
                    continue;
                }
                [$r, $c] = $cage['cells'][0];
                // Pass 1: merge into a non-given neighbor with room
                $ni = null;
                foreach ([[0,1],[0,-1],[1,0],[-1,0]] as [$dr, $dc]) {
                    $nr = $r + $dr; $nc = $c + $dc;
                    if ($nr >= 0 && $nr < $n && $nc >= 0 && $nc < $n) {
                        $candidate = $cell_idx["$nr,$nc"] ?? null;
                        if ($candidate !== null && $candidate !== $i
                                && empty($cages[$candidate]['given'])
                                && count($cages[$candidate]['cells']) < $max_size) {
                            $ni = $candidate;
                            break;
                        }
                    }
                }
                if ($ni !== null) {
                    // standard merge
                    $merged          = $this->assign_operation(
                        array_merge($cages[$ni]['cells'], $cage['cells']), $ops, 0.0);
                    $merged['given'] = false;
                    $cages[$ni]      = $merged;
                    unset($cages[$i]);
                    $cages   = array_values($cages);
                    $changed = true;
                    break;
                }

                // Pass 2: steal a border cell from a full non-given neighbor
                $stolen = false;
                foreach ([[0,1],[0,-1],[1,0],[-1,0]] as [$dr, $dc]) {
                    $nr = $r + $dr; $nc = $c + $dc;
                    if ($nr < 0 || $nr >= $n || $nc < 0 || $nc >= $n) { continue; }
                    $donor_idx = $cell_idx["$nr,$nc"] ?? null;
                    if ($donor_idx === null || $donor_idx === $i
                            || !empty($cages[$donor_idx]['given'])) { continue; }

                    $donor     = $cages[$donor_idx];
                    $remaining = array_values(array_filter(
                        $donor['cells'], fn($c) => !($c[0] === $nr && $c[1] === $nc)));

                    // Donor must have ≥ 2 cells left and stay connected after the steal
                    if (count($remaining) < 2) { continue; }
                    if (!$this->cells_connected($remaining)) { continue; }

                    // Rebuild donor cage without the stolen cell
                    $new_donor          = $this->assign_operation($remaining, $ops, 0.0);
                    $new_donor['given'] = false;
                    $cages[$donor_idx]  = $new_donor;

                    // Merge singleton + stolen cell into a new 2-cell cage
                    $new_cage          = $this->assign_operation([[$r, $c], [$nr, $nc]], $ops, 0.0);
                    $new_cage['given'] = false;
                    $cages[$i]         = $new_cage;

                    $stolen  = true;
                    $changed = true;
                    break;
                }
                if ($stolen) { break; }

                // Pass 3: last resort — merge into any non-given neighbor ignoring size,
                // or promote to given if difficulty allows it
                [$gmin, $gmax_n] = $this->given_range();
                $last_ni = null;
                foreach ([[0,1],[0,-1],[1,0],[-1,0]] as [$dr, $dc]) {
                    $nr = $r + $dr; $nc = $c + $dc;
                    if ($nr >= 0 && $nr < $n && $nc >= 0 && $nc < $n) {
                        $candidate = $cell_idx["$nr,$nc"] ?? null;
                        if ($candidate !== null && $candidate !== $i
                                && empty($cages[$candidate]['given'])) {
                            $last_ni = $candidate;
                            break;
                        }
                    }
                }
                if ($last_ni !== null) {
                    $merged          = $this->assign_operation(
                        array_merge($cages[$last_ni]['cells'], $cage['cells']), $ops, 0.0);
                    $merged['given'] = false;
                    $cages[$last_ni] = $merged;
                    unset($cages[$i]);
                    $cages   = array_values($cages);
                } else {
                    // Truly surrounded by given cells — promote to given
                    $cages[$i]['given'] = true;
                }
                $changed = true;
                break;
            }
        }

        return $cages;
    }

    private function cells_connected(array $cells): bool {
        if (count($cells) <= 1) { return true; }
        $set = [];
        foreach ($cells as [$r, $c]) { $set["$r,$c"] = [$r, $c]; }
        $start   = reset($cells);
        $visited = [$start[0] . ',' . $start[1] => true];
        $queue   = [$start];
        while (!empty($queue)) {
            [$r, $c] = array_shift($queue);
            foreach ([[0,1],[0,-1],[1,0],[-1,0]] as [$dr, $dc]) {
                $key = ($r + $dr) . ',' . ($c + $dc);
                if (isset($set[$key]) && !isset($visited[$key])) {
                    $visited[$key] = true;
                    $queue[]       = $set[$key];
                }
            }
        }
        return count($visited) === count($cells);
    }

    private function assign_operation(array $cells, array $ops, float $hide_op_prob): array {
        $values = array_map(fn($cell) => $this->grid[$cell[0]][$cell[1]], $cells);
        $count  = count($cells);

        if ($count === 1) {
            return ['cells' => $cells, 'op' => null, 'target' => $values[0], 'show_op' => true];
        }

        $valid_ops = [];

        if (in_array('+', $ops)) {
            $valid_ops[] = ['+', array_sum($values)];
        }

        if ($count === 2 && in_array('-', $ops)) {
            $valid_ops[] = ['-', abs($values[0] - $values[1])];
        }

        if (in_array('*', $ops)) {
            $prod = 1;
            foreach ($values as $v) {
                $prod *= $v;
            }
            $valid_ops[] = ['*', $prod];
        }

        if ($count === 2 && in_array('/', $ops)) {
            $max = max($values);
            $min = min($values);
            if ($min > 0 && $max % $min === 0) {
                $valid_ops[] = ['/', intdiv($max, $min)];
            }
        }

        if (empty($valid_ops)) {
            $valid_ops[] = ['+', array_sum($values)];
        }

        $chosen  = $valid_ops[mt_rand(0, count($valid_ops) - 1)];
        $show_op = ($hide_op_prob === 0.0) || mt_rand(0, 99) >= (int) ($hide_op_prob * 100);

        return [
            'cells'   => $cells,
            'op'      => $chosen[0],
            'target'  => $chosen[1],
            'show_op' => $show_op,
        ];
    }

    // ── Difficulty params ─────────────────────────────────────────────────────

    private function difficulty_params(): array {
        return match ($this->difficulty) {
            self::EASY   => [3, ['+', '-'],            0.0],
            self::MEDIUM => [4, ['+', '-', '*', '/'],  0.0],
            self::HARD   => [5, ['+', '-', '*', '/'],  0.3],
        };
    }

    private function expand_prob(): int {
        return match ($this->difficulty) {
            self::EASY   => 40,
            self::MEDIUM => 60,
            self::HARD   => 70,
        };
    }

    private function given_range(): array {
        return match ($this->difficulty) {
            self::EASY   => [2, 3],
            self::MEDIUM => [0, 1],
            self::HARD   => [0, 0],
        };
    }
}
