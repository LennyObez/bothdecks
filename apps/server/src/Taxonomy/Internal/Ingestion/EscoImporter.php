<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Ingestion;

use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Domain\LabelKind;
use BothDecks\Taxonomy\Domain\SkillRelation;
use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use Psr\Clock\ClockInterface;
use Pulsar\Database\ConnectionInterface;

/**
 * Loads a snapshot into the taxonomy tables as one new version, in one transaction.
 *
 * Either the whole snapshot becomes a version or nothing does. A relation pointing at a concept the snapshot
 * does not hold, a concept without a preferred label in the reference language, a preferred label made of
 * no letters: each stops the import with the record named, because a taxonomy with a hole in it is a
 * taxonomy that will resolve a query to the wrong code one day and nobody will know why.
 *
 * Labels in languages the product does not serve are left out, and so is a second spelling of a wording a
 * concept already carries in a language, since the two normalise to the same text and the matching would
 * count one wording twice. Every row carries the source's licence and the resource it was fetched from, so a
 * row can always say where it came from and on what terms.
 */
final readonly class EscoImporter
{
    private const array FILES = [
        Snapshot::ISCO_GROUPS => ConceptKind::IscoGroup,
        Snapshot::SKILL_GROUPS => ConceptKind::SkillGroup,
        Snapshot::OCCUPATIONS => ConceptKind::Occupation,
        Snapshot::SKILLS => ConceptKind::Skill,
    ];

    private const array BROADER_LINKS = [
        'broaderIscoGroup',
        'broaderOccupation',
        'broaderConcept',
        'broaderHierarchyConcept',
        'broaderSkill',
    ];

    public function __construct(
        private ConnectionInterface $connection,
        private ProductLocales $locales,
        private ClockInterface $clock,
    ) {}

    /**
     * @param callable(string): void $log
     */
    public function import(Snapshot $snapshot, callable $log): ImportReport
    {
        $manifest = $snapshot->manifest;

        return $this->connection->transaction(function (ConnectionInterface $db) use ($snapshot, $manifest, $log): ImportReport {
            $existing = $db->query(
                'SELECT id FROM taxonomy_version WHERE source = :source AND source_version = :version AND snapshot_digest = :digest',
                ['source' => $manifest->source, 'version' => $manifest->version, 'digest' => $manifest->digest()],
            )->first();

            if ($existing !== null) {
                throw new ImportException(\sprintf(
                    'Snapshot %s of %s %s is already imported as version %d.',
                    $manifest->digest(),
                    $manifest->source,
                    $manifest->version,
                    $existing->getInt('id'),
                ));
            }

            $versionId = $db->query(
                <<<'SQL'
                    INSERT INTO taxonomy_version (source, source_version, snapshot_digest, snapshot_taken_at, licence, attribution, imported_at)
                    VALUES (:source, :version, :digest, :taken_at, :licence, :attribution, :imported_at)
                    RETURNING id
                    SQL,
                [
                    'source' => $manifest->source,
                    'version' => $manifest->version,
                    'digest' => $manifest->digest(),
                    'taken_at' => $manifest->takenAt->format(\DateTimeInterface::RFC3339),
                    'licence' => $manifest->licence,
                    'attribution' => $manifest->attribution,
                    'imported_at' => $this->clock->now()->format(\DateTimeInterface::RFC3339),
                ],
            )->firstOrFail()->getInt('id');

            $log(\sprintf('Version %d created for %s %s (%s)', $versionId, $manifest->source, $manifest->version, $manifest->digest()));

            $rows = [];
            $rows['taxonomy_concept'] = $this->writeConcepts($db, $snapshot, $versionId, $manifest->licence);
            $log(\sprintf('  %d concepts', $rows['taxonomy_concept']));

            $ids = $this->conceptIds($db, $versionId);
            $skipped = [];
            $rows += $this->writeDetails($db, $snapshot, $ids, $log, $skipped);

            foreach ($skipped as $line) {
                $log('  label without letters or digits, not imported: ' . $line);
            }

            $db->execute('UPDATE taxonomy_version SET is_current = false WHERE is_current');
            $db->execute('UPDATE taxonomy_version SET is_current = true WHERE id = :id', ['id' => $versionId]);

            $log(\sprintf('Version %d is now current', $versionId));

            return new ImportReport($versionId, $manifest->version, $manifest->digest(), $rows, \count($skipped));
        });
    }

    private function writeConcepts(ConnectionInterface $db, Snapshot $snapshot, int $versionId, string $licence): int
    {
        $writer = new BulkWriter($db, 'taxonomy_concept', ['version_id', 'kind', 'uri', 'code', 'status', 'source', 'source_uri', 'licence']);

        foreach (self::FILES as $file => $kind) {
            foreach ($snapshot->records($file) as $record) {
                $uri = self::string($record, 'uri', $file);
                $code = $record['code'] ?? null;
                $status = $record['status'] ?? null;
                $self = self::linkedUri($record, 'self', 'href');

                if ($self === null) {
                    throw new ImportException($uri . ' carries no link to itself; the provenance cannot be recorded.');
                }

                $writer->add([
                    $versionId,
                    $kind->value,
                    $uri,
                    \is_string($code) && $code !== '' ? $code : null,
                    // Group concepts carry no status in the source; they are part of a released version by
                    // being in it.
                    \is_string($status) && $status !== '' ? $status : 'released',
                    $snapshot->manifest->source,
                    $self,
                    $licence,
                ]);
            }
        }

        $writer->flush();

        return $writer->written();
    }

    /**
     * @return array<string, int> Concept identifier by source identifier.
     */
    private function conceptIds(ConnectionInterface $db, int $versionId): array
    {
        $ids = [];

        foreach ($db->query('SELECT id, uri FROM taxonomy_concept WHERE version_id = :v', ['v' => $versionId])->map(
            static fn(\Pulsar\Database\Row $row): array => [$row->getString('uri'), $row->getInt('id')],
        ) as [$uri, $id]) {
            $ids[$uri] = $id;
        }

        return $ids;
    }

    /**
     * @param array<string, int> $ids
     * @param callable(string): void $log
     * @param list<string> $skipped Receives one line per label left out, for the log.
     *
     * @return array<string, int>
     */
    private function writeDetails(ConnectionInterface $db, Snapshot $snapshot, array $ids, callable $log, array &$skipped): array
    {
        $labels = new BulkWriter($db, 'concept_label', ['concept_id', 'locale', 'kind', 'label', 'normalised']);
        $descriptions = new BulkWriter($db, 'concept_description', ['concept_id', 'locale', 'description']);
        $broader = new BulkWriter($db, 'concept_broader', ['concept_id', 'broader_id'], 'ON CONFLICT DO NOTHING');
        $occupations = new BulkWriter($db, 'occupation', ['concept_id', 'isco_group_id', 'regulated']);
        $skills = new BulkWriter($db, 'skill', ['concept_id', 'skill_type', 'reuse_level']);
        $occupationSkills = new BulkWriter($db, 'occupation_skill', ['occupation_id', 'skill_id', 'relation']);

        $groupsByOccupation = $this->occupationGroups($snapshot);

        foreach (self::FILES as $file => $kind) {
            foreach ($snapshot->records($file) as $record) {
                $uri = self::string($record, 'uri', $file);
                $id = $ids[$uri] ?? throw new ImportException($uri . ' was written and cannot be read back.');

                $this->writeLabels($labels, $record, $id, $uri, $skipped);
                $this->writeDescriptions($descriptions, $record, $id);

                foreach (self::BROADER_LINKS as $link) {
                    foreach ($this->linkedUris($record, $link) as $target) {
                        $broader->add([$id, $this->idOf($ids, $target, $uri, $link)]);
                    }
                }

                if ($kind === ConceptKind::Occupation) {
                    $regulated = self::linkedUri($record, 'regulatedProfessionNote', 'uri');

                    $occupations->add([
                        $id,
                        $this->idOf($ids, $this->groupOf($uri, $groupsByOccupation), $uri, 'broaderIscoGroup'),
                        $regulated === null ? null : self::lastSegment($regulated),
                    ]);

                    // A skill is essential or optional for an occupation, never both. Essential is read first,
                    // so a skill the source lists under both is kept as essential.
                    $related = [];

                    foreach ([SkillRelation::Essential, SkillRelation::Optional] as $relation) {
                        $link = $relation === SkillRelation::Essential ? 'hasEssentialSkill' : 'hasOptionalSkill';

                        foreach ($this->linkedUris($record, $link) as $target) {
                            $skillId = $this->idOf($ids, $target, $uri, $link);

                            if (isset($related[$skillId])) {
                                continue;
                            }

                            $related[$skillId] = true;
                            $occupationSkills->add([$id, $skillId, $relation->value]);
                        }
                    }
                }

                if ($kind === ConceptKind::Skill) {
                    $types = $this->linkedUris($record, 'hasSkillType');
                    $levels = $this->linkedUris($record, 'hasReuseLevel');

                    if (\count($types) > 1 || \count($levels) > 1) {
                        throw new ImportException(\sprintf('%s has %d skill types and %d reuse levels; at most one of each is expected.', $uri, \count($types), \count($levels)));
                    }

                    $skills->add([
                        $id,
                        $types === [] ? null : self::lastSegment($types[0]),
                        $levels === [] ? null : self::lastSegment($levels[0]),
                    ]);
                }
            }

            $log(\sprintf('  %s read', $file));
        }

        foreach ([$labels, $descriptions, $broader, $occupations, $skills, $occupationSkills] as $writer) {
            $writer->flush();
        }

        return [
            'concept_label' => $labels->written(),
            'concept_description' => $descriptions->written(),
            'concept_broader' => $broader->written(),
            'occupation' => $occupations->written(),
            'skill' => $skills->written(),
            'occupation_skill' => $occupationSkills->written(),
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param list<string> $skipped
     */
    private function writeLabels(BulkWriter $writer, array $record, int $id, string $uri, array &$skipped): void
    {
        $preferred = $record['preferredLabel'] ?? null;

        if (!\is_array($preferred) || !\is_string($preferred[$this->locales->fallback()] ?? null)) {
            throw new ImportException($uri . ' has no preferred label in ' . $this->locales->fallback() . ', the fallback locale.');
        }

        $seen = [];

        foreach ($preferred as $locale => $label) {
            if (!\is_string($locale) || !$this->locales->has($locale) || !\is_string($label)) {
                continue;
            }

            $this->addLabel($writer, $seen, $skipped, $id, $locale, LabelKind::Preferred, $label, $uri);
        }

        foreach ([[LabelKind::Alternative, 'alternativeLabel'], [LabelKind::Hidden, 'hiddenLabel']] as [$kind, $key]) {
            $lists = $record[$key] ?? [];

            if (!\is_array($lists)) {
                continue;
            }

            foreach ($lists as $locale => $list) {
                if (!\is_string($locale) || !$this->locales->has($locale) || !\is_array($list)) {
                    continue;
                }

                foreach ($list as $label) {
                    if (\is_string($label)) {
                        $this->addLabel($writer, $seen, $skipped, $id, $locale, $kind, $label, $uri);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, true> $seen
     * @param list<string> $skipped
     */
    private function addLabel(BulkWriter $writer, array &$seen, array &$skipped, int $id, string $locale, LabelKind $kind, string $label, string $uri): void
    {
        $label = trim($label);

        if ($label === '') {
            return;
        }

        $normalised = LabelNormaliser::normalise($label);

        if ($normalised === '') {
            // The source carries the odd placeholder among its alternative labels, a dash or a dot. It can
            // never be matched, so it is not imported; a preferred label like that would be a hole in the
            // interface, and is refused.
            if ($kind === LabelKind::Preferred) {
                throw new ImportException(\sprintf('%s has a preferred label in %s made of no letters or digits: %s', $uri, $locale, $label));
            }

            $skipped[] = \sprintf('%s %s %s: %s', $uri, $kind->value, $locale, $label);

            return;
        }

        // One row per wording and language: the preferred label is added first, so a repeat of it among the
        // alternatives, or a second spelling differing only in case, is not stored again.
        $key = $locale . "\0" . $normalised;

        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $writer->add([$id, $locale, $kind->value, $label, $normalised]);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function writeDescriptions(BulkWriter $writer, array $record, int $id): void
    {
        $descriptions = $record['description'] ?? null;

        if (!\is_array($descriptions)) {
            return;
        }

        foreach ($descriptions as $locale => $entry) {
            if (!\is_string($locale) || !$this->locales->has($locale) || !\is_array($entry)) {
                continue;
            }

            $literal = $entry['literal'] ?? null;

            if (!\is_string($literal) || trim($literal) === '') {
                continue;
            }

            $writer->add([$id, $locale, trim($literal)]);
        }
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return list<string>
     */
    private function linkedUris(array $record, string $link): array
    {
        $links = $record['_links'] ?? null;
        $entries = \is_array($links) ? ($links[$link] ?? []) : [];

        if (!\is_array($entries)) {
            return [];
        }

        $uris = [];

        foreach ($entries as $entry) {
            if (\is_array($entry) && \is_string($entry['uri'] ?? null)) {
                $uris[] = $entry['uri'];
            }
        }

        return $uris;
    }

    /**
     * Every occupation's group, or its parent occupation when it sits under one.
     *
     * The source attaches a group to the occupations at the top of its own hierarchy only; an occupation
     * narrower than another carries the parent instead, and inherits the parent's group. Both are read in
     * one pass so the group of any occupation can then be found by walking up.
     *
     * @return array<string, array{group: string|null, parent: string|null}>
     */
    private function occupationGroups(Snapshot $snapshot): array
    {
        $links = [];

        foreach ($snapshot->records(Snapshot::OCCUPATIONS) as $record) {
            $uri = self::string($record, 'uri', Snapshot::OCCUPATIONS);
            $groups = $this->linkedUris($record, 'broaderIscoGroup');
            $parents = $this->linkedUris($record, 'broaderOccupation');

            if (\count($groups) > 1 || \count($parents) > 1 || ($groups === [] && $parents === [])) {
                throw new ImportException(\sprintf(
                    '%s links to %d occupation groups and %d parent occupations; at most one of each and at least one of the two are expected.',
                    $uri,
                    \count($groups),
                    \count($parents),
                ));
            }

            $links[$uri] = ['group' => $groups[0] ?? null, 'parent' => $parents[0] ?? null];
        }

        return $links;
    }

    /**
     * @param array<string, array{group: string|null, parent: string|null}> $links
     */
    private function groupOf(string $uri, array $links): string
    {
        $visited = [];
        $current = $uri;

        while (true) {
            if (isset($visited[$current])) {
                throw new ImportException($uri . ' sits in a cycle of parent occupations.');
            }

            $visited[$current] = true;
            $entry = $links[$current] ?? throw new ImportException($uri . ' has a parent occupation the snapshot does not hold: ' . $current);

            if ($entry['group'] !== null) {
                return $entry['group'];
            }

            $current = $entry['parent'] ?? throw new ImportException($uri . ' reaches no occupation group through its parents.');
        }
    }

    /**
     * A single link's attribute, or null when the record has no such link.
     *
     * @param array<string, mixed> $record
     */
    private static function linkedUri(array $record, string $link, string $attribute): ?string
    {
        $links = $record['_links'] ?? null;
        $entry = \is_array($links) ? ($links[$link] ?? null) : null;
        $value = \is_array($entry) ? ($entry[$attribute] ?? null) : null;

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, int> $ids
     */
    private function idOf(array $ids, string $target, string $from, string $link): int
    {
        return $ids[$target] ?? throw new ImportException(\sprintf(
            '%s links through %s to %s, which the snapshot does not hold.',
            $from,
            $link,
            $target,
        ));
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function string(array $record, string $key, string $file): string
    {
        $value = $record[$key] ?? null;

        if (!\is_string($value) || $value === '') {
            throw new ImportException(\sprintf('A record in %s has no %s.', $file, $key));
        }

        return $value;
    }

    private static function lastSegment(string $uri): string
    {
        $position = strrpos($uri, '/');

        return $position === false ? $uri : substr($uri, $position + 1);
    }
}
