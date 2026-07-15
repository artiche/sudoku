<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Sudoku/Grid.php';
require_once __DIR__ . '/../src/Sudoku/FullGridGenerator.php';
require_once __DIR__ . '/../src/Sudoku/SolutionCounter.php';
require_once __DIR__ . '/../src/Sudoku/DifficultyProfile.php';
require_once __DIR__ . '/../src/Sudoku/HumanSolver.php';
require_once __DIR__ . '/../src/Sudoku/PuzzleGenerator.php';

use Sudoku\DifficultyProfile;
use Sudoku\Grid;
use Sudoku\HumanSolver;
use Sudoku\HumanSolverState;
use Sudoku\PuzzleGenerator;
use Sudoku\SolutionCounter;

$passed = 0;
$failed = 0;

function assert_true(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $message\n";
        $passed++;
    } else {
        echo "[FAIL] $message\n";
        $failed++;
    }
}

/** @return array{rows:int[],cols:int[],boxes:int[]} */
function givens_distribution(Grid $grid): array
{
    $rows = array_fill(0, 9, 0);
    $cols = array_fill(0, 9, 0);
    $boxes = array_fill(0, 9, 0);

    for ($r = 0; $r < 9; $r++) {
        for ($c = 0; $c < 9; $c++) {
            if ($grid->get($r, $c) === 0) {
                continue;
            }

            $rows[$r]++;
            $cols[$c]++;
            $boxes[intdiv($r, 3) * 3 + intdiv($c, 3)]++;
        }
    }

    return ['rows' => $rows, 'cols' => $cols, 'boxes' => $boxes];
}

$generator = new PuzzleGenerator();
$counter   = new SolutionCounter();

// --- Easy profile tests ---

$profile = DifficultyProfile::easy();

// Test 1: puzzle has at least one empty cell (not 81 givens)
$puzzle = $generator->generate($profile, seed: 12345);
assert_true(
    $puzzle->givensCount() < 81,
    "Easy puzzle has at least one empty cell (givens < 81)"
);

// Test 2: givens >= minGivens
assert_true(
    $puzzle->givensCount() >= $profile->minGivens,
    "Easy puzzle givens ({$puzzle->givensCount()}) >= minGivens ({$profile->minGivens})"
);

// Test 3: givens <= maxGivens
assert_true(
    $puzzle->givensCount() <= $profile->maxGivens,
    "Easy puzzle givens ({$puzzle->givensCount()}) <= maxGivens ({$profile->maxGivens})"
);

// Test 4: exactly one solution
assert_true(
    $counter->countSolutions($puzzle, 2) === 1,
    "Easy puzzle has exactly one solution"
);

$dist = givens_distribution($puzzle);
assert_true(
    max($dist['boxes']) - min($dist['boxes']) <= 3,
    "Easy puzzle has balanced givens across 3x3 boxes"
);
assert_true(
    min($dist['boxes']) >= 1,
    "Easy puzzle keeps at least one given in each 3x3 box"
);

// Test 5: multiple seeds all produce valid puzzles
$allValid = true;
for ($seed = 1; $seed <= 5; $seed++) {
    $p = $generator->generate($profile, seed: $seed);
    $givens = $p->givensCount();
    $sols   = $counter->countSolutions($p, 2);
    if ($givens < $profile->minGivens || $givens > $profile->maxGivens || $sols !== 1) {
        $allValid = false;
        echo "  Seed $seed failed: givens=$givens solutions=$sols\n";
    }
}
assert_true($allValid, "Easy profile: 5 different seeds all produce valid puzzles within givens range");

// --- Medium profile tests ---

$medProfile = DifficultyProfile::medium();

$medPuzzle = $generator->generate($medProfile, seed: 42);
assert_true(
    $medPuzzle->givensCount() >= $medProfile->minGivens && $medPuzzle->givensCount() <= $medProfile->maxGivens,
    "Medium puzzle givens ({$medPuzzle->givensCount()}) in [{$medProfile->minGivens}, {$medProfile->maxGivens}]"
);
assert_true(
    $counter->countSolutions($medPuzzle, 2) === 1,
    "Medium puzzle has exactly one solution"
);

$medDist = givens_distribution($medPuzzle);
assert_true(
    max($medDist['boxes']) - min($medDist['boxes']) <= 3,
    "Medium puzzle has balanced givens across 3x3 boxes"
);

// --- Targeted technique test: naked_pair elimination ---

$solver = new HumanSolver();
$state = new HumanSolverState(new Grid(), array_fill(0, 81, 0));

// Row 0 setup:
// c0 = {1,2}, c1 = {1,2} (naked pair), c2 = {1,2,3} should become {3}
$state->candidateMasks[0] = (1 << 0) | (1 << 1);
$state->candidateMasks[1] = (1 << 0) | (1 << 1);
$state->candidateMasks[2] = (1 << 0) | (1 << 1) | (1 << 2);

$ref = new ReflectionClass($solver);
$method = $ref->getMethod('applyNakedPairs');
$eliminations = $method->invoke($solver, $state);

assert_true(
    $eliminations >= 2,
    "Naked pair performs at least two candidate eliminations in a unit"
);

assert_true(
    $state->candidateMasks[2] === (1 << 2),
    "Naked pair removes paired digits from other cells in the unit"
);

// --- Targeted technique test: pointing pair/triple elimination ---

$statePointing = new HumanSolverState(new Grid(), array_fill(0, 81, 0));

// Box (r0..r2,c0..c2): digit 5 appears only on row 0 inside the box.
$statePointing->candidateMasks[0] = (1 << 4) | (1 << 0); // r0c0 {1,5}
$statePointing->candidateMasks[1] = (1 << 4) | (1 << 1); // r0c1 {2,5}

// Same row but outside the box: digit 5 must be removed.
$statePointing->candidateMasks[3] = (1 << 4) | (1 << 2); // r0c3 {3,5}

// Different row, same column pattern is not targeted by this row pointing setup.
$statePointing->candidateMasks[12] = (1 << 4) | (1 << 3); // r1c3 {4,5}

$methodPointing = $ref->getMethod('applyPointingPairs');
$pointingEliminations = $methodPointing->invoke($solver, $statePointing);

assert_true(
    $pointingEliminations >= 1,
    "Pointing pair/triple performs candidate elimination outside the box"
);

assert_true(
    ($statePointing->candidateMasks[3] & (1 << 4)) === 0,
    "Pointing pair/triple removes digit from aligned row outside the box"
);

assert_true(
    ($statePointing->candidateMasks[12] & (1 << 4)) !== 0,
    "Pointing pair/triple keeps unrelated candidates unchanged"
);

// --- Targeted technique test: x-wing rows elimination ---

$stateXWingRows = new HumanSolverState(new Grid(), array_fill(0, 81, 0));

// Digit 7 x-wing on rows r0 and r3, columns c1 and c6.
$stateXWingRows->candidateMasks[0 * 9 + 1] = (1 << 6);
$stateXWingRows->candidateMasks[0 * 9 + 6] = (1 << 6);
$stateXWingRows->candidateMasks[3 * 9 + 1] = (1 << 6);
$stateXWingRows->candidateMasks[3 * 9 + 6] = (1 << 6);

// Candidates to eliminate in same columns on other rows.
$stateXWingRows->candidateMasks[5 * 9 + 1] = (1 << 6) | (1 << 1);
$stateXWingRows->candidateMasks[8 * 9 + 6] = (1 << 6) | (1 << 2);

$methodXWing = $ref->getMethod('applyXWing');
$xWingRowsEliminations = $methodXWing->invoke($solver, $stateXWingRows);

assert_true(
    $xWingRowsEliminations >= 2,
    "X-wing (rows) performs eliminations on matching columns"
);

assert_true(
    ($stateXWingRows->candidateMasks[5 * 9 + 1] & (1 << 6)) === 0,
    "X-wing (rows) removes digit from column c1 outside wing rows"
);

assert_true(
    ($stateXWingRows->candidateMasks[8 * 9 + 6] & (1 << 6)) === 0,
    "X-wing (rows) removes digit from column c6 outside wing rows"
);

// --- Targeted technique test: x-wing columns elimination ---

$stateXWingCols = new HumanSolverState(new Grid(), array_fill(0, 81, 0));

// Digit 4 x-wing on columns c2 and c7, rows r1 and r5.
$stateXWingCols->candidateMasks[1 * 9 + 2] = (1 << 3);
$stateXWingCols->candidateMasks[5 * 9 + 2] = (1 << 3);
$stateXWingCols->candidateMasks[1 * 9 + 7] = (1 << 3);
$stateXWingCols->candidateMasks[5 * 9 + 7] = (1 << 3);

// Candidates to eliminate in same rows on other columns.
$stateXWingCols->candidateMasks[1 * 9 + 0] = (1 << 3) | (1 << 0);
$stateXWingCols->candidateMasks[5 * 9 + 8] = (1 << 3) | (1 << 1);

$xWingColsEliminations = $methodXWing->invoke($solver, $stateXWingCols);

assert_true(
    $xWingColsEliminations >= 2,
    "X-wing (cols) performs eliminations on matching rows"
);

assert_true(
    ($stateXWingCols->candidateMasks[1 * 9 + 0] & (1 << 3)) === 0,
    "X-wing (cols) removes digit from row r1 outside wing columns"
);

assert_true(
    ($stateXWingCols->candidateMasks[5 * 9 + 8] & (1 << 3)) === 0,
    "X-wing (cols) removes digit from row r5 outside wing columns"
);

// Summary
echo "\n";
echo "Results: $passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
