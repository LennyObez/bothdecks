<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

use BothDecks\Taxonomy\Domain\Resolution;
use BothDecks\Taxonomy\Domain\ResolutionCandidate;

/**
 * Counts of how a set of queries came out against what each should have resolved to.
 */
final class EvaluationTally
{
    private int $right = 0;

    private int $wrong = 0;

    private int $askedWithRight = 0;

    private int $askedWithoutRight = 0;

    /** @var array<string, int> */
    private array $byPath = [];

    public function record(Resolution $resolution, int $expectedConceptId): void
    {
        $this->byPath[$resolution->path->value] = ($this->byPath[$resolution->path->value] ?? 0) + 1;
        $answer = $resolution->resolved();

        if ($answer !== null) {
            if ($answer->conceptId === $expectedConceptId) {
                $this->right++;
            } else {
                $this->wrong++;
            }

            return;
        }

        $offered = array_map(static fn(ResolutionCandidate $c): int => $c->conceptId, $resolution->candidates);

        if (\in_array($expectedConceptId, $offered, true)) {
            $this->askedWithRight++;
        } else {
            $this->askedWithoutRight++;
        }
    }

    public function total(): int
    {
        return $this->right + $this->wrong + $this->askedWithRight + $this->askedWithoutRight;
    }

    public function right(): int
    {
        return $this->right;
    }

    public function wrong(): int
    {
        return $this->wrong;
    }

    public function askedWithRight(): int
    {
        return $this->askedWithRight;
    }

    public function askedWithoutRight(): int
    {
        return $this->askedWithoutRight;
    }

    /**
     * Of the queries that were resolved, the share resolved to the right concept.
     */
    public function precision(): ?float
    {
        $resolved = $this->right + $this->wrong;

        return $resolved === 0 ? null : $this->right / $resolved;
    }

    /**
     * Of every query, the share resolved to the right concept without asking.
     */
    public function recall(): ?float
    {
        return $this->total() === 0 ? null : $this->right / $this->total();
    }

    /**
     * Of every query, the share that asked the user.
     */
    public function askShare(): ?float
    {
        return $this->total() === 0 ? null : ($this->askedWithRight + $this->askedWithoutRight) / $this->total();
    }

    /**
     * Of every query, the share resolved to a wrong concept: the figure to keep near zero.
     */
    public function wrongShare(): ?float
    {
        return $this->total() === 0 ? null : $this->wrong / $this->total();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total(),
            'right' => $this->right,
            'wrong' => $this->wrong,
            'asked_with_right' => $this->askedWithRight,
            'asked_without_right' => $this->askedWithoutRight,
            'precision' => self::rounded($this->precision()),
            'recall' => self::rounded($this->recall()),
            'ask_share' => self::rounded($this->askShare()),
            'wrong_share' => self::rounded($this->wrongShare()),
            'by_path' => $this->byPath,
        ];
    }

    private static function rounded(?float $value): ?float
    {
        return $value === null ? null : round($value, 4);
    }
}
