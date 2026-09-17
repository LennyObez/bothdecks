<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Bubbles;

/**
 * What a bubble computation produced, for the log and the version report.
 */
final readonly class BubbleReport
{
    /**
     * @param array<string, float> $weights The weights actually used, after signals without data took zero.
     */
    public function __construct(
        public int $occupations,
        public int $members,
        public int $added,
        public int $excluded,
        public array $weights,
    ) {}
}
