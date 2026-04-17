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
 * Puzzle generator for mod_mathdoku.
 *
 * @package   mod_mathdoku
 * @copyright 2026 Álvaro Ángel Molina <luisernestomarceloberni@gmail.com>
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

    /** @var int Easy difficulty level. */
    public const EASY   = 1;

    /** @var int Medium difficulty level. */
    public const MEDIUM = 2;

    /** @var int Hard difficulty level. */
    public const HARD   = 3;

    /** @var int Grid side length. */
    private int $size = 9;

    /** @var int Difficulty level (1=easy, 2=medium, 3=hard). */
    private int $difficulty;

    /** @var array Two-dimensional grid [row][col] => 1-9. */
    private array $grid = [];

    /**
     * Constructor.
     *
     * @param int $difficulty Difficulty level (1=easy, 2=medium, 3=hard).
     */
    public function __construct(int $difficulty) {
        $this->difficulty = max(1, min(3, $difficulty));
    }

    /**
     * Generate a puzzle deterministically from the given seed.
     *
     * Returns an associative array with keys:
     *   - size     => 9
     *   - cages    => array of cage objects (cells, op, target, show_op)
     *   - solution => 2D array [row][col] => digit
     *
     * @param int $seed Random seed for reproducible generation.
     * @return array Puzzle data with size, cages, and solution.
     */
    public function generate(int $seed): array {
        mt_srand($seed);
        $this->generatelatinsquare();
        $cages = $this->createcages();
        return [
            'size'     => $this->size,
            'cages'    => $cages,
            'solution' => $this->grid,
        ];
    }

    /**
     * Build the internal grid as a shuffled Latin square.
     *
     * @return void
     */
    private function generatelatinsquare(): void {
        $n = $this->size;

        // Base: grid[r][c] = (r + c) % n + 1  (valid Latin square).
        $base = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $base[$r][$c] = ($r + $c) % $n + 1;
            }
        }

        $rows   = range(0, $n - 1);
        $this->fisheryates($rows);
        $cols   = range(0, $n - 1);
        $this->fisheryates($cols);
        $digits = range(1, $n);
        $this->fisheryates($digits);

        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $orig = $base[$rows[$r]][$cols[$c]];
                $this->grid[$r][$c] = $digits[$orig - 1];
            }
        }
    }

    /**
     * Shuffle an array in place using the Fisher-Yates algorithm.
     *
     * @param array $arr Array to shuffle (passed by reference).
     * @return void
     */
    private function fisheryates(array &$arr): void {
        $n = count($arr);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
    }

    /**
     * Partition all grid cells into arithmetic cages.
     *
     * @return array Array of cage associative arrays.
     */
    private function createcages(): array {
        $n      = $this->size;
        $caged  = array_fill(0, $n, array_fill(0, $n, false));
        $cages  = [];

        [$maxsize, $ops, $hideopprob] = $this->difficultyparams();

        $allcells = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $allcells[] = [$r, $c];
            }
        }
        $this->fisheryates($allcells);

        [$givenmin, $givenmaxn] = $this->givenrange();
        $giventarget = ($givenmin < $givenmaxn) ? mt_rand($givenmin, $givenmaxn) : $givenmin;
        $givencount  = 0;

        foreach ($allcells as [$sr, $sc]) {
            if ($caged[$sr][$sc]) {
                continue;
            }

            $cagecells           = [[$sr, $sc]];
            $caged[$sr][$sc]     = true;

            if ($givencount < $giventarget) {
                $cage          = $this->assignoperation($cagecells, $ops, $hideopprob);
                $cage['given'] = true;
                $cages[]       = $cage;
                $givencount++;
                continue;
            }

            for ($exp = 1; $exp < $maxsize; $exp++) {
                if (mt_rand(0, 99) >= $this->expandprob()) {
                    break;
                }

                $adjacent = [];
                foreach ($cagecells as [$r, $c]) {
                    foreach ([[0, 1], [0, -1], [1, 0], [-1, 0]] as [$dr, $dc]) {
                        $nr = $r + $dr;
                        $nc = $c + $dc;
                        if (
                            $nr >= 0 && $nr < $n &&
                            $nc >= 0 && $nc < $n &&
                            !$caged[$nr][$nc]
                        ) {
                            $adjacent["$nr,$nc"] = [$nr, $nc];
                        }
                    }
                }

                if (empty($adjacent)) {
                    break;
                }

                $adjacent     = array_values($adjacent);
                $pick         = $adjacent[mt_rand(0, count($adjacent) - 1)];
                $cagecells[]  = $pick;
                $caged[$pick[0]][$pick[1]] = true;
            }

            $cage          = $this->assignoperation($cagecells, $ops, $hideopprob);
            $cage['given'] = false;
            $cages[]       = $cage;
        }

        return $this->mergesingletons($cages, $ops, $maxsize);
    }

    /**
     * Merge any non-given 1-cell cage into an adjacent cage.
     *
     * A lone cell whose answer is printed as a label gives away the solution trivially.
     *
     * @param array $cages   Current list of cage arrays.
     * @param array $ops     Allowed arithmetic operators.
     * @param int   $maxsize Maximum cage size for this difficulty.
     * @return array Updated list of cage arrays with no isolated singletons.
     */
    private function mergesingletons(array $cages, array $ops, int $maxsize): array {
        $n = $this->size;

        // Build index mapping "r,c" => cage index.
        $buildindex = function (array $cages): array {
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
            $changed  = false;
            $cellidx  = $buildindex($cages);

            foreach ($cages as $i => $cage) {
                if (count($cage['cells']) !== 1 || !empty($cage['given'])) {
                    continue;
                }
                [$r, $c] = $cage['cells'][0];

                // Pass 1: merge into a non-given neighbor with room.
                $ni = null;
                foreach ([[0, 1], [0, -1], [1, 0], [-1, 0]] as [$dr, $dc]) {
                    $nr = $r + $dr;
                    $nc = $c + $dc;
                    if ($nr >= 0 && $nr < $n && $nc >= 0 && $nc < $n) {
                        $candidate = $cellidx["$nr,$nc"] ?? null;
                        if (
                            $candidate !== null &&
                            $candidate !== $i &&
                            empty($cages[$candidate]['given']) &&
                            count($cages[$candidate]['cells']) < $maxsize
                        ) {
                            $ni = $candidate;
                            break;
                        }
                    }
                }
                if ($ni !== null) {
                    // Standard merge.
                    $merged          = $this->assignoperation(
                        array_merge($cages[$ni]['cells'], $cage['cells']),
                        $ops,
                        0.0
                    );
                    $merged['given'] = false;
                    $cages[$ni]      = $merged;
                    unset($cages[$i]);
                    $cages   = array_values($cages);
                    $changed = true;
                    break;
                }

                // Pass 2: steal a border cell from a full non-given neighbor.
                $stolen = false;
                foreach ([[0, 1], [0, -1], [1, 0], [-1, 0]] as [$dr, $dc]) {
                    $nr = $r + $dr;
                    $nc = $c + $dc;
                    if ($nr < 0 || $nr >= $n || $nc < 0 || $nc >= $n) {
                        continue;
                    }
                    $donoridx = $cellidx["$nr,$nc"] ?? null;
                    if (
                        $donoridx === null ||
                        $donoridx === $i ||
                        !empty($cages[$donoridx]['given'])
                    ) {
                        continue;
                    }

                    $donor     = $cages[$donoridx];
                    $remaining = array_values(array_filter(
                        $donor['cells'],
                        fn($cell) => !($cell[0] === $nr && $cell[1] === $nc)
                    ));

                    // Donor must have >= 2 cells left and stay connected after the steal.
                    if (count($remaining) < 2) {
                        continue;
                    }
                    if (!$this->cellsconnected($remaining)) {
                        continue;
                    }

                    // Rebuild donor cage without the stolen cell.
                    $newdonor          = $this->assignoperation($remaining, $ops, 0.0);
                    $newdonor['given'] = false;
                    $cages[$donoridx]  = $newdonor;

                    // Merge singleton + stolen cell into a new 2-cell cage.
                    $newcage          = $this->assignoperation([[$r, $c], [$nr, $nc]], $ops, 0.0);
                    $newcage['given'] = false;
                    $cages[$i]        = $newcage;

                    $stolen  = true;
                    $changed = true;
                    break;
                }
                if ($stolen) {
                    break;
                }

                // Pass 3: last resort — merge into any non-given neighbor ignoring size,
                // or promote to given if difficulty allows it.
                [$gmin, $gmaxn] = $this->givenrange();
                $lastni = null;
                foreach ([[0, 1], [0, -1], [1, 0], [-1, 0]] as [$dr, $dc]) {
                    $nr = $r + $dr;
                    $nc = $c + $dc;
                    if ($nr >= 0 && $nr < $n && $nc >= 0 && $nc < $n) {
                        $candidate = $cellidx["$nr,$nc"] ?? null;
                        if (
                            $candidate !== null &&
                            $candidate !== $i &&
                            empty($cages[$candidate]['given'])
                        ) {
                            $lastni = $candidate;
                            break;
                        }
                    }
                }
                if ($lastni !== null) {
                    $merged          = $this->assignoperation(
                        array_merge($cages[$lastni]['cells'], $cage['cells']),
                        $ops,
                        0.0
                    );
                    $merged['given'] = false;
                    $cages[$lastni]  = $merged;
                    unset($cages[$i]);
                    $cages   = array_values($cages);
                } else {
                    // Truly surrounded by given cells — promote to given.
                    $cages[$i]['given'] = true;
                }
                $changed = true;
                break;
            }
        }

        return $cages;
    }

    /**
     * Check whether a set of cells forms a connected region.
     *
     * @param array $cells List of [row, col] pairs.
     * @return bool True if all cells are connected, false otherwise.
     */
    private function cellsconnected(array $cells): bool {
        if (count($cells) <= 1) {
            return true;
        }
        $set = [];
        foreach ($cells as [$r, $c]) {
            $set["$r,$c"] = [$r, $c];
        }
        $start   = reset($cells);
        $visited = [$start[0] . ',' . $start[1] => true];
        $queue   = [$start];
        while (!empty($queue)) {
            [$r, $c] = array_shift($queue);
            foreach ([[0, 1], [0, -1], [1, 0], [-1, 0]] as [$dr, $dc]) {
                $key = ($r + $dr) . ',' . ($c + $dc);
                if (isset($set[$key]) && !isset($visited[$key])) {
                    $visited[$key] = true;
                    $queue[]       = $set[$key];
                }
            }
        }
        return count($visited) === count($cells);
    }

    /**
     * Choose an arithmetic operation and compute the target for a cage.
     *
     * @param array $cells      List of [row, col] pairs in the cage.
     * @param array $ops        Allowed operator symbols.
     * @param float $hideopprob Probability (0.0–1.0) of hiding the operator.
     * @return array Cage array with keys cells, op, target, show_op.
     */
    private function assignoperation(array $cells, array $ops, float $hideopprob): array {
        $values = array_map(fn($cell) => $this->grid[$cell[0]][$cell[1]], $cells);
        $count  = count($cells);

        if ($count === 1) {
            return ['cells' => $cells, 'op' => null, 'target' => $values[0], 'show_op' => true];
        }

        $validops = [];

        if (in_array('+', $ops)) {
            $validops[] = ['+', array_sum($values)];
        }

        if ($count === 2 && in_array('-', $ops)) {
            $validops[] = ['-', abs($values[0] - $values[1])];
        }

        if (in_array('*', $ops)) {
            $prod = 1;
            foreach ($values as $v) {
                $prod *= $v;
            }
            $validops[] = ['*', $prod];
        }

        if ($count === 2 && in_array('/', $ops)) {
            $max = max($values);
            $min = min($values);
            if ($min > 0 && $max % $min === 0) {
                $validops[] = ['/', intdiv($max, $min)];
            }
        }

        if (empty($validops)) {
            $validops[] = ['+', array_sum($values)];
        }

        $chosen  = $validops[mt_rand(0, count($validops) - 1)];
        $showop  = ($hideopprob === 0.0) || mt_rand(0, 99) >= (int) ($hideopprob * 100);

        return [
            'cells'   => $cells,
            'op'      => $chosen[0],
            'target'  => $chosen[1],
            'show_op' => $showop,
        ];
    }

    /**
     * Return cage size limit, allowed operators, and hide-op probability for the current difficulty.
     *
     * @return array Three-element array: [maxsize, ops, hideopprob].
     */
    private function difficultyparams(): array {
        return match ($this->difficulty) {
            self::EASY   => [3, ['+', '-'],            0.0],
            self::MEDIUM => [4, ['+', '-', '*', '/'],  0.0],
            self::HARD   => [5, ['+', '-', '*', '/'],  0.3],
        };
    }

    /**
     * Return the probability (as a percentage integer) of expanding a cage by one cell.
     *
     * @return int Expansion probability out of 100.
     */
    private function expandprob(): int {
        return match ($this->difficulty) {
            self::EASY   => 40,
            self::MEDIUM => 60,
            self::HARD   => 70,
        };
    }

    /**
     * Return the [min, max] range for the number of single-cell "given" cages.
     *
     * @return array Two-element array: [min, max].
     */
    private function givenrange(): array {
        return match ($this->difficulty) {
            self::EASY   => [2, 3],
            self::MEDIUM => [0, 1],
            self::HARD   => [0, 0],
        };
    }
}
