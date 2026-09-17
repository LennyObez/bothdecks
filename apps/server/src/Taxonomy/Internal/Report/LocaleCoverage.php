<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

/**
 * What one language covers of one concept kind.
 */
final readonly class LocaleCoverage
{
    public function __construct(
        public int $total,
        public int $labelled,
        public int $described,
        public int $alternativeLabels,
    ) {}

    public function labelledShare(): float
    {
        return $this->total === 0 ? 0.0 : $this->labelled / $this->total;
    }

    public function describedShare(): float
    {
        return $this->total === 0 ? 0.0 : $this->described / $this->total;
    }

    /**
     * @return array{total: int, labelled: int, described: int, alternative_labels: int}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'labelled' => $this->labelled,
            'described' => $this->described,
            'alternative_labels' => $this->alternativeLabels,
        ];
    }
}
