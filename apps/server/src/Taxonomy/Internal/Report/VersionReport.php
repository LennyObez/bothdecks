<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Internal\Embedding\EmbeddingProvider;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Row;

/**
 * What the current version holds, what changed since the previous one, and how the taxonomy is doing.
 *
 * Every figure is read from the tables rather than from what an import printed, so the report says what is
 * there now. The migration section exists so a newer release is reviewed before profiles and offers are
 * matched against it: a removed occupation is a code some profile may carry.
 */
final readonly class VersionReport
{
    public function __construct(
        private ConnectionInterface $connection,
        private EmbeddingProvider $embeddings,
        private ProductLocales $locales,
        private CurrentVersion $version,
    ) {}

    public function build(): Report
    {
        $current = $this->version->id();
        $previous = $this->previousVersion($current);

        return new Report(
            version: $this->describe($current),
            previous: $previous === null ? null : $this->describe($previous),
            migration: $previous === null ? null : $this->migration($previous, $current),
            measures: $this->measures($current),
        );
    }

    private function previousVersion(int $current): ?int
    {
        // Identifiers are given in import order, and two imports can share a second.
        $row = $this->connection->query(
            'SELECT id FROM taxonomy_version WHERE id < :c ORDER BY id DESC LIMIT 1',
            ['c' => $current],
        )->first();

        return $row?->getInt('id');
    }

    private function describe(int $versionId): VersionSummary
    {
        $row = $this->connection->query(
            'SELECT id, source, source_version, snapshot_digest, snapshot_taken_at, imported_at, licence, attribution, is_current FROM taxonomy_version WHERE id = :v',
            ['v' => $versionId],
        )->firstOrFail();

        $counts = [];

        foreach ($this->connection->query(
            'SELECT kind, COUNT(*) AS n FROM taxonomy_concept WHERE version_id = :v GROUP BY kind ORDER BY kind',
            ['v' => $versionId],
        )->map(static fn(Row $r): array => [$r->getString('kind'), $r->getInt('n')]) as [$kind, $n]) {
            $counts[$kind] = $n;
        }

        return new VersionSummary(
            id: $row->getInt('id'),
            source: $row->getString('source'),
            sourceVersion: $row->getString('source_version'),
            digest: $row->getString('snapshot_digest'),
            snapshotTakenAt: $row->getString('snapshot_taken_at'),
            importedAt: $row->getString('imported_at'),
            licence: $row->getString('licence'),
            attribution: $row->getString('attribution'),
            current: $row->getBool('is_current'),
            concepts: $counts,
        );
    }

    private function migration(int $from, int $to): Migration
    {
        $added = [];
        $removed = [];

        foreach (ConceptKind::cases() as $kind) {
            $added[$kind->value] = $this->onlyIn($to, $from, $kind);
            $removed[$kind->value] = $this->onlyIn($from, $to, $kind);
        }

        $relabelled = $this->connection->query(
            <<<'SQL'
                SELECT COUNT(*) AS n
                FROM taxonomy_concept a
                JOIN taxonomy_concept b ON b.uri = a.uri AND b.version_id = :to
                JOIN concept_label la ON la.concept_id = a.id AND la.locale = :locale AND la.kind = 'preferred'
                JOIN concept_label lb ON lb.concept_id = b.id AND lb.locale = :locale AND lb.kind = 'preferred'
                WHERE a.version_id = :from AND la.label <> lb.label
                SQL,
            ['from' => $from, 'to' => $to, 'locale' => $this->locales->fallback()],
        )->firstOrFail()->getInt('n');

        $relations = $this->connection->query(
            <<<'SQL'
                WITH pairs AS (
                    SELECT o.version_id, o.uri AS occupation_uri, s.uri AS skill_uri, os.relation
                    FROM occupation_skill os
                    JOIN taxonomy_concept o ON o.id = os.occupation_id
                    JOIN taxonomy_concept s ON s.id = os.skill_id
                    WHERE o.version_id IN (:from, :to)
                )
                SELECT
                    (SELECT COUNT(*) FROM pairs n WHERE n.version_id = :to
                       AND NOT EXISTS (SELECT 1 FROM pairs p WHERE p.version_id = :from AND p.occupation_uri = n.occupation_uri AND p.skill_uri = n.skill_uri AND p.relation = n.relation)) AS added,
                    (SELECT COUNT(*) FROM pairs p WHERE p.version_id = :from
                       AND NOT EXISTS (SELECT 1 FROM pairs n WHERE n.version_id = :to AND n.occupation_uri = p.occupation_uri AND n.skill_uri = p.skill_uri AND n.relation = p.relation)) AS removed
                SQL,
            ['from' => $from, 'to' => $to],
        )->firstOrFail();

        return new Migration(
            from: $from,
            to: $to,
            added: $added,
            removed: $removed,
            preferredLabelsChanged: $relabelled,
            skillRelationsAdded: $relations->getInt('added'),
            skillRelationsRemoved: $relations->getInt('removed'),
        );
    }

    /**
     * Concepts of a kind present in one version and absent from the other, by source identifier.
     *
     * @return list<ConceptRef>
     */
    private function onlyIn(int $version, int $other, ConceptKind $kind): array
    {
        return $this->connection->query(
            <<<'SQL'
                SELECT c.uri, c.code, l.label
                FROM taxonomy_concept c
                JOIN concept_label l ON l.concept_id = c.id AND l.locale = :locale AND l.kind = 'preferred'
                WHERE c.version_id = :v AND c.kind = :kind
                  AND NOT EXISTS (SELECT 1 FROM taxonomy_concept o WHERE o.version_id = :other AND o.uri = c.uri)
                ORDER BY c.code, l.label
                SQL,
            ['v' => $version, 'other' => $other, 'kind' => $kind->value, 'locale' => $this->locales->fallback()],
        )->map(static fn(Row $r): ConceptRef => new ConceptRef($r->getString('uri'), $r->getNullableString('code'), $r->getString('label')));
    }

    private function measures(int $versionId): Measures
    {
        $coverage = [];

        foreach ([ConceptKind::Occupation, ConceptKind::Skill] as $kind) {
            $total = $this->connection->query(
                'SELECT COUNT(*) AS n FROM taxonomy_concept WHERE version_id = :v AND kind = :kind',
                ['v' => $versionId, 'kind' => $kind->value],
            )->firstOrFail()->getInt('n');

            foreach ($this->locales->all() as $locale) {
                $row = $this->connection->query(
                    <<<'SQL'
                        SELECT
                            (SELECT COUNT(*) FROM concept_label l JOIN taxonomy_concept c ON c.id = l.concept_id
                              WHERE c.version_id = :v AND c.kind = :kind AND l.locale = :locale AND l.kind = 'preferred') AS labelled,
                            (SELECT COUNT(*) FROM concept_description d JOIN taxonomy_concept c ON c.id = d.concept_id
                              WHERE c.version_id = :v AND c.kind = :kind AND d.locale = :locale) AS described,
                            (SELECT COUNT(*) FROM concept_label l JOIN taxonomy_concept c ON c.id = l.concept_id
                              WHERE c.version_id = :v AND c.kind = :kind AND l.locale = :locale AND l.kind <> 'preferred') AS alternatives
                        SQL,
                    ['v' => $versionId, 'kind' => $kind->value, 'locale' => $locale],
                )->firstOrFail();

                $coverage[$kind->value][$locale] = new LocaleCoverage(
                    total: $total,
                    labelled: $row->getInt('labelled'),
                    described: $row->getInt('described'),
                    alternativeLabels: $row->getInt('alternatives'),
                );
            }
        }

        $vectors = [];

        foreach ($this->connection->query(
            <<<'SQL'
                SELECT c.kind, COUNT(e.concept_id) AS n
                FROM taxonomy_concept c
                LEFT JOIN concept_embedding e ON e.concept_id = c.id AND e.model = :model
                WHERE c.version_id = :v AND c.kind IN ('occupation', 'skill')
                GROUP BY c.kind
                SQL,
            ['v' => $versionId, 'model' => $this->embeddings->model()],
        )->map(static fn(Row $r): array => [$r->getString('kind'), $r->getInt('n')]) as [$kind, $n]) {
            $vectors[$kind] = $n;
        }

        $bubbles = $this->connection->query(
            <<<'SQL'
                SELECT
                    COUNT(k.id) AS bubbles,
                    COUNT(k.id) FILTER (WHERE NOT EXISTS (SELECT 1 FROM occupation_cluster_member m WHERE m.cluster_id = k.id)) AS empty_bubbles,
                    COALESCE(AVG((SELECT COUNT(*) FROM occupation_cluster_member m WHERE m.cluster_id = k.id)), 0) AS mean_members,
                    (SELECT COUNT(*) FROM occupation_cluster_member m JOIN occupation_cluster kk ON kk.id = m.cluster_id WHERE kk.version_id = :v AND m.origin = 'added') AS added_by_a_person,
                    (SELECT COUNT(*) FROM occupation_cluster_override) AS overrides,
                    MAX(k.parameters::text) AS parameters
                FROM occupation_cluster k
                WHERE k.version_id = :v
                SQL,
            ['v' => $versionId],
        )->firstOrFail();

        $paths = [];

        foreach ($this->connection->query(
            'SELECT path, COUNT(*) AS n FROM taxonomy_resolution_log WHERE version_id = :v GROUP BY path ORDER BY path',
            ['v' => $versionId],
        )->map(static fn(Row $r): array => [$r->getString('path'), $r->getInt('n')]) as [$path, $n]) {
            $paths[$path] = $n;
        }

        return new Measures(
            coverage: $coverage,
            embeddingModel: $this->embeddings->model(),
            vectors: $vectors,
            bubbles: $bubbles->getInt('bubbles'),
            emptyBubbles: $bubbles->getInt('empty_bubbles'),
            meanMembers: round($bubbles->getFloat('mean_members'), 2),
            membersAddedByAPerson: $bubbles->getInt('added_by_a_person'),
            overridesRecorded: $bubbles->getInt('overrides'),
            bubbleParameters: $bubbles->getNullableString('parameters'),
            resolutionsByPath: $paths,
        );
    }
}
