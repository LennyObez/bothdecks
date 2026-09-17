<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Support;

use BothDecks\Taxonomy\Internal\Embedding\EmbeddingProvider;
use BothDecks\Taxonomy\Internal\Ingestion\EscoImporter;
use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use PHPUnit\Framework\Assert;
use Pulsar\Core\Kernel;
use Pulsar\Database\ConnectionInterface;

/**
 * A booted kernel with the fixture snapshot imported as the current version, and the deterministic
 * embedding stand-in bound in place of the model.
 *
 * The import is done once per database state and recognised afterwards by the snapshot's digest: a test
 * that finds the fixture already current gets the tables it mutates emptied (vectors, bubbles, corrections,
 * log) and the concepts left as they are. A test suite that re-imported the fixture for every test would
 * spend its time in the importer, and so would every mutant the mutation runner tries against this code.
 *
 * The import is checked against what the fixture holds before any test runs on it: every test that reads
 * these tables would otherwise be measuring an import nobody verified, and whichever test happens to
 * trigger the import is the one the mutation runner credits with covering the importer.
 */
final readonly class ImportedFixture
{
    /**
     * What one import of the fixture writes, by table.
     */
    public const array ROWS = [
        'taxonomy_concept' => 7 + 74 + 16 + 94,
        'concept_label' => 2692,
        'concept_description' => 633,
        'concept_broader' => 211,
        'occupation' => 7,
        'skill' => 74,
        'occupation_skill' => 70,
    ];

    private function __construct(
        public Kernel $kernel,
        public ConnectionInterface $connection,
    ) {}

    public static function load(): self
    {
        $kernel = Application::bootedKernel();
        Database::migrate($kernel);
        $connection = Database::connection($kernel);
        $kernel->container()->instance(EmbeddingProvider::class, new BagOfWordsEmbeddings());

        $snapshot = Snapshot::open(self::snapshot());
        $versions = $connection->query('SELECT is_current, snapshot_digest FROM taxonomy_version')->map(
            static fn(\Pulsar\Database\Row $r): array => [$r->getBool('is_current'), $r->getString('snapshot_digest')],
        );
        $onlyTheFixture = $versions === [[true, $snapshot->manifest->digest()]];

        if (!$onlyTheFixture) {
            Database::emptyTaxonomy($connection);
            $report = $kernel->container()->get(EscoImporter::class)->import($snapshot, static function (string $line): void {});

            Assert::assertSame(self::ROWS, $report->rows, 'The fixture import did not write what the fixture holds.');
            Assert::assertSame(0, $report->labelsSkipped);
            Assert::assertSame(
                self::ROWS['taxonomy_concept'],
                $connection->query('SELECT COUNT(*) AS n FROM taxonomy_concept WHERE status = \'released\'')->firstOrFail()->getInt('n'),
            );
        } else {
            $connection->execute(
                'TRUNCATE taxonomy_resolution_log, occupation_cluster_override, occupation_cluster_member, occupation_cluster,'
                . ' occupation_transition, concept_embedding, product_skill RESTART IDENTITY CASCADE',
            );
        }

        return new self($kernel, $connection);
    }

    public static function snapshot(): string
    {
        return \dirname(__DIR__, 2) . '/Fixtures/taxonomy/snapshot';
    }

    /**
     * The identifier of the concept with this code in the fixture's version.
     */
    public function conceptId(string $code): int
    {
        return $this->connection
            ->query('SELECT id FROM taxonomy_concept WHERE code = :code', ['code' => $code])
            ->firstOrFail()
            ->getInt('id');
    }
}
