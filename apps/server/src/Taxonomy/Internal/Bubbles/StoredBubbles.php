<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Bubbles;

use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Taxonomy\Contracts\OccupationBubbles;
use BothDecks\Taxonomy\Domain\BubbleNeighbour;
use BothDecks\Taxonomy\Domain\BubbleOrigin;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Row;

/**
 * Reads the bubbles the computation stored, for the current version, labelled in the reader's language.
 *
 * Nothing is computed here. A bubble read at request time is the one computed offline, so two readers see
 * the same bubble and a deck is explainable by what is in the table.
 */
final readonly class StoredBubbles implements OccupationBubbles
{
    public function __construct(
        private ConnectionInterface $connection,
        private CurrentVersion $version,
        private ProductLocales $locales,
    ) {}

    public function neighboursOf(int $occupationConceptId, string $locale): array
    {
        if (!$this->locales->has($locale)) {
            throw new \InvalidArgumentException('Not a locale the product serves: ' . $locale);
        }

        return $this->connection->query(
            <<<'SQL'
                SELECT m.neighbour_id, c.uri, c.code, m.weight, m.hierarchy, m.skills, m.vector, m.mobility, m.origin,
                       COALESCE(wanted.label, fallback.label) AS label
                FROM occupation_cluster k
                JOIN occupation_cluster_member m ON m.cluster_id = k.id
                JOIN taxonomy_concept c ON c.id = m.neighbour_id
                LEFT JOIN concept_label wanted ON wanted.concept_id = c.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                JOIN concept_label fallback ON fallback.concept_id = c.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                WHERE k.version_id = :v AND k.occupation_id = :o
                ORDER BY m.weight DESC, c.code
                SQL,
            ['v' => $this->version->id(), 'o' => $occupationConceptId, 'locale' => $locale, 'fallback' => $this->locales->fallback()],
        )->map(static fn(Row $r): BubbleNeighbour => new BubbleNeighbour(
            conceptId: $r->getInt('neighbour_id'),
            uri: $r->getString('uri'),
            code: $r->getNullableString('code'),
            label: $r->getString('label'),
            weight: $r->getFloat('weight'),
            hierarchy: $r->getFloat('hierarchy'),
            skills: $r->getFloat('skills'),
            vector: $r->getFloat('vector'),
            mobility: $r->getFloat('mobility'),
            origin: BubbleOrigin::from($r->getString('origin')),
        ));
    }
}
