<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

/**
 * One version as the report describes it.
 */
final readonly class VersionSummary
{
    /**
     * @param array<string, int> $concepts Count per concept kind.
     */
    public function __construct(
        public int $id,
        public string $source,
        public string $sourceVersion,
        public string $digest,
        public string $snapshotTakenAt,
        public string $importedAt,
        public string $licence,
        public string $attribution,
        public bool $current,
        public array $concepts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'source_version' => $this->sourceVersion,
            'digest' => $this->digest,
            'snapshot_taken_at' => $this->snapshotTakenAt,
            'imported_at' => $this->importedAt,
            'licence' => $this->licence,
            'attribution' => $this->attribution,
            'current' => $this->current,
            'concepts' => $this->concepts,
        ];
    }
}
