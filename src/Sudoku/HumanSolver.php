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

final class HumanSolver
{
    /**
     * Solve with limited human-like techniques and compute a score.
     */
    public function solveWithScore(Grid $grid, DifficultyProfile $profile): HumanSolverResult
    {
        $g = $grid->copy();
        $score = 0;
        $used = [];

        while (true) {
            $progress = false;

            if (in_array('naked_single', $profile->allowedTechniques, true)) {
                $applied = $this->applyNakedSingles($g);
                if ($applied > 0) {
                    $progress = true;
                    $score += $applied * 10;
                    $used['naked_single'] = true;
                }
            }

            if (!$progress && in_array('hidden_single', $profile->allowedTechniques, true)) {
                $applied = $this->applyHiddenSingles($g);
                if ($applied > 0) {
                    $progress = true;
                    $score += $applied * 20;
                    $used['hidden_single'] = true;
                }
            }

            // Placeholder simple (optionnel à enrichir)
            if (!$progress && in_array('naked_pair', $profile->allowedTechniques, true)) {
                $applied = $this->applyNakedPairsLight($g);
                if ($applied > 0) {
                    $progress = true;
                    $score += $applied * 50;
                    $used['naked_pair'] = true;
                }
            }

            if (!$progress) break;
        }

        return new HumanSolverResult(
            solved: $this->isSolved($g),
            score: $score,
            usedTechniques: array_keys($used)
        );
    }

    private function isSolved(Grid $grid): bool
    {
        return $grid->isCompleteAndValid();
    }

    private function applyNakedSingles(Grid $grid): int
    {
        $count = 0;
        for ($idx = 0; $idx < 81; $idx++) {
            if ($grid->getByIndex($idx) !== 0) continue;
            $r = intdiv($idx, 9);
            $c = $idx % 9;
            $cands = $this->candidates($grid, $r, $c);
            if (count($cands) === 1) {
                $grid->set($r, $c, $cands[0]);
                $count++;
            }
        }
        return $count;
    }

    private function applyHiddenSingles(Grid $grid): int
    {
        $count = 0;

        // rows
        for ($r = 0; $r < 9; $r++) {
            $count += $this->placeHiddenSingleInUnit($grid, $this->rowCells($r));
        }
        // cols
        for ($c = 0; $c < 9; $c++) {
            $count += $this->placeHiddenSingleInUnit($grid, $this->colCells($c));
        }
        // boxes
        for ($br = 0; $br < 3; $br++) {
            for ($bc = 0; $bc < 3; $bc++) {
                $count += $this->placeHiddenSingleInUnit($grid, $this->boxCells($br, $bc));
            }
        }

        return $count;
    }

    private function applyNakedPairsLight(Grid $grid): int
    {
        // Implémentation volontairement légère: on ne fait que compter quelques éliminations conceptuelles.
        // Pour une vraie difficulté "medium+", il faudra propager les candidats par unité.
        return 0;
    }

    /** @param array<array{int,int}> $cells */
    private function placeHiddenSingleInUnit(Grid $grid, array $cells): int
    {
        $positionsByDigit = [];
        for ($d = 1; $d <= 9; $d++) $positionsByDigit[$d] = [];

        foreach ($cells as [$r, $c]) {
            if ($grid->get($r, $c) !== 0) continue;
            foreach ($this->candidates($grid, $r, $c) as $cand) {
                $positionsByDigit[$cand][] = [$r, $c];
            }
        }

        $placed = 0;
        for ($d = 1; $d <= 9; $d++) {
            if (count($positionsByDigit[$d]) === 1) {
                [$r, $c] = $positionsByDigit[$d][0];
                if ($grid->get($r, $c) === 0) {
                    $grid->set($r, $c, $d);
                    $placed++;
                }
            }
        }
        return $placed;
    }

    private function rowCells(int $r): array
    {
        $out = [];
        for ($c = 0; $c < 9; $c++) $out[] = [$r, $c];
        return $out;
    }

    private function colCells(int $c): array
    {
        $out = [];
        for ($r = 0; $r < 9; $r++) $out[] = [$r, $c];
        return $out;
    }

    private function boxCells(int $br, int $bc): array
    {
        $out = [];
        for ($r = $br * 3; $r < $br * 3 + 3; $r++) {
            for ($c = $bc * 3; $c < $bc * 3 + 3; $c++) {
                $out[] = [$r, $c];
            }
        }
        return $out;
    }

    /** @return int[] */
    private function candidates(Grid $grid, int $r, int $c): array
    {
        $used = array_fill(1, 9, false);

        for ($i = 0; $i < 9; $i++) {
            $rv = $grid->get($r, $i);
            $cv = $grid->get($i, $c);
            if ($rv !== 0) $used[$rv] = true;
            if ($cv !== 0) $used[$cv] = true;
        }

        $br = intdiv($r, 3) * 3;
        $bc = intdiv($c, 3) * 3;
        for ($rr = $br; $rr < $br + 3; $rr++) {
            for ($cc = $bc; $cc < $bc + 3; $cc++) {
                $v = $grid->get($rr, $cc);
                if ($v !== 0) $used[$v] = true;
            }
        }

        $out = [];
        for ($v = 1; $v <= 9; $v++) {
            if (!$used[$v]) $out[] = $v;
        }
        return $out;
    }
}
