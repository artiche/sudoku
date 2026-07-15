<?php

declare(strict_types=1);

namespace Sudoku;

final class HumanSolverResult
{
    public function __construct(
        public bool $solved,
        public int $score,
        /** @var string[] */
        public array $usedTechniques
    ) {}
}

final class HumanSolverState
{
    /** @var int[] bitmask candidates per cell index (0 for solved cells) */
    public array $candidateMasks;

    public function __construct(
        public Grid $grid,
        array $candidateMasks
    ) {
        $this->candidateMasks = $candidateMasks;
    }
}

final class HumanSolver
{
    /** @var int[][] */
    private array $units;

    /** @var int[][] peers by cell index */
    private array $peers;

    public function __construct()
    {
        $this->units = $this->buildUnits();
        $this->peers = $this->buildPeers();
    }

    /**
     * Solve with limited human-like techniques and compute a score.
     */
    public function solveWithScore(Grid $grid, DifficultyProfile $profile): HumanSolverResult
    {
        $state = $this->createState($grid->copy());
        $score = 0;
        $used = [];

        while (true) {
            $progress = false;

            if (in_array('naked_single', $profile->allowedTechniques, true)) {
                $applied = $this->applyNakedSingles($state);
                if ($applied > 0) {
                    $progress = true;
                    $score += $applied * 10;
                    $used['naked_single'] = true;
                }
            }

            if (!$progress && in_array('hidden_single', $profile->allowedTechniques, true)) {
                $applied = $this->applyHiddenSingles($state);
                if ($applied > 0) {
                    $progress = true;
                    $score += $applied * 20;
                    $used['hidden_single'] = true;
                }
            }

            if (!$progress && in_array('naked_pair', $profile->allowedTechniques, true)) {
                $applied = $this->applyNakedPairs($state);
                if ($applied > 0) {
                    $progress = true;
                    $score += $applied * 50;
                    $used['naked_pair'] = true;
                }
            }

            if (!$progress && in_array('pointing_pair', $profile->allowedTechniques, true)) {
                $applied = $this->applyPointingPairs($state);
                if ($applied > 0) {
                    $progress = true;
                    $score += $applied * 70;
                    $used['pointing_pair'] = true;
                }
            }

            if (!$progress && in_array('x_wing', $profile->allowedTechniques, true)) {
                $applied = $this->applyXWing($state);
                if ($applied > 0) {
                    $progress = true;
                    $score += $applied * 120;
                    $used['x_wing'] = true;
                }
            }

            if (!$progress) break;
        }

        return new HumanSolverResult(
            solved: $this->isSolved($state->grid),
            score: $score,
            usedTechniques: array_keys($used)
        );
    }

    private function isSolved(Grid $grid): bool
    {
        return $grid->isCompleteAndValid();
    }

    private function createState(Grid $grid): HumanSolverState
    {
        $candidateMasks = array_fill(0, 81, 0);

        for ($idx = 0; $idx < 81; $idx++) {
            if ($grid->getByIndex($idx) !== 0) {
                $candidateMasks[$idx] = 0;
                continue;
            }

            $candidateMasks[$idx] = $this->computeCellMask($grid, $idx);
        }

        return new HumanSolverState($grid, $candidateMasks);
    }

    private function applyNakedSingles(HumanSolverState $state): int
    {
        $count = 0;

        for ($idx = 0; $idx < 81; $idx++) {
            if ($state->grid->getByIndex($idx) !== 0) {
                continue;
            }

            $mask = $state->candidateMasks[$idx];
            if ($this->bitCount($mask) === 1) {
                $digit = $this->singleDigitFromMask($mask);
                if ($this->placeDigit($state, $idx, $digit)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function applyHiddenSingles(HumanSolverState $state): int
    {
        $toPlace = [];

        foreach ($this->units as $unit) {
            for ($digit = 1; $digit <= 9; $digit++) {
                $digitBit = 1 << ($digit - 1);
                $targetIdx = -1;
                $count = 0;

                foreach ($unit as $idx) {
                    if ($state->grid->getByIndex($idx) !== 0) {
                        continue;
                    }

                    if (($state->candidateMasks[$idx] & $digitBit) !== 0) {
                        $targetIdx = $idx;
                        $count++;
                        if ($count > 1) {
                            break;
                        }
                    }
                }

                if ($count === 1 && $targetIdx >= 0) {
                    $toPlace[$targetIdx] = $digit;
                }
            }
        }

        $placed = 0;
        foreach ($toPlace as $idx => $digit) {
            if ($this->placeDigit($state, $idx, $digit)) {
                $placed++;
            }
        }

        return $placed;
    }

    private function applyNakedPairs(HumanSolverState $state): int
    {
        $eliminations = 0;

        foreach ($this->units as $unit) {
            $pairCellsByMask = [];

            foreach ($unit as $idx) {
                if ($state->grid->getByIndex($idx) !== 0) {
                    continue;
                }

                $mask = $state->candidateMasks[$idx];
                if ($this->bitCount($mask) === 2) {
                    if (!isset($pairCellsByMask[$mask])) {
                        $pairCellsByMask[$mask] = [];
                    }
                    $pairCellsByMask[$mask][] = $idx;
                }
            }

            foreach ($pairCellsByMask as $pairMask => $pairCells) {
                if (count($pairCells) !== 2) {
                    continue;
                }

                foreach ($unit as $idx) {
                    if (in_array($idx, $pairCells, true)) {
                        continue;
                    }

                    if ($state->grid->getByIndex($idx) !== 0) {
                        continue;
                    }

                    $shared = $state->candidateMasks[$idx] & $pairMask;
                    if ($shared === 0) {
                        continue;
                    }

                    $state->candidateMasks[$idx] &= ~$pairMask;
                    $removedCount = $this->bitCount($shared);
                    if ($removedCount > 0) {
                        $eliminations += $removedCount;
                    }
                }
            }
        }

        return $eliminations;
    }

    private function applyPointingPairs(HumanSolverState $state): int
    {
        $eliminations = 0;

        for ($br = 0; $br < 3; $br++) {
            for ($bc = 0; $bc < 3; $bc++) {
                $boxCells = [];
                for ($r = $br * 3; $r < $br * 3 + 3; $r++) {
                    for ($c = $bc * 3; $c < $bc * 3 + 3; $c++) {
                        $boxCells[] = $r * 9 + $c;
                    }
                }

                for ($digit = 1; $digit <= 9; $digit++) {
                    $bit = 1 << ($digit - 1);
                    $positions = [];

                    foreach ($boxCells as $idx) {
                        if ($state->grid->getByIndex($idx) !== 0) {
                            continue;
                        }
                        if (($state->candidateMasks[$idx] & $bit) !== 0) {
                            $positions[] = $idx;
                        }
                    }

                    $count = count($positions);
                    if ($count < 2 || $count > 3) {
                        continue;
                    }

                    $rows = [];
                    $cols = [];
                    foreach ($positions as $idx) {
                        $rows[intdiv($idx, 9)] = true;
                        $cols[$idx % 9] = true;
                    }

                    if (count($rows) === 1) {
                        $row = (int)array_key_first($rows);
                        for ($c = 0; $c < 9; $c++) {
                            if ($c >= $bc * 3 && $c < $bc * 3 + 3) {
                                continue;
                            }
                            $idx = $row * 9 + $c;
                            if ($state->grid->getByIndex($idx) !== 0) {
                                continue;
                            }
                            if (($state->candidateMasks[$idx] & $bit) !== 0) {
                                $state->candidateMasks[$idx] &= ~$bit;
                                $eliminations++;
                            }
                        }
                    }

                    if (count($cols) === 1) {
                        $col = (int)array_key_first($cols);
                        for ($r = 0; $r < 9; $r++) {
                            if ($r >= $br * 3 && $r < $br * 3 + 3) {
                                continue;
                            }
                            $idx = $r * 9 + $col;
                            if ($state->grid->getByIndex($idx) !== 0) {
                                continue;
                            }
                            if (($state->candidateMasks[$idx] & $bit) !== 0) {
                                $state->candidateMasks[$idx] &= ~$bit;
                                $eliminations++;
                            }
                        }
                    }
                }
            }
        }

        return $eliminations;
    }

    private function applyXWing(HumanSolverState $state): int
    {
        $eliminations = 0;

        for ($digit = 1; $digit <= 9; $digit++) {
            $bit = 1 << ($digit - 1);
            $eliminations += $this->applyXWingRows($state, $bit);
            $eliminations += $this->applyXWingCols($state, $bit);
        }

        return $eliminations;
    }

    private function applyXWingRows(HumanSolverState $state, int $bit): int
    {
        $eliminations = 0;
        $rowsByPair = [];

        for ($r = 0; $r < 9; $r++) {
            $cols = [];
            for ($c = 0; $c < 9; $c++) {
                $idx = $r * 9 + $c;
                if ($state->grid->getByIndex($idx) !== 0) {
                    continue;
                }
                if (($state->candidateMasks[$idx] & $bit) !== 0) {
                    $cols[] = $c;
                }
            }

            if (count($cols) === 2) {
                sort($cols);
                $key = $cols[0] . ':' . $cols[1];
                if (!isset($rowsByPair[$key])) {
                    $rowsByPair[$key] = [];
                }
                $rowsByPair[$key][] = $r;
            }
        }

        foreach ($rowsByPair as $key => $rows) {
            if (count($rows) < 2) {
                continue;
            }

            [$c1, $c2] = array_map('intval', explode(':', $key));
            $rowCount = count($rows);
            for ($i = 0; $i < $rowCount - 1; $i++) {
                for ($j = $i + 1; $j < $rowCount; $j++) {
                    $rA = $rows[$i];
                    $rB = $rows[$j];

                    for ($r = 0; $r < 9; $r++) {
                        if ($r === $rA || $r === $rB) {
                            continue;
                        }

                        $idx1 = $r * 9 + $c1;
                        if ($state->grid->getByIndex($idx1) === 0 && ($state->candidateMasks[$idx1] & $bit) !== 0) {
                            $state->candidateMasks[$idx1] &= ~$bit;
                            $eliminations++;
                        }

                        $idx2 = $r * 9 + $c2;
                        if ($state->grid->getByIndex($idx2) === 0 && ($state->candidateMasks[$idx2] & $bit) !== 0) {
                            $state->candidateMasks[$idx2] &= ~$bit;
                            $eliminations++;
                        }
                    }
                }
            }
        }

        return $eliminations;
    }

    private function applyXWingCols(HumanSolverState $state, int $bit): int
    {
        $eliminations = 0;
        $colsByPair = [];

        for ($c = 0; $c < 9; $c++) {
            $rows = [];
            for ($r = 0; $r < 9; $r++) {
                $idx = $r * 9 + $c;
                if ($state->grid->getByIndex($idx) !== 0) {
                    continue;
                }
                if (($state->candidateMasks[$idx] & $bit) !== 0) {
                    $rows[] = $r;
                }
            }

            if (count($rows) === 2) {
                sort($rows);
                $key = $rows[0] . ':' . $rows[1];
                if (!isset($colsByPair[$key])) {
                    $colsByPair[$key] = [];
                }
                $colsByPair[$key][] = $c;
            }
        }

        foreach ($colsByPair as $key => $cols) {
            if (count($cols) < 2) {
                continue;
            }

            [$r1, $r2] = array_map('intval', explode(':', $key));
            $colCount = count($cols);
            for ($i = 0; $i < $colCount - 1; $i++) {
                for ($j = $i + 1; $j < $colCount; $j++) {
                    $cA = $cols[$i];
                    $cB = $cols[$j];

                    for ($c = 0; $c < 9; $c++) {
                        if ($c === $cA || $c === $cB) {
                            continue;
                        }

                        $idx1 = $r1 * 9 + $c;
                        if ($state->grid->getByIndex($idx1) === 0 && ($state->candidateMasks[$idx1] & $bit) !== 0) {
                            $state->candidateMasks[$idx1] &= ~$bit;
                            $eliminations++;
                        }

                        $idx2 = $r2 * 9 + $c;
                        if ($state->grid->getByIndex($idx2) === 0 && ($state->candidateMasks[$idx2] & $bit) !== 0) {
                            $state->candidateMasks[$idx2] &= ~$bit;
                            $eliminations++;
                        }
                    }
                }
            }
        }

        return $eliminations;
    }

    private function placeDigit(HumanSolverState $state, int $idx, int $digit): bool
    {
        if ($state->grid->getByIndex($idx) !== 0) {
            return false;
        }

        $r = intdiv($idx, 9);
        $c = $idx % 9;
        if (!$state->grid->isValidPlacement($r, $c, $digit)) {
            return false;
        }

        $state->grid->setByIndex($idx, $digit);
        $state->candidateMasks[$idx] = 0;

        $digitBit = 1 << ($digit - 1);
        foreach ($this->peers[$idx] as $peer) {
            if ($state->grid->getByIndex($peer) !== 0) {
                continue;
            }
            $state->candidateMasks[$peer] &= ~$digitBit;
        }

        return true;
    }

    private function computeCellMask(Grid $grid, int $idx): int
    {
        if ($grid->getByIndex($idx) !== 0) {
            return 0;
        }

        $r = intdiv($idx, 9);
        $c = $idx % 9;

        $used = array_fill(1, 9, false);
        for ($i = 0; $i < 9; $i++) {
            $rv = $grid->get($r, $i);
            $cv = $grid->get($i, $c);
            if ($rv !== 0) {
                $used[$rv] = true;
            }
            if ($cv !== 0) {
                $used[$cv] = true;
            }
        }

        $br = intdiv($r, 3) * 3;
        $bc = intdiv($c, 3) * 3;
        for ($rr = $br; $rr < $br + 3; $rr++) {
            for ($cc = $bc; $cc < $bc + 3; $cc++) {
                $v = $grid->get($rr, $cc);
                if ($v !== 0) {
                    $used[$v] = true;
                }
            }
        }

        $mask = 0;
        for ($d = 1; $d <= 9; $d++) {
            if (!$used[$d]) {
                $mask |= (1 << ($d - 1));
            }
        }

        return $mask;
    }

    private function singleDigitFromMask(int $mask): int
    {
        for ($digit = 1; $digit <= 9; $digit++) {
            if (($mask & (1 << ($digit - 1))) !== 0) {
                return $digit;
            }
        }
        return 0;
    }

    private function bitCount(int $mask): int
    {
        $count = 0;
        while ($mask > 0) {
            $count += $mask & 1;
            $mask >>= 1;
        }
        return $count;
    }

    /** @return int[][] */
    private function buildUnits(): array
    {
        $units = [];

        for ($r = 0; $r < 9; $r++) {
            $unit = [];
            for ($c = 0; $c < 9; $c++) {
                $unit[] = $r * 9 + $c;
            }
            $units[] = $unit;
        }

        for ($c = 0; $c < 9; $c++) {
            $unit = [];
            for ($r = 0; $r < 9; $r++) {
                $unit[] = $r * 9 + $c;
            }
            $units[] = $unit;
        }

        for ($br = 0; $br < 3; $br++) {
            for ($bc = 0; $bc < 3; $bc++) {
                $unit = [];
                for ($r = $br * 3; $r < $br * 3 + 3; $r++) {
                    for ($c = $bc * 3; $c < $bc * 3 + 3; $c++) {
                        $unit[] = $r * 9 + $c;
                    }
                }
                $units[] = $unit;
            }
        }

        return $units;
    }

    /** @return int[][] */
    private function buildPeers(): array
    {
        $peers = [];
        for ($idx = 0; $idx < 81; $idx++) {
            $peerSet = [];
            foreach ($this->units as $unit) {
                if (!in_array($idx, $unit, true)) {
                    continue;
                }
                foreach ($unit as $uIdx) {
                    if ($uIdx !== $idx) {
                        $peerSet[$uIdx] = true;
                    }
                }
            }
            $peers[$idx] = array_keys($peerSet);
        }

        return $peers;
    }
}
