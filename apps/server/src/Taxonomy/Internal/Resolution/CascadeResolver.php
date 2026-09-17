<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Resolution;

use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Taxonomy\Contracts\TaxonomyResolver;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Domain\Resolution;
use BothDecks\Taxonomy\Domain\ResolutionCandidate;
use BothDecks\Taxonomy\Domain\ResolutionPath;
use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Embedding\ConceptEmbedder;
use BothDecks\Taxonomy\Internal\Embedding\EmbeddingProvider;
use BothDecks\Taxonomy\Internal\Ingestion\LabelNormaliser;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use Psr\Clock\ClockInterface;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Row;

/**
 * The resolution cascade: exact, then approximate, then vector, then ask.
 *
 * Each step either answers with one concept, hands the question to the user with the concepts it found, or
 * passes to the next step because it found nothing close enough. A step never guesses between two concepts
 * it cannot tell apart: an exact match on two labels, or two approximate matches within a hair of each
 * other, is a question, because the cost of asking is one tap and the cost of a wrong code is a deck full
 * of the wrong offers.
 *
 * Every call writes one log row with the path, the score, the version and the candidates shown, so a code
 * can be explained afterwards by reading the log rather than by re-running the query against a taxonomy
 * that may have moved.
 *
 * @see docs/adr/0005-codes-not-strings.md
 */
final readonly class CascadeResolver implements TaxonomyResolver
{
    /**
     * How far ahead of the runner-up the best approximate or vector candidate must be to be trusted alone.
     */
    private const float CLEAR_MARGIN = 0.05;

    public function __construct(
        private ConnectionInterface $connection,
        private EmbeddingProvider $embeddings,
        private TaxonomyConfig $config,
        private ProductLocales $locales,
        private CurrentVersion $version,
        private ClockInterface $clock,
    ) {}

    public function resolve(string $query, string $locale, ConceptKind $kind): Resolution
    {
        if (!$this->locales->has($locale)) {
            throw new \InvalidArgumentException('Not a locale the product serves: ' . $locale);
        }

        if (!\in_array($kind, ConceptKind::resolvable(), true)) {
            throw new \InvalidArgumentException('Free text does not resolve to a ' . $kind->value . '.');
        }

        $started = hrtime(true);
        $versionId = $this->version->id();
        $normalised = LabelNormaliser::normalise($query);

        $resolution = $normalised === ''
            ? new Resolution(ResolutionPath::Ask, [], $versionId, $locale, $query)
            : ($this->exact($normalised, $locale, $kind, $versionId, $query)
                ?? $this->approximateThenVector($normalised, $query, $locale, $kind, $versionId));

        $this->log($resolution, $kind, $normalised, (int) ((hrtime(true) - $started) / 1_000_000));

        return $resolution;
    }

    /**
     * The normalised query equals a normalised label. One concept: resolved. Several: resolved to the one
     * whose preferred label matched, when exactly one did, since the preferred label is what the source
     * calls the thing; otherwise the user chooses.
     */
    private function exact(string $normalised, string $locale, ConceptKind $kind, int $versionId, string $query): ?Resolution
    {
        $rows = $this->connection->query(
            <<<'SQL'
                SELECT c.id, c.uri, c.code,
                       COALESCE(wanted.label, fallback.label) AS label,
                       bool_or(l.kind = 'preferred') AS on_preferred
                FROM concept_label l
                JOIN taxonomy_concept c ON c.id = l.concept_id
                LEFT JOIN concept_label wanted ON wanted.concept_id = c.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                JOIN concept_label fallback ON fallback.concept_id = c.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                WHERE c.version_id = :v AND c.kind = :kind AND l.locale = :locale AND l.normalised = :q
                GROUP BY c.id, c.uri, c.code, wanted.label, fallback.label
                ORDER BY on_preferred DESC, c.code, c.id
                SQL,
            ['v' => $versionId, 'kind' => $kind->value, 'locale' => $locale, 'fallback' => $this->locales->fallback(), 'q' => $normalised],
        )->map(static fn(Row $r): array => ['candidate' => self::candidate($r, $kind, 1.0), 'preferred' => $r->getBool('on_preferred')]);

        if ($rows === []) {
            return null;
        }

        if (\count($rows) === 1) {
            return new Resolution(ResolutionPath::Exact, [$rows[0]['candidate']], $versionId, $locale, $query);
        }

        $onPreferred = array_values(array_filter($rows, static fn(array $row): bool => $row['preferred']));

        if (\count($onPreferred) === 1) {
            return new Resolution(ResolutionPath::Exact, [$onPreferred[0]['candidate']], $versionId, $locale, $query);
        }

        $candidates = array_map(static fn(array $row): ResolutionCandidate => $row['candidate'], $rows);

        return new Resolution(ResolutionPath::Ask, \array_slice($candidates, 0, $this->config->candidatesWhenAsking), $versionId, $locale, $query);
    }

    /**
     * The two probabilistic steps, and how they check each other.
     *
     * An approximate match whose whole label is a typo away from the query stands alone. A looser one, or
     * a match on one word of a longer label, is trusted only when the vector step names the same concept
     * above its own floor: character trigrams alone are what turns a word that happens to share letters
     * with another into a wrong code, and the vectors, which carry meaning rather than letters, are the
     * check. When the two disagree, the user chooses between what both found. When the labels offer
     * nothing, the vector step decides alone, with its floor and margin.
     *
     * Before the concepts have vectors, only a whole-label match can resolve; a word match asks. The
     * evaluation records what that costs, and the vectors are computed right after an import for that reason.
     */
    private function approximateThenVector(string $normalised, string $query, string $locale, ConceptKind $kind, int $versionId): Resolution
    {
        $scored = $this->approximateCandidates($normalised, $locale, $kind, $versionId);
        $approximate = array_map(static fn(array $entry): ResolutionCandidate => $entry['candidate'], $scored);
        $best = $approximate[0] ?? null;
        $whole = $scored[0]['whole'] ?? 0.0;

        if ($best !== null && $whole >= $this->config->approximateTrustedSimilarity && self::clear($approximate)) {
            return new Resolution(ResolutionPath::Approximate, [$best], $versionId, $locale, $query);
        }

        $vectors = $this->vectorCandidates($query, $locale, $kind, $versionId);

        if ($vectors === []) {
            if ($best === null) {
                return new Resolution(ResolutionPath::Ask, [], $versionId, $locale, $query);
            }

            return $whole >= $this->config->approximateMinimumSimilarity && self::clear($approximate)
                ? new Resolution(ResolutionPath::Approximate, [$best], $versionId, $locale, $query)
                : new Resolution(ResolutionPath::Ask, $approximate, $versionId, $locale, $query);
        }

        $vectorBest = $vectors[0] ?? throw new \LogicException('A non-empty list has a first element.');
        $vectorConfident = $vectorBest->score >= $this->config->vectorMinimumSimilarity;

        if ($best !== null) {
            if (self::clear($approximate) && $vectorConfident && $vectorBest->conceptId === $best->conceptId) {
                return new Resolution(ResolutionPath::Approximate, [$best], $versionId, $locale, $query);
            }

            return new Resolution(ResolutionPath::Ask, self::merge($approximate, $vectors), $versionId, $locale, $query);
        }

        if ($vectorConfident && self::clear($vectors)) {
            return new Resolution(ResolutionPath::Vector, [$vectorBest], $versionId, $locale, $query);
        }

        return new Resolution(ResolutionPath::Ask, $vectors, $versionId, $locale, $query);
    }

    /**
     * Concepts whose labels in the locale are close to the query by character trigrams, best first, through
     * the trigram index and above the configured floor.
     *
     * Two similarities are taken and the greater kept. Whole-string similarity catches a misspelt label.
     * Word similarity, the similarity of the query to the closest part of a label, catches a short query
     * against a long label: the source writes many labels as a masculine and a feminine form joined, and a
     * person types one word of it. A word many labels share scores high on all of them, which is a question
     * with those labels as the candidates, not a guess.
     *
     * @return list<array{candidate: ResolutionCandidate, whole: float}> Best first, each with its whole-string similarity.
     */
    private function approximateCandidates(string $normalised, string $locale, ConceptKind $kind, int $versionId): array
    {
        $this->connection->execute(
            'SELECT set_config(\'pg_trgm.similarity_threshold\', :t, false), set_config(\'pg_trgm.word_similarity_threshold\', :t, false)',
            ['t' => (string) $this->config->approximateMinimumSimilarity],
        );

        $rows = $this->connection->query(
            <<<'SQL'
                SELECT c.id, c.uri, c.code,
                       COALESCE(wanted.label, fallback.label) AS label,
                       MAX(GREATEST(similarity(l.normalised, :q), word_similarity(:q, l.normalised))) AS score,
                       MAX(similarity(l.normalised, :q)) AS whole,
                       bool_or(l.kind = 'preferred') AS on_preferred,
                       MIN(length(l.normalised)) AS shortest
                FROM concept_label l
                JOIN taxonomy_concept c ON c.id = l.concept_id
                LEFT JOIN concept_label wanted ON wanted.concept_id = c.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                JOIN concept_label fallback ON fallback.concept_id = c.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                WHERE c.version_id = :v AND c.kind = :kind AND l.locale = :locale AND (l.normalised % :q OR :q <% l.normalised)
                GROUP BY c.id, c.uri, c.code, wanted.label, fallback.label
                ORDER BY score DESC, on_preferred DESC, shortest, c.code, c.id
                LIMIT :limit
                SQL,
            [
                'v' => $versionId,
                'kind' => $kind->value,
                'locale' => $locale,
                'fallback' => $this->locales->fallback(),
                'q' => $normalised,
                'limit' => $this->config->candidatesWhenAsking,
            ],
        )->map(static fn(Row $r): array => [
            'candidate' => self::candidate($r, $kind, min(1.0, $r->getFloat('score'))),
            'whole' => min(1.0, $r->getFloat('whole')),
        ]);

        return $rows;
    }

    /**
     * Concepts nearest to the query's vector, best first, by cosine similarity. The query is embedded as it
     * was typed, in its own language; the model is multilingual, and how well that holds per language is
     * what the evaluation measures. Empty while the version's concepts have no vectors.
     *
     * @return list<ResolutionCandidate>
     */
    private function vectorCandidates(string $query, string $locale, ConceptKind $kind, int $versionId): array
    {
        $embedded = $this->connection->query(
            <<<'SQL'
                SELECT 1 AS present
                FROM concept_embedding e
                JOIN taxonomy_concept c ON c.id = e.concept_id
                WHERE c.version_id = :v AND c.kind = :kind AND e.model = :model
                LIMIT 1
                SQL,
            ['v' => $versionId, 'kind' => $kind->value, 'model' => $this->embeddings->model()],
        )->first();

        if ($embedded === null) {
            return [];
        }

        $vector = $this->embeddings->embed([ConceptEmbedder::text($query, null)])[0]
            ?? throw new \LogicException('One text was embedded; one vector was expected.');

        return $this->connection->query(
            <<<'SQL'
                SELECT c.id, c.uri, c.code,
                       COALESCE(wanted.label, fallback.label) AS label,
                       1 - (e.embedding <=> CAST(:vec AS vector)) AS score
                FROM concept_embedding e
                JOIN taxonomy_concept c ON c.id = e.concept_id
                LEFT JOIN concept_label wanted ON wanted.concept_id = c.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                JOIN concept_label fallback ON fallback.concept_id = c.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                WHERE c.version_id = :v AND c.kind = :kind AND e.model = :model
                ORDER BY e.embedding <=> CAST(:vec AS vector)
                LIMIT :limit
                SQL,
            [
                'v' => $versionId,
                'kind' => $kind->value,
                'locale' => $locale,
                'fallback' => $this->locales->fallback(),
                'vec' => ConceptEmbedder::literal($vector),
                'model' => $this->embeddings->model(),
                'limit' => $this->config->candidatesWhenAsking,
            ],
        )->map(static fn(Row $r): ResolutionCandidate => self::candidate($r, $kind, max(0.0, min(1.0, $r->getFloat('score')))));
    }

    /**
     * What the user is shown when the two steps disagree: the approximate candidates first, then the vector
     * ones they do not already name, up to the configured number.
     *
     * @param list<ResolutionCandidate> $approximate
     * @param list<ResolutionCandidate> $vectors
     *
     * @return list<ResolutionCandidate>
     */
    private function merge(array $approximate, array $vectors): array
    {
        $merged = $approximate;
        $seen = array_map(static fn(ResolutionCandidate $c): int => $c->conceptId, $approximate);

        foreach ($vectors as $candidate) {
            if (!\in_array($candidate->conceptId, $seen, true)) {
                $merged[] = $candidate;
                $seen[] = $candidate->conceptId;
            }
        }

        return \array_slice($merged, 0, $this->config->candidatesWhenAsking);
    }

    /**
     * @param list<ResolutionCandidate> $candidates Best first.
     */
    private static function clear(array $candidates): bool
    {
        $best = $candidates[0] ?? null;
        $second = $candidates[1] ?? null;

        return $best !== null && ($second === null || $best->score - $second->score >= self::CLEAR_MARGIN);
    }

    private static function candidate(Row $row, ConceptKind $kind, float $score): ResolutionCandidate
    {
        return new ResolutionCandidate(
            kind: $kind,
            conceptId: $row->getInt('id'),
            uri: $row->getString('uri'),
            code: $row->getNullableString('code'),
            label: $row->getString('label'),
            score: $score,
        );
    }

    private function log(Resolution $resolution, ConceptKind $kind, string $normalised, int $elapsedMs): void
    {
        $answer = $resolution->resolved();

        $this->connection->execute(
            <<<'SQL'
                INSERT INTO taxonomy_resolution_log
                    (version_id, requested_at, locale, kind, query, normalised, path, concept_id, score, candidates, elapsed_ms)
                VALUES
                    (:v, :at, :locale, :kind, :query, :normalised, :path, :concept, :score, CAST(:candidates AS jsonb), :elapsed)
                SQL,
            [
                'v' => $resolution->versionId,
                'at' => $this->clock->now()->format(\DateTimeInterface::RFC3339),
                'locale' => $resolution->locale,
                'kind' => $kind->value,
                'query' => $resolution->query,
                'normalised' => $normalised,
                'path' => $resolution->path->value,
                'concept' => $answer?->conceptId,
                'score' => $answer === null ? null : round($answer->score, 5),
                'candidates' => json_encode(array_map(static fn(ResolutionCandidate $c): array => [
                    'id' => $c->conceptId,
                    'uri' => $c->uri,
                    'code' => $c->code,
                    'label' => $c->label,
                    'score' => round($c->score, 5),
                ], $resolution->candidates), JSON_THROW_ON_ERROR),
                'elapsed' => $elapsedMs,
            ],
        );
    }
}
