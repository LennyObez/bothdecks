<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Taxonomy;

use BothDecks\Taxonomy\Contracts\TaxonomyResolver;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Domain\ResolutionPath;
use BothDecks\Taxonomy\Internal\Embedding\ConceptEmbedder;
use BothDecks\Taxonomy\Internal\Resolution\CascadeResolver;
use BothDecks\Tests\Integration\Support\ImportedFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pulsar\Database\Row;

/**
 * The cascade against the fixture: exact, approximate, vector, ask, each with its log row.
 */
#[CoversClass(CascadeResolver::class)]
final class ResolutionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function labelsInSeveralLanguagesOfOneConcept(): iterable
    {
        yield 'English preferred label' => ['software architect', 'en', '2512.3'];
        yield 'German preferred label' => ['Softwarearchitekt/Softwarearchitektin', 'de', '2512.3'];
        yield 'French, different case and accents' => ['CONCEPTEUR DE LOGICIELS/CONCEPTRICE DE LOGICIELS', 'fr', '2512.3'];
        yield 'Dutch welder' => ['lasser', 'nl', '7212.3'];
        yield 'Bulgarian, Cyrillic' => ['заварчик', 'bg', '7212.3'];
    }

    #[DataProvider('labelsInSeveralLanguagesOfOneConcept')]
    public function testALabelInAnyLanguageResolvesExactlyToTheSameCode(string $query, string $locale, string $code): void
    {
        // Arrange
        $resolver = self::resolver(ImportedFixture::load());

        // Act
        $resolution = $resolver->resolve($query, $locale, ConceptKind::Occupation);

        // Assert
        self::assertSame(ResolutionPath::Exact, $resolution->path);
        $answer = $resolution->resolved();
        self::assertNotNull($answer);
        self::assertSame($code, $answer->code);
        self::assertSame(1.0, $answer->score);
    }

    public function testEveryPreferredLabelInEveryLanguageLeadsToItsOwnConcept(): void
    {
        // The invariant behind codes-not-strings, over the whole fixture rather than a few examples: for
        // every concept and every language it is labelled in, its preferred label resolves to it, or asks
        // with it among the candidates when another concept carries the same label. It never resolves to
        // another concept.

        // Arrange
        $fixture = ImportedFixture::load();

        // Act
        $violations = self::labelsNotLeadingToTheirConcept($fixture);

        // Assert
        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testResolvingIsIdempotent(): void
    {
        // Arrange
        $resolver = self::resolver(ImportedFixture::load());

        // Act
        $first = $resolver->resolve('software architect', 'en', ConceptKind::Occupation);
        $second = $resolver->resolve('software architect', 'en', ConceptKind::Occupation);

        // Assert
        $firstAnswer = $first->resolved();
        $secondAnswer = $second->resolved();
        self::assertNotNull($firstAnswer);
        self::assertNotNull($secondAnswer);
        self::assertSame($firstAnswer->conceptId, $secondAnswer->conceptId);
        self::assertSame($first->path, $second->path);
    }

    public function testAMisspellingResolvesApproximatelyWhenNoOtherConceptIsAsClose(): void
    {
        // A typo away from a label, above the trusted floor: the approximate step stands alone.

        // Arrange
        $resolver = self::resolver(ImportedFixture::load());

        // Act
        $resolution = $resolver->resolve('software architecte', 'en', ConceptKind::Occupation);

        // Assert
        self::assertSame(ResolutionPath::Approximate, $resolution->path);
        $answer = $resolution->resolved();
        self::assertNotNull($answer);
        self::assertSame('2512.3', $answer->code);
        self::assertLessThan(1.0, $answer->score);
    }

    public function testOneWordOfALongerLabelIsAQuestionWithThatLabelAmongTheCandidates(): void
    {
        // "architect" is one word of "software architect" and of nothing else in the fixture; without the
        // vectors to confirm it, the cascade shows the label rather than guessing it.

        // Arrange
        $resolver = self::resolver(ImportedFixture::load());

        // Act
        $resolution = $resolver->resolve('architect', 'en', ConceptKind::Occupation);

        // Assert
        self::assertSame(ResolutionPath::Ask, $resolution->path);
        self::assertContains('2512.3', array_map(static fn($c): ?string => $c->code, $resolution->candidates));
    }

    public function testAWordMatchTheVectorsCannotConfirmIsAQuestionLedByTheLabelMatch(): void
    {
        // Arrange: "architect" matches the software architect's label by one word. With the stand-in model
        // a single word shares little with a concept's whole text, so the vectors are not confident enough
        // to confirm it, and the user chooses, the label match first.
        $fixture = ImportedFixture::load();
        $resolver = self::resolver($fixture);
        $fixture->kernel->container()->get(ConceptEmbedder::class)->embedMissing(static function (int $done, int $total): void {});

        // Act
        $resolution = $resolver->resolve('architect', 'en', ConceptKind::Occupation);

        // Assert
        self::assertSame(ResolutionPath::Ask, $resolution->path);
        self::assertSame('2512.3', ($resolution->candidates[0] ?? null)?->code);
        $ids = array_map(static fn($c): int => $c->conceptId, $resolution->candidates);
        self::assertSame($ids, array_values(array_unique($ids)), 'A concept is offered once.');
        self::assertLessThanOrEqual(3, \count($ids));
        self::assertGreaterThan(1, \count($ids), 'The vectors add what the labels did not name.');
    }

    public function testTheLogRowCarriesTheScoreToFiveDecimalsAndTheCandidatesShown(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();
        $resolver = self::resolver($fixture);
        $resolution = $resolver->resolve('software architecte', 'en', ConceptKind::Occupation);
        $answer = $resolution->resolved();
        self::assertNotNull($answer);

        // Act
        $row = $fixture->connection->query(
            'SELECT locale, query, normalised, path, concept_id, score, elapsed_ms, candidates::text AS candidates FROM taxonomy_resolution_log ORDER BY id DESC LIMIT 1',
        )->firstOrFail();

        // Assert
        self::assertSame('en', $row->getString('locale'));
        self::assertSame('software architecte', $row->getString('query'));
        self::assertSame('software architecte', $row->getString('normalised'));
        self::assertSame('approximate', $row->getString('path'));
        self::assertSame($answer->conceptId, $row->getInt('concept_id'));
        self::assertEqualsWithDelta(round($answer->score, 5), $row->getFloat('score'), 0.000001);
        self::assertGreaterThanOrEqual(0, $row->getInt('elapsed_ms'));
        $candidates = json_decode($row->getString('candidates'), true);
        self::assertIsArray($candidates);
        $first = $candidates[0] ?? null;
        self::assertIsArray($first);
        self::assertSame(['id', 'uri', 'code', 'label', 'score'], array_keys($first));
        self::assertSame($answer->uri, $first['uri'] ?? null);
        self::assertSame($answer->label, $first['label'] ?? null);
        self::assertSame(round($answer->score, 5), $first['score'] ?? null);
        self::assertLessThan(1.0, $first['score'] ?? 1.0);
    }

    public function testAWordOfALabelIsResolvedWhenTheVectorsNameTheSameConcept(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();
        $resolver = self::resolver($fixture);
        $fixture->kernel->container()->get(ConceptEmbedder::class)->embedMissing(static function (int $done, int $total): void {});

        // Act: the words of the label in another order equal no label, come close by trigrams, and the
        // stand-in model, which puts every word on a dimension, lands nearest to the same concept.
        $resolution = $resolver->resolve('architect of software', 'en', ConceptKind::Occupation);

        // Assert
        self::assertSame(ResolutionPath::Approximate, $resolution->path);
        self::assertSame('2512.3', $resolution->resolved()?->code);
    }

    public function testTextTheLabelsDoNotCarryGoesThroughTheVectorsOnceTheyExist(): void
    {
        // The query is the very text the concept is embedded from: label and description together. No label
        // equals it and none is close by trigrams, so before the vectors exist the cascade asks, and once
        // they exist the vector step finds the concept at similarity one. That proves the path from the text
        // a concept is embedded from to the vector a query is compared with, with the stand-in model.

        // Arrange
        $fixture = ImportedFixture::load();
        $resolver = self::resolver($fixture);
        $row = $fixture->connection->query(
            <<<'SQL'
                SELECT l.label, d.description
                FROM taxonomy_concept c
                JOIN concept_label l ON l.concept_id = c.id AND l.locale = 'en' AND l.kind = 'preferred'
                JOIN concept_description d ON d.concept_id = c.id AND d.locale = 'en'
                WHERE c.code = '2512.3'
                SQL,
        )->firstOrFail();
        $text = ConceptEmbedder::text($row->getString('label'), $row->getString('description'));

        $before = $resolver->resolve($text, 'en', ConceptKind::Occupation);
        $fixture->kernel->container()->get(ConceptEmbedder::class)->embedMissing(static function (int $done, int $total): void {});

        // Act
        $after = $resolver->resolve($text, 'en', ConceptKind::Occupation);

        // Assert
        self::assertSame(ResolutionPath::Ask, $before->path);
        self::assertSame(ResolutionPath::Vector, $after->path);
        $answer = $after->resolved();
        self::assertNotNull($answer);
        self::assertSame('2512.3', $answer->code);
        self::assertEqualsWithDelta(1.0, $answer->score, 0.001);
    }

    public function testNothingCloseAsksWithNoCandidates(): void
    {
        // Arrange
        $resolver = self::resolver(ImportedFixture::load());

        // Act
        $resolution = $resolver->resolve('qzxv', 'en', ConceptKind::Occupation);

        // Assert
        self::assertSame(ResolutionPath::Ask, $resolution->path);
        self::assertNull($resolution->resolved());
        self::assertSame([], $resolution->candidates);
    }

    public function testEveryResolutionIsLoggedWithItsPathScoreVersionAndCandidates(): void
    {
        // Arrange
        $fixture = ImportedFixture::load();
        $resolver = self::resolver($fixture);
        $resolver->resolve('software architect', 'en', ConceptKind::Occupation);
        $resolver->resolve('qzxv', 'nl', ConceptKind::Skill);

        // Act
        $rows = $fixture->connection
            ->query('SELECT locale, kind, query, normalised, path, concept_id, score, candidates::text AS candidates FROM taxonomy_resolution_log ORDER BY id')
            ->map(static fn(Row $r): array => [
                'kind' => $r->getString('kind'),
                'path' => $r->getString('path'),
                'concept_id' => $r->getNullableInt('concept_id'),
                'candidates' => json_decode($r->getString('candidates'), true),
            ]);

        // Assert
        self::assertCount(2, $rows);
        $exact = $rows[0] ?? [];
        $ask = $rows[1] ?? [];
        self::assertSame('exact', $exact['path'] ?? null);
        self::assertSame('occupation', $exact['kind'] ?? null);
        self::assertSame($fixture->conceptId('2512.3'), $exact['concept_id'] ?? null);
        $candidates = $exact['candidates'] ?? null;
        self::assertIsArray($candidates);
        $best = $candidates[0] ?? null;
        self::assertIsArray($best);
        self::assertSame('2512.3', $best['code'] ?? null);
        self::assertSame('ask', $ask['path'] ?? null);
        self::assertSame('skill', $ask['kind'] ?? null);
        self::assertNull($ask['concept_id'] ?? null);
        self::assertSame([], $ask['candidates'] ?? null);
    }

    public function testALocaleTheProductDoesNotServeIsRefused(): void
    {
        // Arrange
        $resolver = self::resolver(ImportedFixture::load());

        // Act
        $failure = self::capture(static fn() => $resolver->resolve('welder', 'is', ConceptKind::Occupation));

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $failure);
    }

    private static function resolver(ImportedFixture $fixture): TaxonomyResolver
    {
        return $fixture->kernel->container()->get(TaxonomyResolver::class);
    }

    /**
     * Every (concept, locale, preferred label) of the fixture whose resolution neither names the concept
     * nor offers it, described one per line.
     *
     * @return list<string>
     */
    private static function labelsNotLeadingToTheirConcept(ImportedFixture $fixture): array
    {
        $resolver = self::resolver($fixture);
        $labels = $fixture->connection->query(
            <<<'SQL'
                SELECT c.id, c.kind, c.code, c.uri, l.locale, l.label
                FROM concept_label l
                JOIN taxonomy_concept c ON c.id = l.concept_id
                JOIN taxonomy_version v ON v.id = c.version_id AND v.is_current
                WHERE l.kind = 'preferred' AND c.kind IN ('occupation', 'skill')
                ORDER BY c.kind, c.id, l.locale
                SQL,
        )->map(static fn(Row $r): array => [
            'id' => $r->getInt('id'),
            'kind' => $r->getString('kind'),
            'name' => $r->getNullableString('code') ?? $r->getString('uri'),
            'locale' => $r->getString('locale'),
            'label' => $r->getString('label'),
        ]);

        $violations = [];

        foreach ($labels as $row) {
            $kind = ConceptKind::from($row['kind']);
            $resolution = $resolver->resolve($row['label'], $row['locale'], $kind);
            $answer = $resolution->resolved();
            $offered = array_map(static fn($c): int => $c->conceptId, $resolution->candidates);

            if ($answer !== null && $answer->conceptId !== $row['id']) {
                $violations[] = \sprintf('%s "%s" (%s) resolved to %s instead', $row['name'], $row['label'], $row['locale'], $answer->code ?? $answer->uri);
            } elseif ($answer === null && !\in_array($row['id'], $offered, true)) {
                $violations[] = \sprintf('%s "%s" (%s) asked without offering it', $row['name'], $row['label'], $row['locale']);
            }
        }

        return $violations;
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
