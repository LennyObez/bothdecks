<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

/**
 * The version report, typed, with one serialisation for the JSON form.
 */
final readonly class Report
{
    public function __construct(
        public VersionSummary $version,
        public ?VersionSummary $previous,
        public ?Migration $migration,
        public Measures $measures,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version->toArray(),
            'previous' => $this->previous?->toArray(),
            'migration' => $this->migration?->toArray(),
            'measures' => $this->measures->toArray(),
        ];
    }
}
