<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Ingestion;

/**
 * What an import wrote, by table, for the log and the version report.
 */
final readonly class ImportReport
{
    /**
     * @param array<string, int> $rows Rows written, keyed by table.
     * @param int $labelsSkipped Alternative or hidden labels made of no letters or digits, left out.
     */
    public function __construct(
        public int $versionId,
        public string $sourceVersion,
        public string $digest,
        public array $rows,
        public int $labelsSkipped,
    ) {}
}
