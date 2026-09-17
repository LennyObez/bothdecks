<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Catalogue;

use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Row;

/**
 * Finds concepts of the current version by the identifiers a person types: a code or a source identifier.
 *
 * Both are accepted wherever a command or a screen names a concept, because a code is what a person reads
 * on a screen and an identifier is what survives a version change; a lookup that accepted only one would
 * make the other useless in practice.
 */
final readonly class ConceptFinder
{
    public function __construct(
        private ConnectionInterface $connection,
        private CurrentVersion $version,
        private ProductLocales $locales,
    ) {}

    /**
     * @return array{id: int, uri: string, code: string|null, label: string}|null
     */
    public function find(ConceptKind $kind, string $codeOrUri, ?string $locale = null): ?array
    {
        $row = $this->connection->query(
            <<<'SQL'
                SELECT c.id, c.uri, c.code, COALESCE(wanted.label, fallback.label) AS label
                FROM taxonomy_concept c
                LEFT JOIN concept_label wanted ON wanted.concept_id = c.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                JOIN concept_label fallback ON fallback.concept_id = c.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                WHERE c.version_id = :v AND c.kind = :kind AND (c.uri = :key OR c.code = :key)
                SQL,
            [
                'v' => $this->version->id(),
                'kind' => $kind->value,
                'key' => $codeOrUri,
                'locale' => $locale ?? $this->locales->fallback(),
                'fallback' => $this->locales->fallback(),
            ],
        )->first();

        return $row === null ? null : self::shape($row);
    }

    /**
     * Occupations whose preferred or alternative label in the locale contains the text, best first.
     *
     * @return list<array{id: int, uri: string, code: string|null, label: string}>
     */
    public function search(ConceptKind $kind, string $text, string $locale, int $limit): array
    {
        // The escape character itself is escaped first, so a backslash typed by a person stays a backslash
        // instead of escaping the wildcard that follows it.
        $needle = '%' . strtr(mb_strtolower(trim($text)), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';

        return $this->connection->query(
            <<<'SQL'
                SELECT DISTINCT c.id, c.uri, c.code, COALESCE(wanted.label, fallback.label) AS label
                FROM taxonomy_concept c
                JOIN concept_label any_label ON any_label.concept_id = c.id AND any_label.locale = :locale
                LEFT JOIN concept_label wanted ON wanted.concept_id = c.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                JOIN concept_label fallback ON fallback.concept_id = c.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                WHERE c.version_id = :v AND c.kind = :kind AND lower(any_label.label) LIKE :needle ESCAPE '\'
                ORDER BY label
                LIMIT :limit
                SQL,
            [
                'v' => $this->version->id(),
                'kind' => $kind->value,
                'locale' => $locale,
                'fallback' => $this->locales->fallback(),
                'needle' => $needle,
                'limit' => $limit,
            ],
        )->map(self::shape(...));
    }

    /**
     * @return array{id: int, uri: string, code: string|null, label: string}
     */
    private static function shape(Row $row): array
    {
        return [
            'id' => $row->getInt('id'),
            'uri' => $row->getString('uri'),
            'code' => $row->getNullableString('code'),
            'label' => $row->getString('label'),
        ];
    }
}
