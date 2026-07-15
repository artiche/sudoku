<?php

declare(strict_types=1);

namespace Sudoku;

final class PuzzleGenerator
{
    public function __construct(
        private readonly FullGridGenerator $fullGridGenerator = new FullGridGenerator(),
        private readonly SolutionCounter $solutionCounter = new SolutionCounter(),
        private readonly HumanSolver $humanSolver = new HumanSolver()
    ) {}

    private const MAX_ATTEMPTS = 100;

    public function generate(DifficultyProfile $profile, ?int $seed = null, bool $symmetry180 = true): Grid
    {
        $result = $this->generateWithSolution($profile, $seed, $symmetry180);
        return $result['puzzle'];
    }

    /**
     * @return array{puzzle: Grid, solution: Grid, seed: int|null}
     */
    public function generateWithSolution(DifficultyProfile $profile, ?int $seed = null, bool $symmetry180 = true): array
    {
        $currentSeed = $seed;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $solution = $this->fullGridGenerator->generate($currentSeed);
            $puzzle = $this->tryGenerate($profile, $solution, $symmetry180, $currentSeed);
            if ($puzzle !== null) {
                return [
                    'puzzle' => $puzzle,
                    'solution' => $solution,
                    'seed' => $currentSeed,
                ];
            }
            $currentSeed = ($currentSeed === null) ? mt_rand() : $currentSeed + 1;
        }

        throw new \RuntimeException(
            sprintf(
                'Failed to generate a valid %s puzzle after %d attempts.',
                $profile->name,
                self::MAX_ATTEMPTS
            )
        );
    }

    private function tryGenerate(DifficultyProfile $profile, Grid $solution, bool $symmetry180, ?int $seed): ?Grid
    {
        $puzzle = $solution->copy();

        $positions = range(0, 80);
        $this->shuffle($positions, $seed);

        foreach ($positions as $idx) {
            if ($puzzle->getByIndex($idx) === 0) continue;

            // Stop digging once we've reached the target givens range
            if ($puzzle->givensCount() <= $profile->maxGivens) {
                break;
            }

            $toRemove = [$idx];
            if ($symmetry180) {
                $sym = 80 - $idx;
                if ($sym !== $idx && $puzzle->getByIndex($sym) !== 0) {
                    $toRemove[] = $sym;
                }
            }

            $backup = [];
            foreach ($toRemove as $i) {
                $backup[$i] = $puzzle->getByIndex($i);
                $puzzle->setByIndex($i, 0);
            }

            // Never go below minGivens
            if ($puzzle->givensCount() < $profile->minGivens) {
                foreach ($backup as $i => $v) $puzzle->setByIndex($i, $v);
                continue;
            }

            // Uniqueness check: removal is only valid if puzzle still has exactly one solution
            $nSolutions = $this->solutionCounter->countSolutions($puzzle, 2);
            if ($nSolutions !== 1) {
                foreach ($backup as $i => $v) $puzzle->setByIndex($i, $v);
                continue;
            }

            // Keep givens visually balanced across units.
            if (!$this->isBalancedDistribution($puzzle)) {
                foreach ($backup as $i => $v) $puzzle->setByIndex($i, $v);
                continue;
            }

            // Removal accepted
        }

        // Final acceptance: givens must be within [minGivens, maxGivens] and solution unique
        $finalCount = $puzzle->givensCount();
        if ($finalCount < $profile->minGivens || $finalCount > $profile->maxGivens) {
            return null;
        }

        if ($this->solutionCounter->countSolutions($puzzle, 2) !== 1) {
            return null;
        }

        if (!$this->isBalancedDistribution($puzzle)) {
            return null;
        }

        return $puzzle;
    }

    private function isBalancedDistribution(Grid $grid): bool
    {
        $rowCounts = array_fill(0, 9, 0);
        $colCounts = array_fill(0, 9, 0);
        $boxCounts = array_fill(0, 9, 0);

        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                if ($grid->get($r, $c) === 0) {
                    continue;
                }
                $rowCounts[$r]++;
                $colCounts[$c]++;
                $boxIdx = intdiv($r, 3) * 3 + intdiv($c, 3);
                $boxCounts[$boxIdx]++;
            }
        }

        $givens = $grid->givensCount();
        $expectedPerUnit = $givens / 9.0;

        $minLineGivens = 1;
        $maxLineGivens = min(9, (int)ceil($expectedPerUnit) + 3);

        $minBoxGivens = 1;
        $maxBoxGivens = min(9, (int)ceil($expectedPerUnit) + 2);

        if (!$this->isWithinBounds($rowCounts, $minLineGivens, $maxLineGivens)) {
            return false;
        }

        if (!$this->isWithinBounds($colCounts, $minLineGivens, $maxLineGivens)) {
            return false;
        }

        if (!$this->isWithinBounds($boxCounts, $minBoxGivens, $maxBoxGivens)) {
            return false;
        }

        if ((max($rowCounts) - min($rowCounts)) > 4) {
            return false;
        }

        if ((max($colCounts) - min($colCounts)) > 4) {
            return false;
        }

        if ((max($boxCounts) - min($boxCounts)) > 3) {
            return false;
        }

        return true;
    }

    /** @param int[] $counts */
    private function isWithinBounds(array $counts, int $min, int $max): bool
    {
        foreach ($counts as $count) {
            if ($count < $min || $count > $max) {
                return false;
            }
        }
        return true;
    }

    private function shuffle(array &$arr, ?int $seed = null): void
    {
        if ($seed !== null) {
            $state = ($seed ^ 0x9E3779B9) & 0x7fffffff;
            if ($state === 0) {
                $state = 1;
            }

            for ($i = count($arr) - 1; $i > 0; $i--) {
                $state = (int)((1103515245 * $state + 12345) & 0x7fffffff);
                $j = $state % ($i + 1);
                [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
            }
            return;
        }

        for ($i = count($arr) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
    }
}
