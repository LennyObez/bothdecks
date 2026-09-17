<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Taxonomy;

use BothDecks\Taxonomy\Internal\Ingestion\EscoImporter;
use BothDecks\Taxonomy\Internal\Ingestion\ImportException;
use BothDecks\Taxonomy\Internal\Ingestion\ImportReport;
use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use BothDecks\Tests\Integration\Support\Application;
use BothDecks\Tests\Integration\Support\Database;
use BothDecks\Tests\Integration\Support\FixtureCopy;
use BothDecks\Tests\Integration\Support\ImportedFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pulsar\Core\Kernel;
use Pulsar\Database\Row;

/**
 * A snapshot becomes a version: every concept, label, description, relation and hierarchy edge it holds,
 * with its provenance, and nothing when any of it cannot be written.
 *
 * The fixture is a closed slice of a real snapshot: seven occupations, one of them under another rather
 * than under a group, the skills essential to them, and the groups above both, in eight languages. Closed
 * means every link points inside the slice, which is also what the importer requires of a full snapshot.
 */
#[CoversClass(EscoImporter::class)]
final class ImportTest extends TestCase
{
    public function testTheFixtureImportsAsOneCurrentVersionWithEveryTableFilled(): void
    {
        // Arrange
        $kernel = self::emptyDatabase();

        // Act
        $report = self::import($kernel);

        // Assert
        self::assertSame(1, $report->versionId);
        self::assertSame('v1.2.1', $report->sourceVersion);
        self::assertSame(ImportedFixture::ROWS, $report->rows);
        self::assertSame(0, $report->labelsSkipped);

        $current = Database::connection($kernel)->query('SELECT id, licence, attribution FROM taxonomy_version WHERE is_current')->firstOrFail();
        self::assertSame(1, $current->getInt('id'));
        self::assertSame('CC-BY-4.0', $current->getString('licence'));
        self::assertStringContainsString('ESCO', $current->getString('attribution'));
    }

    public function testEveryConceptCarriesItsProvenanceAndLicence(): void
    {
        // Arrange
        $kernel = self::emptyDatabase();
        self::import($kernel);

        // Act
        $without = Database::connection($kernel)->query(
            "SELECT COUNT(*) AS n FROM taxonomy_concept WHERE source_uri NOT LIKE 'https://%' OR licence = '' OR source <> 'esco'",
        )->firstOrFail()->getInt('n');

        // Assert
        self::assertSame(0, $without);
    }

    public function testLabelsAreStoredInTheProductLocalesOnlyAndNormalised(): void
    {
        // The fixture carries eight languages, six of which the product serves; the other two are there to
        // prove the filter is the configuration, not the fixture.

        // Arrange
        $kernel = self::emptyDatabase();
        self::import($kernel);
        $connection = Database::connection($kernel);

        // Act
        $locales = $connection->query('SELECT DISTINCT locale FROM concept_label ORDER BY locale')->pluck('locale');
        $architect = $connection->query(
            "SELECT l.label, l.normalised FROM concept_label l JOIN taxonomy_concept c ON c.id = l.concept_id WHERE c.code = '2512.3' AND l.locale = 'de' AND l.kind = 'preferred'",
        )->firstOrFail();

        // Assert
        self::assertSame(['bg', 'de', 'el', 'en', 'fr', 'nl'], $locales);
        self::assertNotContains('is', $locales);
        self::assertNotContains('no', $locales);
        self::assertSame('Softwarearchitekt/Softwarearchitektin', $architect->getString('label'));
        self::assertSame('softwarearchitekt softwarearchitektin', $architect->getString('normalised'));
    }

    public function testANarrowerOccupationInheritsTheGroupOfItsParent(): void
    {
        // 2512.4.1 carries no group of its own in the source, only a parent occupation, 2512.4; the import
        // walks up to the parent's group.

        // Arrange
        $kernel = self::emptyDatabase();
        self::import($kernel);

        // Act
        $groups = Database::connection($kernel)->query(
            <<<'SQL'
                SELECT c.code, g.code AS isco
                FROM occupation o
                JOIN taxonomy_concept c ON c.id = o.concept_id
                JOIN taxonomy_concept g ON g.id = o.isco_group_id
                ORDER BY c.code
                SQL,
        )->map(static fn(Row $r): string => $r->getString('code') . ' in ' . $r->getString('isco'));

        // Assert
        self::assertContains('2512.4.1 in 2512', $groups);
        self::assertContains('2512.3 in 2512', $groups);
        self::assertContains('7212.3 in 7212', $groups);
        self::assertCount(7, $groups);
    }

    public function testANarrowerOccupationWhoseParentIsMissingStopsTheImport(): void
    {
        // Arrange: the fixture without 2512.4, the parent of 2512.4.1.
        $kernel = self::emptyDatabase();
        $copy = FixtureCopy::withoutOccupation('2512.4');
        $importer = $kernel->container()->get(EscoImporter::class);

        // Act
        $failure = self::capture(static fn() => $importer->import(Snapshot::open($copy->directory), static function (string $line): void {}));

        // Assert
        self::assertInstanceOf(ImportException::class, $failure);
        self::assertStringContainsString('broaderOccupation', $failure->getMessage());
        self::assertStringContainsString('which the snapshot does not hold', $failure->getMessage());
        self::assertSame(0, Database::connection($kernel)->query('SELECT COUNT(*) AS n FROM taxonomy_version')->firstOrFail()->getInt('n'));

        $copy->remove();
    }

    public function testAnOccupationWithNeitherGroupNorParentStopsTheImport(): void
    {
        // Arrange: the architect with its group link removed and no parent occupation to inherit one from;
        // the record is refused on its own links, before any walk up the parents.
        $kernel = self::emptyDatabase();
        $copy = FixtureCopy::rewriting(static function (string $file, array $record): array {
            if (($record['code'] ?? null) === '2512.3' && \is_array($record['_links'] ?? null)) {
                unset($record['_links']['broaderIscoGroup']);
            }

            return $record;
        });
        $importer = $kernel->container()->get(EscoImporter::class);

        // Act
        $failure = self::capture(static fn() => $importer->import(Snapshot::open($copy->directory), static function (string $line): void {}));

        // Assert
        self::assertInstanceOf(ImportException::class, $failure);
        self::assertSame(
            'http://data.europa.eu/esco/occupation/d0aa0792-4345-474b-9365-686cf4869d2e links to 0 occupation groups and 0 parent occupations;'
            . ' at most one of each and at least one of the two are expected.',
            $failure->getMessage(),
        );
        self::assertSame(0, Database::connection($kernel)->query('SELECT COUNT(*) AS n FROM taxonomy_version')->firstOrFail()->getInt('n'));

        $copy->remove();
    }

    public function testASkillWithTwoTypesOrTwoReuseLevelsStopsTheImport(): void
    {
        // Arrange: the first skill given a second type; the tables take one, and the source publishes one.
        $kernel = self::emptyDatabase();
        $rewritten = false;
        $copy = FixtureCopy::rewriting(static function (string $file, array $record) use (&$rewritten): array {
            if ($file === Snapshot::SKILLS && !$rewritten && \is_array($record['_links'] ?? null)) {
                $record['_links']['hasSkillType'] = [
                    ['uri' => 'http://data.europa.eu/esco/skill-type/knowledge'],
                    ['uri' => 'http://data.europa.eu/esco/skill-type/skill'],
                ];
                $rewritten = true;
            }

            return $record;
        });
        $importer = $kernel->container()->get(EscoImporter::class);

        // Act
        $failure = self::capture(static fn() => $importer->import(Snapshot::open($copy->directory), static function (string $line): void {}));

        // Assert
        self::assertInstanceOf(ImportException::class, $failure);
        self::assertStringEndsWith(' has 2 skill types and 1 reuse levels; at most one of each is expected.', $failure->getMessage());
        self::assertSame(0, Database::connection($kernel)->query('SELECT COUNT(*) AS n FROM taxonomy_version')->firstOrFail()->getInt('n'));

        $copy->remove();
    }

    public function testASnapshotAlreadyImportedIsRefusedByItsDigest(): void
    {
        // Arrange
        $kernel = self::emptyDatabase();
        self::import($kernel);

        // Act
        $failure = self::capture(static fn() => self::import($kernel));

        // Assert
        self::assertInstanceOf(ImportException::class, $failure);
        self::assertStringContainsString('already imported as version 1', $failure->getMessage());
        self::assertSame(1, Database::connection($kernel)->query('SELECT COUNT(*) AS n FROM taxonomy_version')->firstOrFail()->getInt('n'));
    }

    public function testARelationToAConceptOutsideTheSnapshotLeavesNothingBehind(): void
    {
        // A snapshot with a hole must not become a version with a hole, nor half a version.

        // Arrange: every skill dropped, so the occupations' essential skills point at nothing.
        $kernel = self::emptyDatabase();
        $copy = FixtureCopy::rewriting(static fn(string $file, array $record): ?array => $file === Snapshot::SKILLS ? null : $record);
        $importer = $kernel->container()->get(EscoImporter::class);

        // Act
        $failure = self::capture(static fn() => $importer->import(Snapshot::open($copy->directory), static function (string $line): void {}));

        // Assert
        self::assertInstanceOf(ImportException::class, $failure);
        self::assertStringContainsString('which the snapshot does not hold', $failure->getMessage());
        $connection = Database::connection($kernel);
        self::assertSame(0, $connection->query('SELECT COUNT(*) AS n FROM taxonomy_version')->firstOrFail()->getInt('n'));
        self::assertSame(0, $connection->query('SELECT COUNT(*) AS n FROM taxonomy_concept')->firstOrFail()->getInt('n'));

        $copy->remove();
    }

    private static function emptyDatabase(): Kernel
    {
        $kernel = Application::bootedKernel();
        Database::migrate($kernel);
        Database::emptyTaxonomy(Database::connection($kernel));

        return $kernel;
    }

    private static function import(Kernel $kernel): ImportReport
    {
        return $kernel->container()->get(EscoImporter::class)->import(Snapshot::open(self::fixture()), static function (string $line): void {});
    }

    public static function fixture(): string
    {
        return \dirname(__DIR__, 2) . '/Fixtures/taxonomy/snapshot';
    }

    /**
     * @param callable(): mixed $build
     */
    private static function capture(callable $build): ?\Throwable
    {
        try {
            $build();
        } catch (\Throwable $caught) {
            return $caught;
        }

        return null;
    }
}
