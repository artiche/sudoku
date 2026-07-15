<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Sudoku/Grid.php';
require_once __DIR__ . '/../src/Sudoku/FullGridGenerator.php';
require_once __DIR__ . '/../src/Sudoku/SolutionCounter.php';
require_once __DIR__ . '/../src/Sudoku/DifficultyProfile.php';
require_once __DIR__ . '/../src/Sudoku/HumanSolver.php';
require_once __DIR__ . '/../src/Sudoku/PuzzleGenerator.php';

use Sudoku\DifficultyProfile;
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

// Summary
echo "\n";
echo "Results: $passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
