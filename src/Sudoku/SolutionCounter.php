<?php

declare(strict_types=1);

namespace Sudoku;

final class SolutionCounter
{
    public function countSolutions(Grid $grid, int $limit = 2): int
    {
        $copy = $grid->copy();
        $count = 0;
        $this->search($copy, $count, $limit);
        return $count;
    }

    private function search(Grid $grid, int &$count, int $limit): void
    {
        if ($count >= $limit) return;

        $bestIdx = -1;
        $bestCandidates = null;

        for ($idx = 0; $idx < 81; $idx++) {
            if ($grid->getByIndex($idx) !== 0) continue;
            $r = intdiv($idx, 9);
            $c = $idx % 9;
            $cands = $this->candidates($grid, $r, $c);
            $n = count($cands);

            if ($n === 0) return; // dead end
            if ($bestIdx === -1 || $n < count($bestCandidates)) {
                $bestIdx = $idx;
                $bestCandidates = $cands;
                if ($n === 1) break;
            }
        }

        if ($bestIdx === -1) {
            $count++;
            return;
        }

        $r = intdiv($bestIdx, 9);
        $c = $bestIdx % 9;
        foreach ($bestCandidates as $v) {
            $grid->set($r, $c, $v);
            $this->search($grid, $count, $limit);
            $grid->set($r, $c, 0);
            if ($count >= $limit) return;
        }
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
