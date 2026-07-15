<?php

declare(strict_types=1);

namespace Sudoku;

final class Grid
{
    /** @var int[] values 0..9, 0 = empty */
    private array $cells;

    public function __construct(?array $cells = null)
    {
        if ($cells === null) {
            $cells = array_fill(0, 81, 0);
        }
        if (count($cells) !== 81) {
            throw new \InvalidArgumentException('Grid must have 81 cells.');
        }
        foreach ($cells as $v) {
            if (!is_int($v) || $v < 0 || $v > 9) {
                throw new \InvalidArgumentException('Cell values must be integers between 0 and 9.');
            }
        }
        $this->cells = array_values($cells);
    }

    public function copy(): self
    {
        return new self($this->cells);
    }

    public function get(int $r, int $c): int
    {
        return $this->cells[$r * 9 + $c];
    }

    public function set(int $r, int $c, int $v): void
    {
        $this->cells[$r * 9 + $c] = $v;
    }

    public function getByIndex(int $idx): int
    {
        return $this->cells[$idx];
    }

    public function setByIndex(int $idx, int $v): void
    {
        $this->cells[$idx] = $v;
    }

    public function isEmpty(int $r, int $c): bool
    {
        return $this->get($r, $c) === 0;
    }

    public function givensCount(): int
    {
        $n = 0;
        foreach ($this->cells as $v) {
            if ($v !== 0) $n++;
        }
        return $n;
    }

    public function toArray(): array
    {
        return $this->cells;
    }

    public function isValidPlacement(int $r, int $c, int $v): bool
    {
        if ($v < 1 || $v > 9) return false;

        for ($i = 0; $i < 9; $i++) {
            if ($this->get($r, $i) === $v && $i !== $c) return false;
            if ($this->get($i, $c) === $v && $i !== $r) return false;
        }

        $br = intdiv($r, 3) * 3;
        $bc = intdiv($c, 3) * 3;
        for ($rr = $br; $rr < $br + 3; $rr++) {
            for ($cc = $bc; $cc < $bc + 3; $cc++) {
                if ($rr === $r && $cc === $c) continue;
                if ($this->get($rr, $cc) === $v) return false;
            }
        }
        return true;
    }

    public function isCompleteAndValid(): bool
    {
        // Check rows/cols/boxes contain 1..9 exactly once
        for ($i = 0; $i < 9; $i++) {
            $rowSeen = array_fill(1, 9, false);
            $colSeen = array_fill(1, 9, false);
            for ($j = 0; $j < 9; $j++) {
                $rv = $this->get($i, $j);
                $cv = $this->get($j, $i);
                if ($rv < 1 || $rv > 9 || $rowSeen[$rv]) return false;
                if ($cv < 1 || $cv > 9 || $colSeen[$cv]) return false;
                $rowSeen[$rv] = true;
                $colSeen[$cv] = true;
            }
        }

        for ($br = 0; $br < 3; $br++) {
            for ($bc = 0; $bc < 3; $bc++) {
                $seen = array_fill(1, 9, false);
                for ($r = $br * 3; $r < $br * 3 + 3; $r++) {
                    for ($c = $bc * 3; $c < $bc * 3 + 3; $c++) {
                        $v = $this->get($r, $c);
                        if ($v < 1 || $v > 9 || $seen[$v]) return false;
                        $seen[$v] = true;
                    }
                }
            }
        }

        return true;
    }

    public function __toString(): string
    {
        $lines = [];
        for ($r = 0; $r < 9; $r++) {
            $parts = [];
            for ($c = 0; $c < 9; $c++) {
                $v = $this->get($r, $c);
                $parts[] = $v === 0 ? '.' : (string)$v;
            }
            $lines[] = implode(' ', $parts);
        }
        return implode(PHP_EOL, $lines);
    }
}
