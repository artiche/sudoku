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

$profileMap = [
    'easy' => fn(): DifficultyProfile => DifficultyProfile::easy(),
    'medium' => fn(): DifficultyProfile => DifficultyProfile::medium(),
    'hard' => fn(): DifficultyProfile => DifficultyProfile::hard(),
];

$profileSuffix = [
    'easy' => '01',
    'medium' => '02',
    'hard' => '03',
];

$selectedProfileKey = isset($_GET['profile']) && is_string($_GET['profile'])
    ? strtolower(trim($_GET['profile']))
    : 'easy';
if (!isset($profileMap[$selectedProfileKey])) {
    $selectedProfileKey = 'easy';
}

$defaultSeedInput = date('dmY');

$seedInput = isset($_GET['seed']) && is_string($_GET['seed'])
    ? trim($_GET['seed'])
    : $defaultSeedInput;
if ($seedInput === '') {
    $seedInput = $defaultSeedInput;
}

$seedInputDigits = '';
if (preg_match('/^\d+$/', $seedInput) === 1) {
    $seedInputDigits = $seedInput;
}

if ($seedInputDigits === '') {
    $seedInputDigits = $defaultSeedInput;
    $seedInput = $defaultSeedInput;
}

$effectiveSeedText = $seedInputDigits . $profileSuffix[$selectedProfileKey];
$seed = (int)$effectiveSeedText;

$profile = $profileMap[$selectedProfileKey]();
$generated = $generator->generateWithSolution($profile, seed: $seed, symmetry180: true);
$cells = $generated['puzzle']->toArray();
$solutionCells = $generated['solution']->toArray();
$safeSeedInput = htmlspecialchars($seedInputDigits, ENT_QUOTES, 'UTF-8');
$safeSeedForUrl = rawurlencode($seedInputDigits);

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sudoku du Jour</title>
    <style>
        :root {
            --bg: #f2efe9;
            --surface: #fffdf9;
            --ink: #1f1c1a;
            --line: #7f756a;
            --line-strong: #2a2520;
            --accent: #b85c38;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 12% 10%, #fff6ea 0 16rem, transparent 17rem),
                radial-gradient(circle at 88% 90%, #efe8dc 0 14rem, transparent 15rem),
                var(--bg);
            min-height: 100vh;
            padding: 1rem;
        }

        .page {
            max-width: 46rem;
            margin: 0 auto;
            background: color-mix(in srgb, var(--surface) 90%, #ffffff 10%);
            border: 1px solid #dfd7cc;
            border-radius: 1rem;
            box-shadow: 0 10px 28px rgba(40, 31, 24, 0.1);
            padding: 1rem;
        }

        h1 {
            margin: 0 0 0.55rem;
            font-size: clamp(1.2rem, 4.2vw, 1.7rem);
            letter-spacing: 0.02em;
            text-align: center;
        }

        .title-link {
            color: inherit;
            text-decoration: none;
        }

        .title-link:hover {
            text-decoration: underline;
            text-underline-offset: 0.16rem;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.7rem;
            padding-bottom: 0.55rem;
            border-bottom: 1px solid #ddd2c4;
        }

        .seed-trigger {
            border: 0;
            background: transparent;
            font: inherit;
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--ink);
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
            text-underline-offset: 0.2rem;
        }

        .profiles {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.98rem;
        }

        .profiles a {
            color: #6a5b4f;
            text-decoration: none;
            font-weight: 500;
        }

        .profiles a.active {
            color: var(--ink);
            font-weight: 800;
        }

        .control-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.9rem;
            color: #5f5247;
            user-select: none;
        }

        .control-toggle input {
            width: 1rem;
            height: 1rem;
            accent-color: #cc6a41;
        }

        .selected-cell {
            outline: 3px solid #4f86d9;
            outline-offset: -2px;
            background: #d5e7ff;
        }

        td.selected-slot {
            box-shadow: inset 0 0 0 3px #4f86d9;
            background: #dcecff;
        }

        td.selected-slot .cell-edit {
            background: #d3e6ff;
            box-shadow: inset 0 0 0 2px #4f86d9;
        }

        td.selected-slot .cell-edit:focus {
            background: #c7ddff;
            box-shadow: inset 0 0 0 2px #3e76cc;
        }

        .grid-wrap {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        table {
            width: min(92vw, 34rem);
            height: min(92vw, 34rem);
            border-collapse: collapse;
            border: 3px solid var(--line-strong);
            table-layout: fixed;
            background: #fff;
        }

        td {
            border: 1px solid var(--line);
            width: calc(100% / 9);
            height: calc(100% / 9);
            text-align: center;
            vertical-align: middle;
            padding: 0;
            position: relative;
            background: #fff;
        }

        .cell-given {
            display: flex;
            width: 100%;
            height: 100%;
            align-items: center;
            justify-content: center;
            font-size: clamp(1rem, 3.8vw, 1.5rem);
            font-weight: 700;
            color: #161311;
            background: #ece5da;
        }

        .cell-edit {
            width: 100%;
            height: 100%;
            border: 0;
            outline: 0;
            background: #dfeeff;
            padding: 0;
            position: relative;
            box-shadow: inset 0 0 0 1px #b4cdf8;
            cursor: pointer;
        }

        .cell-edit:focus {
            background: #c8e0ff;
            box-shadow: inset 0 0 0 2px #4f86d9;
        }

        .cell-value {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: inherit;
            font-size: clamp(1rem, 3.8vw, 1.45rem);
            font-weight: 700;
            color: #125a2e;
            pointer-events: none;
        }

        .cell-notes {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(3, 1fr);
            align-items: center;
            justify-items: center;
            font-size: clamp(0.62rem, 2.95vw, 0.9rem);
            font-weight: 600;
            color: #365b95;
            pointer-events: none;
            transform: translateY(-1px);
        }

        .note {
            opacity: 0;
            transform: scale(0.94);
            transition: opacity 120ms ease;
        }

        .note.active {
            opacity: 1;
        }

        .cell-edit.has-value .cell-notes {
            display: none;
        }

        .cell-edit.is-error .cell-value {
            color: #b32323;
        }

        .cell-edit.is-solved .cell-value {
            color: #1f7b3a;
        }

        .cell-edit.is-solved {
            background: #dff4e5;
            box-shadow: inset 0 0 0 1px #98cfa9;
        }

        .play-controls {
            margin: 0.75rem auto 0;
            width: min(92vw, 21rem);
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .keypad {
            margin: 0.9rem auto 0;
            width: min(92vw, 21rem);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.45rem;
        }

        .keypad.hidden {
            display: none;
        }

        .key {
            border: 1px solid #b8ac9d;
            border-radius: 0.6rem;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 1.15rem;
            font-weight: 700;
            padding: 0.55rem 0;
            cursor: pointer;
        }

        .key:active {
            background: #f2e9df;
        }

        .key.active-note {
            border-color: #3f73c9;
            background: #d8e7ff;
            color: #133f88;
            box-shadow: inset 0 0 0 1px #86adea;
        }

        .key.active-final {
            border-color: #2d8a4d;
            background: #dff4e5;
            color: #1f6c3a;
            box-shadow: inset 0 0 0 1px #84c59a;
        }

        .key-clear {
            grid-column: span 3;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .solved-panel {
            margin: 0.95rem auto 0;
            width: min(92vw, 21rem);
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            padding: 0.65rem 0.7rem;
            border: 1px solid #9acdab;
            border-radius: 0.65rem;
            background: #ecf9f0;
        }

        .solved-panel.visible {
            display: flex;
        }

        .solved-msg {
            font-size: 1.05rem;
            font-weight: 800;
            color: #1f7b3a;
        }

        .reset-btn {
            border: 1px solid #6ea787;
            background: #fff;
            color: #2a6e43;
            border-radius: 0.55rem;
            padding: 0.46rem 0.7rem;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        dialog {
            border: 0;
            border-radius: 0.8rem;
            padding: 1rem;
            width: min(90vw, 22rem);
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.2);
        }

        dialog::backdrop {
            background: rgba(0, 0, 0, 0.35);
        }

        .dialog-title {
            margin: 0 0 0.55rem;
            font-size: 1rem;
            font-weight: 700;
        }

        .dialog-input {
            width: 100%;
            border: 1px solid #b8ac9d;
            border-radius: 0.55rem;
            padding: 0.6rem 0.7rem;
            font: inherit;
            margin-bottom: 0.7rem;
        }

        .dialog-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.55rem;
        }

        .dialog-btn {
            border: 1px solid #b8ac9d;
            border-radius: 0.55rem;
            padding: 0.48rem 0.72rem;
            font: inherit;
            background: #fff;
            cursor: pointer;
        }

        .dialog-btn.primary {
            border-color: #8a401f;
            background: linear-gradient(180deg, #cd6e47 0%, var(--accent) 100%);
            color: #fff8f2;
            font-weight: 700;
        }

        .block-right {
            border-right: 3px solid var(--line-strong);
        }

        .block-bottom {
            border-bottom: 3px solid var(--line-strong);
        }

        @media (min-width: 640px) {
            body {
                padding: 1.4rem;
            }

            .page {
                padding: 1.2rem 1.25rem 1.4rem;
            }

            .toolbar {
                margin-bottom: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <h1><a href="/sudoku" class="title-link">Sudoku</a></h1>

        <div class="toolbar">
            <button type="button" class="seed-trigger" id="seed-trigger">seed <?= $safeSeedInput ?></button>

            <nav class="profiles" aria-label="Profils">
                <a href="?profile=easy&amp;seed=<?= $safeSeedForUrl ?>" class="<?= $selectedProfileKey === 'easy' ? 'active' : '' ?>">easy</a>
                <a href="?profile=medium&amp;seed=<?= $safeSeedForUrl ?>" class="<?= $selectedProfileKey === 'medium' ? 'active' : '' ?>">medium</a>
                <a href="?profile=hard&amp;seed=<?= $safeSeedForUrl ?>" class="<?= $selectedProfileKey === 'hard' ? 'active' : '' ?>">hard</a>
            </nav>
        </div>

        <div class="grid-wrap">
            <table aria-label="Generated Sudoku puzzle" role="grid">
                <tbody>
<?php for ($row = 0; $row < 9; $row++): ?>
                    <tr>
<?php for ($col = 0; $col < 9; $col++): ?>
<?php
    $idx = $row * 9 + $col;
    $value = $cells[$row * 9 + $col];
    $classes = [];
    if ($col % 3 === 2 && $col !== 8) {
        $classes[] = 'block-right';
    }
    if ($row % 3 === 2 && $row !== 8) {
        $classes[] = 'block-bottom';
    }
?>
                        <td<?= $classes === [] ? '' : ' class="' . implode(' ', $classes) . '"' ?>>
<?php if ($value === 0): ?>
                            <button type="button" class="cell-edit" data-idx="<?= $idx ?>" aria-label="Ligne <?= $row + 1 ?> colonne <?= $col + 1 ?>">
                                <span class="cell-value"></span>
                                <span class="cell-notes" aria-hidden="true">
<?php for ($n = 1; $n <= 9; $n++): ?>
                                    <span class="note" data-note="<?= $n ?>"><?= $n ?></span>
<?php endfor; ?>
                                </span>
                            </button>
<?php else: ?>
                            <span class="cell-given"><?= $value ?></span>
<?php endif; ?>
                        </td>
<?php endfor; ?>
                    </tr>
<?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="play-controls">
            <label class="control-toggle">
                <input type="checkbox" id="draft-toggle">
                draft
            </label>

            <label class="control-toggle">
                <input type="checkbox" id="error-toggle">
                errors
            </label>
        </div>

        <div class="keypad" aria-label="Pave numerique">
            <button type="button" class="key" data-digit="1">1</button>
            <button type="button" class="key" data-digit="2">2</button>
            <button type="button" class="key" data-digit="3">3</button>
            <button type="button" class="key" data-digit="4">4</button>
            <button type="button" class="key" data-digit="5">5</button>
            <button type="button" class="key" data-digit="6">6</button>
            <button type="button" class="key" data-digit="7">7</button>
            <button type="button" class="key" data-digit="8">8</button>
            <button type="button" class="key" data-digit="9">9</button>
            <button type="button" class="key key-clear" data-action="clear">Clear selected cell</button>
        </div>

        <div class="solved-panel" id="solved-panel" aria-live="polite">
            <span class="solved-msg">Bravo !</span>
            <button type="button" class="reset-btn" id="reset-grid-btn">Reset grid</button>
        </div>

        <dialog id="seed-dialog">
            <form method="dialog" id="seed-dialog-form">
                <p class="dialog-title">Changer la seed</p>
                <input
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    id="seed-dialog-input"
                    class="dialog-input"
                    value="<?= $safeSeedInput ?>"
                    aria-label="Seed"
                >
                <div class="dialog-actions">
                    <button type="submit" class="dialog-btn" value="cancel">Annuler</button>
                    <button type="submit" class="dialog-btn primary" value="ok">Valider</button>
                </div>
            </form>
        </dialog>
    </main>

    <script>
        (function () {
            const cells = Array.from(document.querySelectorAll('.cell-edit'));
            const keypadButtons = Array.from(document.querySelectorAll('.key'));
            const draftToggle = document.getElementById('draft-toggle');
            const errorToggle = document.getElementById('error-toggle');
            const keypad = document.querySelector('.keypad');
            const solvedPanel = document.getElementById('solved-panel');
            const resetGridBtn = document.getElementById('reset-grid-btn');
            const seedTrigger = document.getElementById('seed-trigger');
            const dialog = document.getElementById('seed-dialog');
            const dialogForm = document.getElementById('seed-dialog-form');
            const dialogInput = document.getElementById('seed-dialog-input');
            const currentProfile = '<?= $selectedProfileKey ?>';
            const solutionCells = <?= json_encode($solutionCells) ?>;
            const storageKey = 'sudoku:plays:v1';
            const puzzleStorageKey = 'seed:<?= $effectiveSeedText ?>';
            const maxSavedPuzzles = 12;

            const byIndex = new Map();
            const stateByIndex = new Map();
            let activeInput = null;
            let activeSlot = null;
            const digitButtons = keypadButtons.filter(function (button) {
                return !!button.dataset.digit;
            });

            cells.forEach(function (cell) {
                const idx = Number(cell.dataset.idx || '-1');
                if (idx >= 0) {
                    byIndex.set(idx, cell);
                    stateByIndex.set(idx, { value: '', notes: new Set() });
                }
            });

            function loadEntriesFromStorage() {
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (!raw) {
                        return [];
                    }
                    const parsed = JSON.parse(raw);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (error) {
                    return [];
                }
            }

            function saveEntriesToStorage(entries) {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(entries));
                } catch (error) {
                    // Ignore storage errors (quota/private mode), gameplay still works in-memory.
                }
            }

            function buildStatePayload() {
                const payloadCells = {};

                for (const [idx, state] of stateByIndex.entries()) {
                    const noteList = Array.from(state.notes).sort(function (a, b) {
                        return a - b;
                    });

                    if (state.value === '' && noteList.length === 0) {
                        continue;
                    }

                    payloadCells[String(idx)] = {
                        v: state.value,
                        n: noteList
                    };
                }

                return {
                    cells: payloadCells,
                    settings: {
                        draft: !!(draftToggle && draftToggle.checked),
                        errors: !!(errorToggle && errorToggle.checked)
                    }
                };
            }

            function persistCurrentState() {
                const entries = loadEntriesFromStorage();
                const filtered = entries.filter(function (entry) {
                    return entry && entry.key !== puzzleStorageKey;
                });

                const payload = buildStatePayload();
                const hasCells = Object.keys(payload.cells).length > 0;
                const settings = payload.settings || {};
                const hasSettings = settings.draft === true || settings.errors === true;
                const hasData = hasCells || hasSettings;

                if (hasData) {
                    filtered.push({
                        key: puzzleStorageKey,
                        updatedAt: Date.now(),
                        payload: payload
                    });
                }

                filtered.sort(function (a, b) {
                    return Number(a.updatedAt || 0) - Number(b.updatedAt || 0);
                });

                while (filtered.length > maxSavedPuzzles) {
                    filtered.shift();
                }

                saveEntriesToStorage(filtered);
            }

            function loadPersistedState() {
                const entries = loadEntriesFromStorage();
                const current = entries.find(function (entry) {
                    return entry && entry.key === puzzleStorageKey;
                });

                if (!current || !current.payload || typeof current.payload !== 'object') {
                    return;
                }

                const settings = current.payload.settings;
                if (settings && typeof settings === 'object') {
                    if (draftToggle && typeof settings.draft === 'boolean') {
                        draftToggle.checked = settings.draft;
                    }
                    if (errorToggle && typeof settings.errors === 'boolean') {
                        errorToggle.checked = settings.errors;
                    }
                }

                const payloadCells = (current.payload.cells && typeof current.payload.cells === 'object')
                    ? current.payload.cells
                    : {};

                Object.keys(payloadCells).forEach(function (idxText) {
                    const idx = Number(idxText);
                    if (!stateByIndex.has(idx)) {
                        return;
                    }

                    const record = payloadCells[idxText];
                    if (!record || typeof record !== 'object') {
                        return;
                    }

                    const value = typeof record.v === 'string' ? record.v : '';
                    const notes = Array.isArray(record.n) ? record.n : [];

                    const state = stateByIndex.get(idx);
                    if (!state) {
                        return;
                    }

                    state.value = /^[1-9]$/.test(value) ? value : '';
                    state.notes.clear();

                    if (state.value === '') {
                        notes.forEach(function (digit) {
                            const d = Number(digit);
                            if (d >= 1 && d <= 9) {
                                state.notes.add(d);
                            }
                        });
                    }
                });
            }

            function renderCell(cell) {
                const idx = Number(cell.dataset.idx || '-1');
                const state = stateByIndex.get(idx);
                if (!state) {
                    return;
                }

                const valueNode = cell.querySelector('.cell-value');
                if (valueNode) {
                    valueNode.textContent = state.value;
                }

                if (state.value !== '') {
                    cell.classList.add('has-value');
                } else {
                    cell.classList.remove('has-value');
                }

                const noteNodes = cell.querySelectorAll('.note');
                noteNodes.forEach(function (node) {
                    const noteDigit = Number(node.getAttribute('data-note') || '0');
                    node.classList.toggle('active', state.notes.has(noteDigit));
                });
            }

            function expectedDigitAt(idx) {
                return Number(solutionCells[idx] || 0);
            }

            function computeSolvedState() {
                for (const [idx, state] of stateByIndex.entries()) {
                    if (state.value === '') {
                        return false;
                    }
                    if (Number(state.value) !== expectedDigitAt(idx)) {
                        return false;
                    }
                }
                return true;
            }

            function refreshValidationState() {
                const showErrors = !(errorToggle && !errorToggle.checked);
                const solved = computeSolvedState();

                cells.forEach(function (cell) {
                    const idx = Number(cell.dataset.idx || '-1');
                    const state = stateByIndex.get(idx);
                    if (!state) {
                        return;
                    }

                    cell.classList.remove('is-error');
                    cell.classList.remove('is-solved');

                    if (state.value === '') {
                        return;
                    }

                    const entered = Number(state.value);
                    const expected = expectedDigitAt(idx);

                    if (showErrors && entered !== expected) {
                        cell.classList.add('is-error');
                        return;
                    }

                    if (solved && entered === expected) {
                        cell.classList.add('is-solved');
                    }
                });

                if (keypad && solvedPanel) {
                    keypad.classList.toggle('hidden', solved);
                    solvedPanel.classList.toggle('visible', solved);
                }
            }

            function refreshKeypadHighlights() {
                digitButtons.forEach(function (button) {
                    button.classList.remove('active-note');
                    button.classList.remove('active-final');
                });

                if (!activeInput) {
                    return;
                }

                const idx = Number(activeInput.dataset.idx || '-1');
                const state = stateByIndex.get(idx);
                if (!state) {
                    return;
                }

                digitButtons.forEach(function (button) {
                    const digit = Number(button.dataset.digit || '0');
                    if (state.value !== '' && Number(state.value) === digit) {
                        button.classList.add('active-final');
                    } else if (state.notes.has(digit)) {
                        button.classList.add('active-note');
                    }
                });
            }

            function setActiveCell(cell) {
                if (activeInput) {
                    activeInput.classList.remove('selected-cell');
                }
                if (activeSlot) {
                    activeSlot.classList.remove('selected-slot');
                }
                activeInput = cell;
                activeInput.classList.add('selected-cell');

                const slot = cell.closest('td');
                if (slot) {
                    activeSlot = slot;
                    activeSlot.classList.add('selected-slot');
                } else {
                    activeSlot = null;
                }

                activeInput.focus();
                refreshKeypadHighlights();
            }

            function moveActiveByArrow(key) {
                if (!activeInput) {
                    return false;
                }

                const idx = Number(activeInput.dataset.idx || '-1');
                if (idx < 0) {
                    return false;
                }

                let step = 0;
                let boundaryCheck = null;
                if (key === 'ArrowLeft') {
                    step = -1;
                    boundaryCheck = function (i) { return i % 9 !== 0; };
                } else if (key === 'ArrowRight') {
                    step = 1;
                    boundaryCheck = function (i) { return i % 9 !== 8; };
                } else if (key === 'ArrowUp') {
                    step = -9;
                    boundaryCheck = function (i) { return i >= 9; };
                } else if (key === 'ArrowDown') {
                    step = 9;
                    boundaryCheck = function (i) { return i < 72; };
                }

                if (step === 0 || boundaryCheck === null) {
                    return false;
                }

                let cursor = idx;
                while (boundaryCheck(cursor)) {
                    cursor += step;
                    if (byIndex.has(cursor)) {
                        const next = byIndex.get(cursor);
                        setActiveCell(next);
                        return true;
                    }
                }

                return false;
            }

            function applyDigitToActiveCell(digit) {
                if (!activeInput) {
                    return;
                }

                const idx = Number(activeInput.dataset.idx || '-1');
                const state = stateByIndex.get(idx);
                if (!state) {
                    return;
                }

                const isDraft = !!(draftToggle && draftToggle.checked);
                if (isDraft) {
                    if (state.value !== '') {
                        return;
                    }
                    if (state.notes.has(digit)) {
                        state.notes.delete(digit);
                    } else {
                        state.notes.add(digit);
                    }
                } else {
                    state.value = String(digit);
                    state.notes.clear();
                }

                renderCell(activeInput);
                refreshKeypadHighlights();
                refreshValidationState();
                persistCurrentState();
            }

            function clearActiveCell() {
                if (!activeInput) {
                    return;
                }

                const idx = Number(activeInput.dataset.idx || '-1');
                const state = stateByIndex.get(idx);
                if (!state) {
                    return;
                }

                if (state.value !== '') {
                    state.value = '';
                } else {
                    state.notes.clear();
                }

                renderCell(activeInput);
                refreshKeypadHighlights();
                refreshValidationState();
                persistCurrentState();
            }

            cells.forEach(function (cell) {
                renderCell(cell);

                cell.addEventListener('click', function () {
                    setActiveCell(this);
                });

                cell.addEventListener('keydown', function (event) {
                    if (event.key >= '1' && event.key <= '9') {
                        event.preventDefault();
                        applyDigitToActiveCell(Number(event.key));
                        return;
                    }

                    if (event.key === 'Backspace' || event.key === 'Delete') {
                        event.preventDefault();
                        clearActiveCell();
                        return;
                    }

                    if (moveActiveByArrow(event.key)) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                });
            });

            loadPersistedState();
            cells.forEach(function (cell) {
                renderCell(cell);
            });

            if (cells.length > 0) {
                setActiveCell(cells[0]);
            }

            refreshValidationState();

            keypadButtons.forEach(function (button) {
                button.addEventListener('mousedown', function (event) {
                    // Keep visual selection on the grid cell when tapping keypad buttons.
                    event.preventDefault();
                });

                button.addEventListener('click', function () {
                    const action = button.dataset.action || '';
                    if (action === 'clear') {
                        clearActiveCell();
                        return;
                    }

                    const digit = button.dataset.digit || '';
                    if (digit >= '1' && digit <= '9') {
                        applyDigitToActiveCell(Number(digit));
                    }
                });
            });

            if (draftToggle) {
                const onDraftToggle = function () {
                    refreshKeypadHighlights();
                    persistCurrentState();
                };
                draftToggle.addEventListener('input', onDraftToggle);
                draftToggle.addEventListener('change', onDraftToggle);
            }

            if (errorToggle) {
                const onErrorToggle = function () {
                    refreshValidationState();
                    persistCurrentState();
                };
                errorToggle.addEventListener('input', onErrorToggle);
                errorToggle.addEventListener('change', onErrorToggle);
            }

            if (resetGridBtn) {
                resetGridBtn.addEventListener('click', function () {
                    for (const [idx, state] of stateByIndex.entries()) {
                        state.value = '';
                        state.notes.clear();
                        const cell = byIndex.get(idx);
                        if (cell) {
                            renderCell(cell);
                        }
                    }

                    refreshValidationState();
                    refreshKeypadHighlights();
                    persistCurrentState();
                });
            }

            document.addEventListener('keydown', function (event) {
                if (!activeInput) {
                    return;
                }

                if (dialog && dialog.open) {
                    return;
                }

                const target = event.target;
                if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement) {
                    return;
                }

                if (target instanceof HTMLElement && target.closest('.cell-edit')) {
                    return;
                }

                if (moveActiveByArrow(event.key)) {
                    event.preventDefault();
                }
            });

            if (seedTrigger && dialog && dialogForm && dialogInput) {
                seedTrigger.addEventListener('click', function () {
                    dialog.showModal();
                    dialogInput.focus();
                    dialogInput.select();
                });

                dialogInput.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '');
                });

                dialogForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    const submitter = event.submitter;
                    if (!submitter || submitter.value !== 'ok') {
                        dialog.close();
                        return;
                    }

                    const nextSeed = dialogInput.value.trim();
                    const finalSeed = nextSeed !== '' ? nextSeed : '<?= $safeSeedInput ?>';
                    const url = new URL(window.location.href);
                    url.searchParams.set('profile', currentProfile);
                    url.searchParams.set('seed', finalSeed);
                    window.location.href = url.toString();
                });
            }
        })();
    </script>
</body>
</html>
