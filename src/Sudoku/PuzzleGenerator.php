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
        $currentSeed = $seed;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $puzzle = $this->tryGenerate($profile, $currentSeed, $symmetry180);
            if ($puzzle !== null) {
                return $puzzle;
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

    private function tryGenerate(DifficultyProfile $profile, ?int $seed, bool $symmetry180): ?Grid
    {
        $solution = $this->fullGridGenerator->generate($seed);
        $puzzle = $solution->copy();

        $positions = range(0, 80);
        $this->shuffle($positions);

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

        return $puzzle;
    }

    private function shuffle(array &$arr): void
    {
        for ($i = count($arr) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
    }
}
