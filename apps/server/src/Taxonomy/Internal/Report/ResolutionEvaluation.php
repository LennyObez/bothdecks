<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Taxonomy\Contracts\TaxonomyResolver;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Row;

/**
 * Measures the cascade, per language, two ways.
 *
 * Held out: a sample of alternative labels is removed from the taxonomy inside a transaction, each is
 * resolved as if a person had typed it, and the transaction is rolled back. Every one of them names a
 * concept the taxonomy still holds under other labels, so the measure is how often the approximate and
 * vector steps find the right concept for a wording the labels do not carry, which is what a real query
 * mostly is. The sample is drawn with a fixed seed, so two runs on the same version measure the same labels.
 *
 * Annotated: a file of queries written by a person, with the code each should resolve to. Small, and the
 * only measure of colloquial wordings the source never lists.
 *
 * Both report the same four figures: resolved to the right concept, resolved to a wrong one, asked with the
 * right one among the candidates, asked without it. A wrong resolution is the figure that matters most: a
 * question costs a tap, a wrong code costs a deck of wrong offers.
 */
final readonly class ResolutionEvaluation
{
    public function __construct(
        private ConnectionInterface $connection,
        private TaxonomyResolver $resolver,
        private ProductLocales $locales,
        private CurrentVersion $version,
    ) {}

    /**
     * @param positive-int $perLocale
     *
     * @return array<string, EvaluationTally> By locale.
     */
    public function heldOut(ConceptKind $kind, int $perLocale, int $seed): array
    {
        $versionId = $this->version->id();
        $tallies = [];

        foreach ($this->locales->all() as $locale) {
            $tally = new EvaluationTally();

            try {
                $this->connection->transaction(function (ConnectionInterface $db) use ($kind, $locale, $perLocale, $seed, $versionId, $tally): void {
                    // A deterministic sample: the labels are ordered by a hash of the row and the seed, so the
                    // same seed on the same version draws the same rows. A label another concept of the same
                    // kind also carries is left out of the sample: removed from one concept it would still be
                    // an exact match for the other, and the outcome would measure the source's own
                    // ambiguity rather than the cascade.
                    $sample = $db->query(
                        <<<'SQL'
                            SELECT l.id, l.label, c.id AS concept_id
                            FROM concept_label l
                            JOIN taxonomy_concept c ON c.id = l.concept_id
                            WHERE c.version_id = :v AND c.kind = :kind AND l.locale = :locale AND l.kind = 'alternative'
                              AND NOT EXISTS (
                                SELECT 1
                                FROM concept_label o
                                JOIN taxonomy_concept oc ON oc.id = o.concept_id
                                WHERE o.locale = l.locale AND o.normalised = l.normalised AND o.concept_id <> l.concept_id
                                  AND oc.version_id = :v AND oc.kind = :kind
                              )
                            ORDER BY md5(l.id::text || ':' || :seed)
                            LIMIT :limit
                            SQL,
                        ['v' => $versionId, 'kind' => $kind->value, 'locale' => $locale, 'seed' => (string) $seed, 'limit' => $perLocale],
                    )->map(static fn(Row $r): array => ['id' => $r->getInt('id'), 'label' => $r->getString('label'), 'concept' => $r->getInt('concept_id')]);

                    if ($sample === []) {
                        return;
                    }

                    // The wording leaves the concept entirely: every row of that concept in that language
                    // with the same normalised text goes, not only the sampled row, or the exact step would
                    // still find it under another spelling and the measure would be of nothing.
                    $placeholders = [];
                    $bindings = [];

                    foreach ($sample as $index => $row) {
                        $placeholders[] = ':id' . $index;
                        $bindings['id' . $index] = $row['id'];
                    }

                    $db->execute(
                        <<<'SQL'
                            DELETE FROM concept_label
                            WHERE id IN (
                                SELECT other.id
                                FROM concept_label sampled
                                JOIN concept_label other ON other.concept_id = sampled.concept_id
                                    AND other.locale = sampled.locale AND other.normalised = sampled.normalised
                                WHERE sampled.id IN (
                            SQL . implode(', ', $placeholders) . '))',
                        $bindings,
                    );

                    foreach ($sample as $row) {
                        $tally->record($this->resolver->resolve($row['label'], $locale, $kind), $row['concept']);
                    }

                    // Nothing this block did survives it: not the deletions, not the log rows the
                    // resolutions wrote, so the measure leaves the taxonomy and the log as it found them.
                    throw new RollBack();
                });
            } catch (RollBack) {
                // The tally was filled before the roll back; the roll back is the point.
            }

            $tallies[$locale] = $tally;
        }

        return $tallies;
    }

    /**
     * @param list<array{query: string, locale: string, kind: string, code: string}> $queries
     *
     * @return array<string, EvaluationTally> By locale.
     */
    public function annotated(array $queries): array
    {
        $versionId = $this->version->id();
        $tallies = [];

        try {
            // Inside a transaction that is rolled back for one reason: the resolutions must not reach the
            // log, or the log's share of questions would measure evaluation runs rather than users.
            $this->connection->transaction(function (ConnectionInterface $db) use ($queries, $versionId, &$tallies): void {
                foreach ($queries as $index => $query) {
                    $kind = ConceptKind::tryFrom($query['kind']);

                    if ($kind === null || !$this->locales->has($query['locale'])) {
                        throw new \InvalidArgumentException(\sprintf('Annotated query %d names an unknown kind or locale.', $index));
                    }

                    $expected = $db->query(
                        'SELECT id FROM taxonomy_concept WHERE version_id = :v AND kind = :kind AND code = :code',
                        ['v' => $versionId, 'kind' => $kind->value, 'code' => $query['code']],
                    )->first();

                    if ($expected === null) {
                        throw new \InvalidArgumentException(\sprintf('Annotated query %d expects code %s, which this version does not hold.', $index, $query['code']));
                    }

                    $tallies[$query['locale']] ??= new EvaluationTally();
                    $tallies[$query['locale']]->record($this->resolver->resolve($query['query'], $query['locale'], $kind), $expected->getInt('id'));
                }

                throw new RollBack();
            });
        } catch (RollBack) {
            // The tallies were filled before the roll back; the roll back is the point.
        }

        ksort($tallies);

        return $tallies;
    }
}
