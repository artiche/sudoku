<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Sudoku/Grid.php';
require_once __DIR__ . '/src/Sudoku/FullGridGenerator.php';
require_once __DIR__ . '/src/Sudoku/SolutionCounter.php';
require_once __DIR__ . '/src/Sudoku/DifficultyProfile.php';
require_once __DIR__ . '/src/Sudoku/HumanSolver.php';
require_once __DIR__ . '/src/Sudoku/PuzzleGenerator.php';

use Sudoku\DifficultyProfile;
use Sudoku\PuzzleGenerator;

$generator = new PuzzleGenerator();

// easy ou medium pour commencer
$profile = DifficultyProfile::easy();

$puzzle = $generator->generate($profile, seed: 12345, symmetry180: true);

echo "Generated Sudoku ({$profile->name})" . PHP_EOL;
echo $puzzle . PHP_EOL;
echo "Givens: " . $puzzle->givensCount() . PHP_EOL;
