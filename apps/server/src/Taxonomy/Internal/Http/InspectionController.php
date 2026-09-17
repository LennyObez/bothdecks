<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Http;

use BothDecks\Shared\Config\ApplicationIdentity;
use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Taxonomy\Contracts\OccupationBubbles;
use BothDecks\Taxonomy\Contracts\TaxonomyResolver;
use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Internal\Catalogue\ConceptFinder;
use BothDecks\Taxonomy\Internal\Report\VersionReport;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use BothDecks\Taxonomy\Internal\Versioning\NoCurrentVersionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Row;
use Pulsar\Http\Message\Response;
use Pulsar\View\Engine\TemplateEngineInterface;

/**
 * The inspection screen: what the taxonomy holds, an occupation's bubble with the signals behind each
 * neighbour and the corrections a person made, and the cascade run on any text.
 *
 * It reads; it does not write. A correction is made at the console with a reason and an author, because
 * the screen has no way to know who is at the keyboard until accounts exist, and a correction with no
 * author is one the next person undoes. The screen shows the command to run.
 */
final readonly class InspectionController
{
    private const int SEARCH_LIMIT = 50;

    public function __construct(
        private TemplateEngineInterface $view,
        private ApplicationIdentity $identity,
        private ProductLocales $locales,
        private CurrentVersion $version,
        private VersionReport $report,
        private ConceptFinder $finder,
        private OccupationBubbles $bubbles,
        private TaxonomyResolver $resolver,
        private ConnectionInterface $connection,
    ) {}

    /**
     * @param array<string, string> $params
     */
    public function overview(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->withVersion($request, fn(): ResponseInterface => $this->render('console.taxonomy.overview', [
            'report' => $this->report->build(),
        ], $request));
    }

    /**
     * @param array<string, string> $params
     */
    public function occupations(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->withVersion($request, fn(): ResponseInterface => $this->occupationsPage($request));
    }

    /**
     * @param array<string, string> $params
     */
    public function occupation(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->withVersion($request, fn(): ResponseInterface => $this->occupationPage($request, $params));
    }

    /**
     * @param array<string, string> $params
     */
    public function resolve(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->withVersion($request, fn(): ResponseInterface => $this->resolvePage($request));
    }

    /**
     * Every page needs a current version; without one, each says so instead of failing.
     *
     * @param callable(): ResponseInterface $page
     */
    private function withVersion(ServerRequestInterface $request, callable $page): ResponseInterface
    {
        try {
            return $page();
        } catch (NoCurrentVersionException $absent) {
            return $this->render('console.taxonomy.empty', ['reason' => $absent->getMessage()], $request);
        }
    }

    private function occupationsPage(ServerRequestInterface $request): ResponseInterface
    {
        $query = trim(self::queryString($request, 'q'));
        $locale = $this->locale($request);

        $results = $query === '' ? [] : $this->finder->search(ConceptKind::Occupation, $query, $locale, self::SEARCH_LIMIT);

        return $this->render('console.taxonomy.occupations', [
            'query' => $query,
            'results' => $results,
            'limit' => self::SEARCH_LIMIT,
        ], $request);
    }

    /**
     * @param array<string, string> $params
     */
    private function occupationPage(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $locale = $this->locale($request);
        $id = (int) ($params['id'] ?? 0);

        $occupation = $this->connection->query(
            <<<'SQL'
                SELECT c.id, c.uri, c.code, c.status, c.source_uri, c.licence,
                       COALESCE(wanted.label, fallback.label) AS label,
                       g.code AS isco_code,
                       COALESCE(gl.label, gf.label) AS isco_label,
                       COALESCE(d.description, df.description) AS description
                FROM taxonomy_concept c
                JOIN occupation o ON o.concept_id = c.id
                JOIN taxonomy_concept g ON g.id = o.isco_group_id
                LEFT JOIN concept_label wanted ON wanted.concept_id = c.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                JOIN concept_label fallback ON fallback.concept_id = c.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                LEFT JOIN concept_label gl ON gl.concept_id = g.id AND gl.locale = :locale AND gl.kind = 'preferred'
                JOIN concept_label gf ON gf.concept_id = g.id AND gf.locale = :fallback AND gf.kind = 'preferred'
                LEFT JOIN concept_description d ON d.concept_id = c.id AND d.locale = :locale
                LEFT JOIN concept_description df ON df.concept_id = c.id AND df.locale = :fallback
                WHERE c.id = :id AND c.version_id = :v AND c.kind = 'occupation'
                SQL,
            ['id' => $id, 'v' => $this->version->id(), 'locale' => $locale, 'fallback' => $this->locales->fallback()],
        )->first();

        if ($occupation === null) {
            return $this->render('console.taxonomy.missing', ['id' => $id], $request, 404);
        }

        $alternatives = $this->connection->query(
            'SELECT label FROM concept_label WHERE concept_id = :id AND locale = :locale AND kind <> \'preferred\' ORDER BY label',
            ['id' => $id, 'locale' => $locale],
        )->pluck('label');

        $skills = $this->connection->query(
            <<<'SQL'
                SELECT s.id, COALESCE(wanted.label, fallback.label) AS label, os.relation
                FROM occupation_skill os
                JOIN taxonomy_concept s ON s.id = os.skill_id
                LEFT JOIN concept_label wanted ON wanted.concept_id = s.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                JOIN concept_label fallback ON fallback.concept_id = s.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                WHERE os.occupation_id = :id
                ORDER BY os.relation, label
                SQL,
            ['id' => $id, 'locale' => $locale, 'fallback' => $this->locales->fallback()],
        )->map(static fn(Row $r): array => ['label' => $r->getString('label'), 'relation' => $r->getString('relation')]);

        $overrides = $this->connection->query(
            <<<'SQL'
                SELECT o.neighbour_uri, o.action, o.weight, o.reason, o.author, o.created_at,
                       COALESCE(wanted.label, fallback.label) AS label, n.code
                FROM occupation_cluster_override o
                LEFT JOIN taxonomy_concept n ON n.uri = o.neighbour_uri AND n.version_id = :v
                LEFT JOIN concept_label wanted ON wanted.concept_id = n.id AND wanted.locale = :locale AND wanted.kind = 'preferred'
                LEFT JOIN concept_label fallback ON fallback.concept_id = n.id AND fallback.locale = :fallback AND fallback.kind = 'preferred'
                WHERE o.occupation_uri = :uri
                ORDER BY o.created_at DESC
                SQL,
            ['v' => $this->version->id(), 'uri' => $occupation->getString('uri'), 'locale' => $locale, 'fallback' => $this->locales->fallback()],
        )->map(static fn(Row $r): array => [
            'neighbour_uri' => $r->getString('neighbour_uri'),
            'label' => $r->getNullableString('label'),
            'code' => $r->getNullableString('code'),
            'action' => $r->getString('action'),
            'weight' => $r->getNullableFloat('weight'),
            'reason' => $r->getString('reason'),
            'author' => $r->getString('author'),
            'created_at' => $r->getString('created_at'),
        ]);

        return $this->render('console.taxonomy.occupation', [
            'occupation' => $occupation->toArray(),
            'alternatives' => $alternatives,
            'skills' => $skills,
            'neighbours' => $this->bubbles->neighboursOf($id, $locale),
            'overrides' => $overrides,
        ], $request);
    }

    private function resolvePage(ServerRequestInterface $request): ResponseInterface
    {
        $query = trim(self::queryString($request, 'q'));
        $locale = $this->locale($request);
        $kind = ConceptKind::tryFrom(self::queryString($request, 'kind')) ?? ConceptKind::Occupation;

        if (!\in_array($kind, ConceptKind::resolvable(), true)) {
            $kind = ConceptKind::Occupation;
        }

        $resolution = $query === '' ? null : $this->resolver->resolve($query, $locale, $kind);

        return $this->render('console.taxonomy.resolution', [
            'query' => $query,
            'kind' => $kind->value,
            'resolution' => $resolution,
        ], $request);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data, ServerRequestInterface $request, int $status = 200): ResponseInterface
    {
        return Response::view($this->view, $template, $data + [
            'product' => $this->identity->name(),
            'locale' => $this->locale($request),
            'locales' => $this->locales->all(),
            'fallback' => $this->locales->fallback(),
        ], $status);
    }

    /**
     * The language the screen reads in: the query string's, when it is one the product serves, else the
     * fallback. The screen is for a person inspecting data in every language, so it never guesses from
     * the browser.
     */
    private function locale(ServerRequestInterface $request): string
    {
        $wanted = self::queryString($request, 'locale');

        return $this->locales->has($wanted) ? $wanted : $this->locales->fallback();
    }

    private static function queryString(ServerRequestInterface $request, string $key): string
    {
        $value = $request->getQueryParams()[$key] ?? '';

        return \is_string($value) ? $value : '';
    }
}
