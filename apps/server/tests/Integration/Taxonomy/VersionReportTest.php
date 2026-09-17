<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Taxonomy;

use BothDecks\Taxonomy\Internal\Bubbles\BubbleComputer;
use BothDecks\Taxonomy\Internal\Ingestion\EscoImporter;
use BothDecks\Taxonomy\Internal\Report\ConceptRef;
use BothDecks\Taxonomy\Internal\Report\ResolutionEvaluation;
use BothDecks\Taxonomy\Internal\Report\VersionReport;
use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use BothDecks\Tests\Integration\Support\FixtureCopy;
use BothDecks\Tests\Integration\Support\ImportedFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The version report on one version, and the migration section once a second version has been imported.
 */
#[CoversClass(VersionReport::class)]
#[CoversClass(ResolutionEvaluation::class)]
final class VersionReportTest extends TestCase
{
    public function testOneVersionReportsItsContentsAndNoMigration(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();

        // Act
        $report = $fixture->kernel->container()->get(VersionReport::class)->build();

        // Assert
        self::assertTrue($report->version->current);
        self::assertSame('v1.2.1', $report->version->sourceVersion);
        self::assertSame(7, $report->version->concepts['occupation'] ?? null);
        self::assertNull($report->previous);
        self::assertNull($report->migration);
        $french = ($report->measures->coverage['occupation'] ?? [])['fr'] ?? null;
        self::assertNotNull($french);
        self::assertSame(1.0, $french->labelledShare());
        self::assertSame(0, $report->measures->bubbles);
        self::assertNull($report->measures->shareAsking());
    }

    public function testASecondVersionIsReportedAgainstTheFirst(): void
    {
        // Arrange: a copy of the fixture without the welder and with the architect's English label changed,
        // imported as a second version on top of the first.
        $fixture = ImportedFixture::load();
        $copy = FixtureCopy::rewriting(static function (string $file, array $record): ?array {
            if (($record['code'] ?? null) === '7212.3') {
                return null;
            }

            if (($record['code'] ?? null) === '2512.3' && \is_array($record['preferredLabel'] ?? null)) {
                $record['preferredLabel']['en'] = 'software architect (revised)';
            }

            return $record;
        });
        $first = $fixture->kernel->container()->get(CurrentVersion::class)->id();
        $fixture->kernel->container()->get(EscoImporter::class)->import(Snapshot::open($copy->directory), static function (string $line): void {});
        $fixture->kernel->container()->get(CurrentVersion::class)->refresh();

        // Act
        $report = $fixture->kernel->container()->get(VersionReport::class)->build();

        // Assert
        self::assertSame($first, $report->previous?->id);
        self::assertGreaterThan($first, $report->version->id);
        self::assertTrue($report->version->current);
        self::assertNotNull($report->migration);
        self::assertSame(['7212.3'], array_map(static fn(ConceptRef $r): ?string => $r->code, $report->migration->removed['occupation'] ?? []));
        self::assertSame([], $report->migration->added['occupation'] ?? null);
        self::assertSame(1, $report->migration->preferredLabelsChanged);
        self::assertGreaterThan(0, $report->migration->skillRelationsRemoved);
        self::assertSame(0, $report->migration->skillRelationsAdded);

        $copy->remove();
    }

    public function testTheEvaluationLeavesTheTaxonomyAndTheLogAsItFoundThem(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();
        $labels = $fixture->connection->query('SELECT COUNT(*) AS n FROM concept_label')->firstOrFail()->getInt('n');

        // Act
        $heldOut = $fixture->kernel->container()->get(ResolutionEvaluation::class)->heldOut(\BothDecks\Taxonomy\Domain\ConceptKind::Occupation, 5, 1);
        $annotated = $fixture->kernel->container()->get(ResolutionEvaluation::class)->annotated([
            ['query' => 'lasser', 'locale' => 'nl', 'kind' => 'occupation', 'code' => '7212.3'],
            ['query' => 'welder', 'locale' => 'en', 'kind' => 'occupation', 'code' => '7212.3'],
        ]);

        // Assert
        self::assertSame($labels, $fixture->connection->query('SELECT COUNT(*) AS n FROM concept_label')->firstOrFail()->getInt('n'));
        self::assertSame(0, $fixture->connection->query('SELECT COUNT(*) AS n FROM taxonomy_resolution_log')->firstOrFail()->getInt('n'));
        self::assertGreaterThan(0, ($heldOut['en'] ?? null)?->total() ?? 0);
        self::assertSame(1, ($annotated['nl'] ?? null)?->right() ?? 0);
        self::assertSame(['en', 'nl'], array_keys($annotated), 'Tallies come sorted by language.');
        self::assertSame(array_keys($heldOut), $fixture->kernel->container()->get(\BothDecks\Shared\I18n\ProductLocales::class)->all());
        // The connection is still usable after the rolled-back transactions.
        self::assertSame(7, $fixture->kernel->container()->get(BubbleComputer::class)->compute(static function (string $line): void {})->occupations);
    }
}
