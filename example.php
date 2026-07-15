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

$profile = DifficultyProfile::easy();
$puzzle = $generator->generate($profile, seed: 12345, symmetry180: true);
$cells = $puzzle->toArray();
$difficulty = htmlspecialchars($profile->name, ENT_QUOTES, 'UTF-8');
$givens = $puzzle->givensCount();

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sudoku Generator Example</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; color: #222; }
        .meta { margin: 0 0 1rem; font-size: 0.95rem; }
        table { border-collapse: collapse; border: 3px solid #333; }
        td {
            width: 2.5rem;
            height: 2.5rem;
            border: 1px solid #666;
            text-align: center;
            vertical-align: middle;
            font-size: 1.25rem;
        }
        .block-right { border-right: 3px solid #333; }
        .block-bottom { border-bottom: 3px solid #333; }
    </style>
</head>
<body>
    <p class="meta">
        Difficulty: <strong><?= $difficulty ?></strong><br>
        Givens: <strong><?= $givens ?></strong>
    </p>
    <table aria-label="Generated Sudoku puzzle" role="grid">
        <tbody>
<?php for ($row = 0; $row < 9; $row++): ?>
            <tr>
<?php for ($col = 0; $col < 9; $col++): ?>
<?php
    $value = $cells[$row * 9 + $col];
    $classes = [];
    if ($col % 3 === 2 && $col !== 8) {
        $classes[] = 'block-right';
    }
    if ($row % 3 === 2 && $row !== 8) {
        $classes[] = 'block-bottom';
    }
?>
                <td<?= $classes === [] ? '' : ' class="' . implode(' ', $classes) . '"' ?>><?= $value === 0 ? '' : $value ?></td>
<?php endfor; ?>
            </tr>
<?php endfor; ?>
        </tbody>
    </table>
</body>
</html>
