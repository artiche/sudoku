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

    public function generate(DifficultyProfile $profile, ?int $seed = null, bool $symmetry180 = true): Grid
    {
        $solution = $this->fullGridGenerator->generate($seed);
        $puzzle = $solution->copy();

        $positions = range(0, 80);
        $this->shuffle($positions);

        foreach ($positions as $idx) {
            if ($puzzle->getByIndex($idx) === 0) continue;

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

            // contrainte min de givens
            if ($puzzle->givensCount() < $profile->minGivens) {
                foreach ($backup as $i => $v) $puzzle->setByIndex((int)$i, $v);
                continue;
            }

            // unicité
            $nSolutions = $this->solutionCounter->countSolutions($puzzle, 2);
            if ($nSolutions !== 1) {
                foreach ($backup as $i => $v) $puzzle->setByIndex((int)$i, $v);
                continue;
            }

            // difficulté
            $eval = $this->humanSolver->solveWithScore($puzzle, $profile);
            $okDifficulty = $eval->solved
                && $eval->score >= $profile->minScore
                && $eval->score <= $profile->maxScore;

            if (!$okDifficulty) {
                foreach ($backup as $i => $v) $puzzle->setByIndex((int)$i, $v);
                continue;
            }

            // sinon suppression validée
        }

        // Vérification finale
        $finalSolutions = $this->solutionCounter->countSolutions($puzzle, 2);
        if ($finalSolutions !== 1) {
            // fallback de sécurité
            return $this->generate($profile, $seed === null ? null : $seed + 1, $symmetry180);
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
