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

$seedInput = isset($_GET['seed']) && is_string($_GET['seed'])
    ? trim($_GET['seed'])
    : '12345';
$seed = null;
if ($seedInput !== '' && preg_match('/^-?\d+$/', $seedInput) === 1) {
    $seed = (int)$seedInput;
}

$profiles = [
    DifficultyProfile::easy(),
    DifficultyProfile::medium(),
    DifficultyProfile::hard(),
];

$generated = [];
foreach ($profiles as $profile) {
    $puzzle = $generator->generate($profile, seed: $seed, symmetry180: true);
    $generated[] = [
        'difficulty' => htmlspecialchars($profile->name, ENT_QUOTES, 'UTF-8'),
        'givens' => $puzzle->givensCount(),
        'cells' => $puzzle->toArray(),
    ];
}

$safeSeedInput = htmlspecialchars($seedInput, ENT_QUOTES, 'UTF-8');

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sudoku Generator Example</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; color: #222; }
        form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: end;
            margin: 0 0 1rem;
        }
        label {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.95rem;
        }
        input, select, button {
            font: inherit;
            padding: 0.35rem 0.5rem;
        }
        .meta { margin: 0 0 1rem; font-size: 0.95rem; }
        .grids {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .grid-card {
            min-width: 20rem;
        }
        .grid-title {
            margin: 0 0 0.5rem;
            font-size: 1rem;
        }
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
    <form method="get" action="">
        <label>
            Seed (integer, optional)
            <input type="number" name="seed" value="<?= $safeSeedInput ?>" step="1">
        </label>

        <button type="submit">Generate 3 Grids</button>
    </form>

    <p class="meta">
        Seed: <strong><?= $seed === null ? 'random' : $seed ?></strong><br>
        Profiles: <strong>easy / medium / hard</strong>
    </p>
    <div class="grids">
<?php foreach ($generated as $grid): ?>
        <section class="grid-card">
            <h2 class="grid-title">
                <?= $grid['difficulty'] ?>
                (<?= $grid['givens'] ?> givens)
            </h2>
            <table aria-label="Generated Sudoku puzzle (<?= $grid['difficulty'] ?>)" role="grid">
                <tbody>
<?php for ($row = 0; $row < 9; $row++): ?>
                    <tr>
<?php for ($col = 0; $col < 9; $col++): ?>
<?php
    $value = $grid['cells'][$row * 9 + $col];
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
        </section>
<?php endforeach; ?>
    </div>
</body>
</html>
