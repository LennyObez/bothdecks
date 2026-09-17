<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Embedding;

use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use Psr\Clock\ClockInterface;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Row;

/**
 * Gives every occupation and skill of the current version a vector.
 *
 * The text embedded is the concept's preferred label and description in the fallback locale, which is the
 * source's reference language and the one every concept is guaranteed to carry. The model is multilingual,
 * so a query in any of the product's languages is compared against these vectors without a translation
 * step; that property is measured, not assumed, by the resolution precision report.
 *
 * Concepts that already have a vector from the same model are skipped, so an interrupted run resumes where
 * it stopped; a change of model re-embeds everything, because vectors of two models do not compare.
 */
final readonly class ConceptEmbedder
{
    public function __construct(
        private ConnectionInterface $connection,
        private EmbeddingProvider $provider,
        private TaxonomyConfig $config,
        private ProductLocales $locales,
        private CurrentVersion $version,
        private ClockInterface $clock,
    ) {}

    /**
     * @param callable(int $done, int $total): void $progress
     *
     * @return int Vectors written.
     */
    public function embedMissing(callable $progress): int
    {
        $versionId = $this->version->id();
        $model = $this->provider->model();
        $this->requireColumnDimensions();

        $pending = $this->connection->query(
            <<<'SQL'
                SELECT c.id,
                       l.label,
                       d.description
                FROM taxonomy_concept c
                JOIN concept_label l ON l.concept_id = c.id AND l.locale = :locale AND l.kind = 'preferred'
                LEFT JOIN concept_description d ON d.concept_id = c.id AND d.locale = :locale
                LEFT JOIN concept_embedding e ON e.concept_id = c.id AND e.model = :model
                WHERE c.version_id = :version
                  AND c.kind IN (:occupation, :skill)
                  AND e.concept_id IS NULL
                ORDER BY c.id
                SQL,
            [
                'locale' => $this->locales->fallback(),
                'model' => $model,
                'version' => $versionId,
                'occupation' => ConceptKind::Occupation->value,
                'skill' => ConceptKind::Skill->value,
            ],
        )->map(static fn(Row $row): array => [
            'id' => $row->getInt('id'),
            'text' => self::text($row->getString('label'), $row->getNullableString('description')),
        ]);

        $total = \count($pending);
        $written = 0;

        foreach (array_chunk($pending, $this->config->embeddingBatchSize) as $batch) {
            $vectors = $this->provider->embed(array_column($batch, 'text'));
            $now = $this->clock->now()->format(\DateTimeInterface::RFC3339);

            $placeholders = [];
            $bindings = [];

            foreach ($batch as $index => $concept) {
                $vector = $vectors[$index] ?? throw new EmbeddingException('The provider answered fewer vectors than texts.');
                $placeholders[] = \sprintf('(:id%1$d, :model, CAST(:vec%1$d AS vector), :at)', $index);
                $bindings['id' . $index] = $concept['id'];
                $bindings['vec' . $index] = self::literal($vector);
            }

            $bindings['model'] = $model;
            $bindings['at'] = $now;

            // A vector written by a concurrent run for the same concept and model is the same vector; the
            // later write wins and nothing is lost.
            $written += $this->connection->execute(
                'INSERT INTO concept_embedding (concept_id, model, embedding, embedded_at) VALUES '
                . implode(', ', $placeholders)
                . ' ON CONFLICT (concept_id) DO UPDATE SET model = EXCLUDED.model, embedding = EXCLUDED.embedding, embedded_at = EXCLUDED.embedded_at',
                $bindings,
            );

            $progress($written, $total);
        }

        return $written;
    }

    /**
     * The column is typed with a dimension count and the provider answers with one; the two are written in
     * two places and this is where they meet. A mismatch is refused before a vector is computed rather than
     * reported by the engine on the first insert.
     */
    private function requireColumnDimensions(): void
    {
        $typmod = $this->connection->query(
            <<<'SQL'
                SELECT a.atttypmod AS dimensions
                FROM pg_attribute a
                WHERE a.attrelid = 'concept_embedding'::regclass AND a.attname = 'embedding'
                SQL,
        )->firstOrFail()->getInt('dimensions');

        if ($typmod !== $this->provider->dimensions()) {
            throw new EmbeddingException(\sprintf(
                'The embedding column holds vectors of %d dimensions and the provider answers %d; the model and the schema disagree.',
                $typmod,
                $this->provider->dimensions(),
            ));
        }
    }

    /**
     * The text a concept is embedded from. The same function embeds a query at resolution time, so query
     * and concept meet in the same space.
     */
    public static function text(string $label, ?string $description): string
    {
        return $description === null || $description === '' ? $label : $label . '. ' . $description;
    }

    /**
     * The engine's textual form of a vector.
     *
     * @param list<float> $vector
     */
    public static function literal(array $vector): string
    {
        return '[' . implode(',', array_map(static fn(float $v): string => \sprintf('%.8g', $v), $vector)) . ']';
    }
}
