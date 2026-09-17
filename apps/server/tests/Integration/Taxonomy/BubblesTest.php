<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Taxonomy;

use BothDecks\Taxonomy\Contracts\OccupationBubbles;
use BothDecks\Taxonomy\Domain\BubbleNeighbour;
use BothDecks\Taxonomy\Domain\BubbleOrigin;
use BothDecks\Taxonomy\Internal\Bubbles\BubbleComputer;
use BothDecks\Taxonomy\Internal\Bubbles\BubbleReport;
use BothDecks\Taxonomy\Internal\Bubbles\StoredBubbles;
use BothDecks\Taxonomy\Internal\Embedding\ConceptEmbedder;
use BothDecks\Tests\Integration\Support\ImportedFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Bubbles on the fixture: seven occupations in three groups. The software architect (2512.3) and the
 * software developer (2512.4) share a unit group and many essential skills; the welder (7212.3) shares
 * nothing with either.
 */
#[CoversClass(BubbleComputer::class)]
#[CoversClass(StoredBubbles::class)]
final class BubblesTest extends TestCase
{
    public function testAnOccupationsBubbleHoldsItsNearestAndNotTheUnrelated(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();

        // Act
        $report = self::compute($fixture);
        $architect = self::bubbles($fixture)->neighboursOf($fixture->conceptId('2512.3'), 'en');

        // Assert
        self::assertSame(7, $report->occupations);
        self::assertSame(
            $fixture->connection->query('SELECT COUNT(*) AS n FROM occupation_cluster_member')->firstOrFail()->getInt('n'),
            $report->members,
        );
        $codes = array_map(static fn(BubbleNeighbour $n): ?string => $n->code, $architect);
        self::assertContains('2512.4', $codes);
        self::assertNotContains('7212.3', $codes);
        self::assertNotContains('2512.3', $codes);

        // The signals are kept beside the weight: the developer shares the architect's unit group, and the
        // architect brings two of the developer's twelve essential skills; seen from the games developer
        // (2513.1), the developer shares the minor group only.
        $developer = self::neighbour($architect, '2512.4');
        self::assertSame(1.0, $developer->hierarchy);
        self::assertSame(round(2 / 12, 4), $developer->skills);
        $fromGamesDeveloper = self::neighbour(self::bubbles($fixture)->neighboursOf($fixture->conceptId('2513.1'), 'en'), '2512.4');
        self::assertSame(0.75, $fromGamesDeveloper->hierarchy);
    }

    public function testSignalsWithoutDataTakeWeightZeroAndTheOthersAreRescaled(): void
    {
        // No vectors and no observed moves in a fresh fixture: two signals out of four carry the bubble.

        // Arrange
        $fixture = ImportedFixture::load();

        // Act
        $weights = self::compute($fixture)->weights;

        // Assert
        self::assertSame(0.0, $weights['vector'] ?? null);
        self::assertSame(0.0, $weights['mobility'] ?? null);
        self::assertEqualsWithDelta(1.0, array_sum($weights), 0.001);
        self::assertGreaterThan($weights['hierarchy'] ?? 0.0, $weights['skills'] ?? 0.0);
    }

    public function testTheVectorSignalJoinsOnceTheOccupationsAreEmbedded(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();
        $fixture->kernel->container()->get(ConceptEmbedder::class)->embedMissing(static function (int $done, int $total): void {});

        // Act
        $weights = self::compute($fixture)->weights;

        // Assert
        self::assertGreaterThan(0.0, $weights['vector'] ?? 0.0);
        self::assertSame(0.0, $weights['mobility'] ?? null);
    }

    public function testTheBubbleIsLabelledInTheReadersLanguageWithFallback(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();
        self::compute($fixture);

        // Act
        $french = self::bubbles($fixture)->neighboursOf($fixture->conceptId('2512.3'), 'fr');

        // Assert
        $first = $french[0] ?? null;
        self::assertInstanceOf(BubbleNeighbour::class, $first);
        self::assertStringContainsStringIgnoringCase('logiciel', $first->label);
    }

    public function testAPersonCanExcludeAComputedNeighbourAndAddAnother(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();
        self::compute($fixture);
        $architect = $fixture->conceptId('2512.3');

        $fixture->connection->execute(
            <<<'SQL'
                INSERT INTO occupation_cluster_override (occupation_uri, neighbour_uri, action, weight, reason, author, created_at) VALUES
                (:o, :developer, 'exclude', NULL, 'Reviewed: not substitutable here', 'reviewer', now()),
                (:o, :welder, 'include', 0.42, 'Reviewed: a real move seen in the field', 'reviewer', now())
                SQL,
            ['o' => self::uriOf($fixture, '2512.3'), 'developer' => self::uriOf($fixture, '2512.4'), 'welder' => self::uriOf($fixture, '7212.3')],
        );

        // Act
        $report = self::compute($fixture, $architect);
        $neighbours = self::bubbles($fixture)->neighboursOf($architect, 'en');

        // Assert
        self::assertSame(1, $report->occupations);
        self::assertSame(1, $report->excluded);
        self::assertSame(1, $report->added);

        $byCode = [];

        foreach ($neighbours as $neighbour) {
            $byCode[$neighbour->code ?? ''] = $neighbour;
        }

        self::assertArrayNotHasKey('2512.4', $byCode);
        self::assertArrayHasKey('7212.3', $byCode);
        $welder = $byCode['7212.3'] ?? null;
        self::assertInstanceOf(BubbleNeighbour::class, $welder);
        self::assertSame(BubbleOrigin::Added, $welder->origin);
        self::assertEqualsWithDelta(0.42, $welder->weight, 0.0001);
    }

    public function testRecomputingOneOccupationLeavesTheOthersBubblesInPlace(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();
        self::compute($fixture);
        $before = $fixture->connection->query('SELECT COUNT(*) AS n FROM occupation_cluster')->firstOrFail()->getInt('n');

        // Act
        self::compute($fixture, $fixture->conceptId('7212.3'));
        $after = $fixture->connection->query('SELECT COUNT(*) AS n FROM occupation_cluster')->firstOrFail()->getInt('n');

        // Assert
        self::assertSame(7, $before);
        self::assertSame(7, $after);
    }

    private static function compute(ImportedFixture $fixture, ?int $only = null): BubbleReport
    {
        return $fixture->kernel->container()->get(BubbleComputer::class)->compute(static function (string $line): void {}, $only);
    }

    private static function bubbles(ImportedFixture $fixture): OccupationBubbles
    {
        return $fixture->kernel->container()->get(OccupationBubbles::class);
    }

    /**
     * @param list<BubbleNeighbour> $neighbours
     */
    private static function neighbour(array $neighbours, string $code): BubbleNeighbour
    {
        foreach ($neighbours as $neighbour) {
            if ($neighbour->code === $code) {
                return $neighbour;
            }
        }

        self::fail($code . ' is not in the bubble.');
    }

    private static function uriOf(ImportedFixture $fixture, string $code): string
    {
        return $fixture->connection
            ->query('SELECT uri FROM taxonomy_concept WHERE code = :code', ['code' => $code])
            ->firstOrFail()
            ->getString('uri');
    }
}
