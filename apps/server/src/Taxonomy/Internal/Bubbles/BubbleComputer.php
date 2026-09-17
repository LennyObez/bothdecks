<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Bubbles;

use BothDecks\Taxonomy\Domain\BubbleOrigin;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Domain\SkillRelation;
use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Embedding\EmbeddingProvider;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use Psr\Clock\ClockInterface;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Row;

/**
 * Computes every occupation's bubble for the current version, from four signals, and stores it.
 *
 * A bubble is directed: the score of B in A's bubble says how acceptable an offer for B is to someone whose
 * occupation is A, and the reverse pair is scored on its own. Each signal is a number between 0 and 1:
 *
 *  - hierarchy: how close A and B sit in the occupation classification, or whether one is the other's
 *    parent occupation;
 *  - skills: the share of B's essential skills that are essential to A as well, which is the most
 *    explainable signal there is, and the strongest;
 *  - vector: the similarity of the two descriptions' vectors, which catches synonymy the hierarchy misses;
 *  - mobility: the share of observed moves out of A that went to B.
 *
 * The signals are combined with the configured weights. A signal that has no data for this version (no
 * vectors yet, no moves observed yet) takes weight zero and the others are rescaled to sum to one; the
 * weights actually used are stored with every bubble, so the version report can say what a bubble was
 * computed from. Corrections recorded by a person are applied last and never silently: a member they added
 * says so.
 *
 * @see docs/adr/0005-codes-not-strings.md
 */
final readonly class BubbleComputer
{
    private const int VECTOR_CANDIDATES = 50;

    public function __construct(
        private ConnectionInterface $connection,
        private TaxonomyConfig $config,
        private EmbeddingProvider $embeddings,
        private CurrentVersion $version,
        private ClockInterface $clock,
    ) {}

    /**
     * @param callable(string): void $log
     * @param int|null $only One occupation to recompute alone, after a correction; every other bubble is
     *                       left as it is. The weights are the same as a full run would use.
     */
    public function compute(callable $log, ?int $only = null): BubbleReport
    {
        $versionId = $this->version->id();
        $occupations = $this->occupations($versionId);

        if ($only !== null && !isset($occupations[$only])) {
            throw new \InvalidArgumentException('No occupation with identifier ' . $only . ' in the current version.');
        }

        $targets = $only === null ? $occupations : [$only => $occupations[$only]];
        $log(\sprintf('%d occupation(s) to compute', \count($targets)));

        $essential = $this->essentialSkills($versionId);
        $parents = $this->parentOccupations($versionId);
        $hasVectors = $this->hasVectors($versionId);
        $vectors = $hasVectors ? $this->vectorNeighbours($versionId, $only) : [];
        $mobility = $this->mobility($occupations);

        $available = [
            'hierarchy' => true,
            'skills' => true,
            'vector' => $hasVectors,
            'mobility' => $mobility !== [],
        ];
        $weights = $this->effectiveWeights($available);
        $log('Weights in use: ' . json_encode($weights, JSON_THROW_ON_ERROR));

        $bySkill = [];

        foreach ($essential as $occupationId => $skills) {
            foreach ($skills as $skillId => $_) {
                $bySkill[$skillId][] = $occupationId;
            }
        }

        $byGroup = [];

        foreach ($occupations as $id => $occupation) {
            $byGroup[substr($occupation['isco'], 0, 3)][] = $id;
        }

        $overrides = $this->overrides();
        $parameters = json_encode([
            'weights' => $weights,
            'minimum_score' => $this->config->bubbleMinimumScore,
            'maximum_neighbours' => $this->config->bubbleMaximumNeighbours,
            'embedding_model' => $this->embeddings->model(),
        ], JSON_THROW_ON_ERROR);

        $now = $this->clock->now()->format(\DateTimeInterface::RFC3339);
        $members = 0;
        $added = 0;
        $excluded = 0;

        $this->connection->transaction(function (ConnectionInterface $db) use (
            $versionId,
            $occupations,
            $targets,
            $essential,
            $parents,
            $vectors,
            $mobility,
            $weights,
            $bySkill,
            $byGroup,
            $overrides,
            $parameters,
            $now,
            &$members,
            &$added,
            &$excluded,
            $log,
        ): void {
            if (\count($targets) === \count($occupations)) {
                $db->execute('DELETE FROM occupation_cluster WHERE version_id = :v', ['v' => $versionId]);
            } else {
                $db->execute(
                    'DELETE FROM occupation_cluster WHERE version_id = :v AND occupation_id = :o',
                    ['v' => $versionId, 'o' => array_key_first($targets)],
                );
            }

            $done = 0;

            foreach ($targets as $id => $occupation) {
                $candidates = $this->candidates($id, $occupation['isco'], $essential, $bySkill, $byGroup, $vectors);
                $scored = [];

                foreach ($candidates as $other) {
                    $signals = [
                        'hierarchy' => $this->hierarchy($id, $other, $occupations, $parents),
                        'skills' => $this->skillCoverage($id, $other, $essential),
                        'vector' => $vectors[$id][$other] ?? 0.0,
                        'mobility' => ($mobility[$occupation['uri']] ?? [])[$occupations[$other]['uri'] ?? ''] ?? 0.0,
                    ];

                    $score = 0.0;

                    foreach ($weights as $signal => $weight) {
                        $score += $weight * ($signals[$signal] ?? 0.0);
                    }

                    if ($score >= $this->config->bubbleMinimumScore) {
                        $scored[$other] = ['score' => $score, 'signals' => $signals];
                    }
                }

                uasort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

                // Exclusions come before the cut, so an excluded neighbour frees its place for the next
                // candidate; members a person added come after it, on top of the computed ones.
                foreach ($overrides[$occupation['uri']] ?? [] as $neighbourUri => $override) {
                    $other = $this->idByUri($occupations, $neighbourUri);

                    if ($other !== null && $override['action'] === 'exclude' && isset($scored[$other])) {
                        unset($scored[$other]);
                        $excluded++;
                    }
                }

                $scored = \array_slice($scored, 0, $this->config->bubbleMaximumNeighbours, true);

                foreach ($overrides[$occupation['uri']] ?? [] as $neighbourUri => $override) {
                    $other = $this->idByUri($occupations, $neighbourUri);

                    if ($other === null || $override['action'] === 'exclude') {
                        continue;
                    }

                    $weight = $override['weight'];

                    if (isset($scored[$other])) {
                        if ($weight !== null) {
                            $scored[$other]['score'] = $weight;
                        }

                        continue;
                    }

                    $scored[$other] = [
                        'score' => $weight ?? 1.0,
                        'signals' => [
                            'hierarchy' => $this->hierarchy($id, $other, $occupations, $parents),
                            'skills' => $this->skillCoverage($id, $other, $essential),
                            'vector' => $vectors[$id][$other] ?? 0.0,
                            'mobility' => $mobility[$occupation['uri']][$neighbourUri] ?? 0.0,
                        ],
                        'added' => true,
                    ];
                    $added++;
                }

                $clusterId = $db->query(
                    'INSERT INTO occupation_cluster (version_id, occupation_id, computed_at, parameters) VALUES (:v, :o, :at, CAST(:p AS jsonb)) RETURNING id',
                    ['v' => $versionId, 'o' => $id, 'at' => $now, 'p' => $parameters],
                )->firstOrFail()->getInt('id');

                foreach ($scored as $other => $entry) {
                    $db->execute(
                        <<<'SQL'
                            INSERT INTO occupation_cluster_member (cluster_id, neighbour_id, weight, hierarchy, skills, vector, mobility, origin)
                            VALUES (:c, :n, :w, :h, :s, :v, :m, :origin)
                            SQL,
                        [
                            'c' => $clusterId,
                            'n' => $other,
                            'w' => round(min(1.0, $entry['score']), 4),
                            'h' => round($entry['signals']['hierarchy'], 4),
                            's' => round($entry['signals']['skills'], 4),
                            'v' => round($entry['signals']['vector'], 4),
                            'm' => round($entry['signals']['mobility'], 4),
                            'origin' => isset($entry['added']) ? BubbleOrigin::Added->value : BubbleOrigin::Computed->value,
                        ],
                    );
                    $members++;
                }

                $done++;

                if ($done % 500 === 0) {
                    $log(\sprintf('  %d / %d', $done, \count($targets)));
                }
            }
        });

        return new BubbleReport(\count($targets), $members, $added, $excluded, $weights);
    }

    /**
     * @param array<string, bool> $available
     *
     * @return array<string, float>
     */
    private function effectiveWeights(array $available): array
    {
        $weights = [];
        $sum = 0.0;

        foreach ($this->config->bubbleWeights as $signal => $weight) {
            $weights[$signal] = ($available[$signal] ?? false) ? $weight : 0.0;
            $sum += $weights[$signal];
        }

        if ($sum <= 0.0) {
            throw new \LogicException('No bubble signal has data; the hierarchy and skill signals always do.');
        }

        foreach ($weights as $signal => $weight) {
            $weights[$signal] = round($weight / $sum, 4);
        }

        return $weights;
    }

    /**
     * Occupations worth scoring against one: those in the same minor group, those sharing at least one
     * essential skill, and the nearest by vector. Everything else scores zero on every signal but mobility,
     * and a move observed to an occupation none of the other signals relate is added through the override
     * table by a person who has looked at it.
     *
     * @param array<int, array<int, true>> $essential
     * @param array<int, list<int>> $bySkill
     * @param array<string, list<int>> $byGroup
     * @param array<int, array<int, float>> $vectors
     *
     * @return list<int>
     */
    private function candidates(int $id, string $isco, array $essential, array $bySkill, array $byGroup, array $vectors): array
    {
        $set = [];

        foreach ($byGroup[substr($isco, 0, 3)] ?? [] as $other) {
            $set[$other] = true;
        }

        foreach ($essential[$id] ?? [] as $skillId => $_) {
            foreach ($bySkill[$skillId] ?? [] as $other) {
                $set[$other] = true;
            }
        }

        foreach ($vectors[$id] ?? [] as $other => $_) {
            $set[$other] = true;
        }

        unset($set[$id]);

        return array_keys($set);
    }

    /**
     * @param array<int, array{uri: string, isco: string}> $occupations
     * @param array<int, array<int, true>> $parents
     */
    private function hierarchy(int $a, int $b, array $occupations, array $parents): float
    {
        if (isset($parents[$a][$b]) || isset($parents[$b][$a])) {
            return 1.0;
        }

        return IscoProximity::between($occupations[$a]['isco'] ?? '', $occupations[$b]['isco'] ?? '');
    }

    /**
     * The share of B's essential skills that A has as essential skills: what someone in A already brings to
     * an offer for B.
     *
     * @param array<int, array<int, true>> $essential
     */
    private function skillCoverage(int $a, int $b, array $essential): float
    {
        $required = $essential[$b] ?? [];

        if ($required === []) {
            return 0.0;
        }

        $shared = \count(array_intersect_key($essential[$a] ?? [], $required));

        return $shared / \count($required);
    }

    /**
     * @return array<int, array{uri: string, isco: string}>
     */
    private function occupations(int $versionId): array
    {
        $occupations = [];

        foreach ($this->connection->query(
            <<<'SQL'
                SELECT c.id, c.uri, g.code AS isco
                FROM occupation o
                JOIN taxonomy_concept c ON c.id = o.concept_id
                JOIN taxonomy_concept g ON g.id = o.isco_group_id
                WHERE c.version_id = :v
                ORDER BY c.id
                SQL,
            ['v' => $versionId],
        )->map(static fn(Row $r): array => ['id' => $r->getInt('id'), 'uri' => $r->getString('uri'), 'isco' => $r->getString('isco')]) as $row) {
            $occupations[$row['id']] = ['uri' => $row['uri'], 'isco' => $row['isco']];
        }

        return $occupations;
    }

    /**
     * @return array<int, array<int, true>> Essential skill identifiers per occupation.
     */
    private function essentialSkills(int $versionId): array
    {
        $essential = [];

        foreach ($this->connection->query(
            <<<'SQL'
                SELECT os.occupation_id, os.skill_id
                FROM occupation_skill os
                JOIN taxonomy_concept c ON c.id = os.occupation_id
                WHERE c.version_id = :v AND os.relation = :relation
                SQL,
            ['v' => $versionId, 'relation' => SkillRelation::Essential->value],
        )->map(static fn(Row $r): array => [$r->getInt('occupation_id'), $r->getInt('skill_id')]) as [$occupationId, $skillId]) {
            $essential[$occupationId][$skillId] = true;
        }

        return $essential;
    }

    /**
     * @return array<int, array<int, true>> Parent occupation identifiers per occupation.
     */
    private function parentOccupations(int $versionId): array
    {
        $parents = [];

        foreach ($this->connection->query(
            <<<'SQL'
                SELECT b.concept_id, b.broader_id
                FROM concept_broader b
                JOIN taxonomy_concept child ON child.id = b.concept_id AND child.kind = :kind AND child.version_id = :v
                JOIN taxonomy_concept parent ON parent.id = b.broader_id AND parent.kind = :kind
                SQL,
            ['v' => $versionId, 'kind' => ConceptKind::Occupation->value],
        )->map(static fn(Row $r): array => [$r->getInt('concept_id'), $r->getInt('broader_id')]) as [$child, $parent]) {
            $parents[$child][$parent] = true;
        }

        return $parents;
    }

    /**
     * Whether any occupation of this version has a vector from the configured model.
     */
    private function hasVectors(int $versionId): bool
    {
        return $this->connection->query(
            <<<'SQL'
                SELECT 1 AS present
                FROM concept_embedding e
                JOIN occupation o ON o.concept_id = e.concept_id
                JOIN taxonomy_concept c ON c.id = e.concept_id
                WHERE c.version_id = :v AND e.model = :model
                LIMIT 1
                SQL,
            ['v' => $versionId, 'model' => $this->embeddings->model()],
        )->first() !== null;
    }

    /**
     * The nearest occupations by vector, with cosine similarity clamped to [0, 1], for every embedded
     * occupation of the version or for one of them.
     *
     * @return array<int, array<int, float>>
     */
    private function vectorNeighbours(int $versionId, ?int $only): array
    {
        $embedded = $this->connection->query(
            <<<'SQL'
                SELECT e.concept_id
                FROM concept_embedding e
                JOIN occupation o ON o.concept_id = e.concept_id
                JOIN taxonomy_concept c ON c.id = e.concept_id
                WHERE c.version_id = :v AND e.model = :model AND (:only::bigint IS NULL OR e.concept_id = :only)
                SQL,
            ['v' => $versionId, 'model' => $this->embeddings->model(), 'only' => $only],
        )->map(static fn(Row $r): int => $r->getInt('concept_id'));

        $neighbours = [];

        foreach ($embedded as $id) {
            foreach ($this->connection->query(
                <<<'SQL'
                    SELECT o.concept_id, 1 - (o.embedding <=> me.embedding) AS similarity
                    FROM concept_embedding me
                    JOIN concept_embedding o ON o.model = me.model AND o.concept_id <> me.concept_id
                    JOIN occupation occ ON occ.concept_id = o.concept_id
                    JOIN taxonomy_concept c ON c.id = o.concept_id AND c.version_id = :v
                    WHERE me.concept_id = :id
                    ORDER BY o.embedding <=> me.embedding
                    LIMIT :limit
                    SQL,
                ['v' => $versionId, 'id' => $id, 'limit' => self::VECTOR_CANDIDATES],
            )->map(static fn(Row $r): array => [$r->getInt('concept_id'), $r->getFloat('similarity')]) as [$other, $similarity]) {
                $neighbours[$id][$other] = max(0.0, min(1.0, $similarity));
            }
        }

        return $neighbours;
    }

    /**
     * The share of observed moves out of each occupation that went to each other one, by identifier.
     *
     * @param array<int, array{uri: string, isco: string}> $occupations
     *
     * @return array<string, array<string, float>>
     */
    private function mobility(array $occupations): array
    {
        $known = [];

        foreach ($occupations as $occupation) {
            $known[$occupation['uri']] = true;
        }

        $totals = [];
        $moves = [];

        foreach ($this->connection->query('SELECT from_uri, to_uri, observations FROM occupation_transition')->map(
            static fn(Row $r): array => [$r->getString('from_uri'), $r->getString('to_uri'), $r->getInt('observations')],
        ) as [$from, $to, $count]) {
            if (!isset($known[$from]) || !isset($known[$to])) {
                continue;
            }

            $totals[$from] = ($totals[$from] ?? 0) + $count;
            $moves[$from][$to] = $count;
        }

        $shares = [];

        foreach ($moves as $from => $targets) {
            $total = $totals[$from] ?? 0;

            if ($total <= 0) {
                continue;
            }

            foreach ($targets as $to => $count) {
                $shares[$from][$to] = $count / $total;
            }
        }

        return $shares;
    }

    /**
     * @return array<string, array<string, array{action: string, weight: float|null}>> By occupation then neighbour.
     */
    private function overrides(): array
    {
        $overrides = [];

        foreach ($this->connection->query('SELECT occupation_uri, neighbour_uri, action, weight FROM occupation_cluster_override')->map(
            static fn(Row $r): array => [
                $r->getString('occupation_uri'),
                $r->getString('neighbour_uri'),
                $r->getString('action'),
                $r->getNullableFloat('weight'),
            ],
        ) as [$occupation, $neighbour, $action, $weight]) {
            $overrides[$occupation][$neighbour] = ['action' => $action, 'weight' => $weight];
        }

        return $overrides;
    }

    /**
     * @param array<int, array{uri: string, isco: string}> $occupations
     */
    private function idByUri(array $occupations, string $uri): ?int
    {
        foreach ($occupations as $id => $occupation) {
            if ($occupation['uri'] === $uri) {
                return $id;
            }
        }

        return null;
    }
}
