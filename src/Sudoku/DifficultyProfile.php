<?php

declare(strict_types=1);

namespace Sudoku;

final class DifficultyProfile
{
    public function __construct(
        public readonly string $name,
        public readonly int $minScore,
        public readonly int $maxScore,
        public readonly int $minGivens,
        public readonly int $maxGivens,
        /** @var string[] */
        public readonly array $allowedTechniques
    ) {}

    public static function easy(): self
    {
        return new self(
            name: 'easy',
            minScore: 50,
            maxScore: 220,
            minGivens: 34,
            maxGivens: 45,
            allowedTechniques: ['naked_single', 'hidden_single']
        );
    }

    public static function medium(): self
    {
        return new self(
            name: 'medium',
            minScore: 180,
            maxScore: 600,
            minGivens: 28,
            maxGivens: 36,
            allowedTechniques: ['naked_single', 'hidden_single', 'naked_pair', 'pointing_pair']
        );
    }

    public static function hard(): self
    {
        return new self(
            name: 'hard',
            minScore: 450,
            maxScore: 1400,
            minGivens: 22,
            maxGivens: 32,
            allowedTechniques: ['naked_single', 'hidden_single', 'naked_pair', 'pointing_pair', 'x_wing']
        );
    }
}
