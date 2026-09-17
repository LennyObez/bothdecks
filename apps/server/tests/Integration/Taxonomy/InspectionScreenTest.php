<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Taxonomy;

use BothDecks\Taxonomy\Internal\Bubbles\BubbleComputer;
use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Http\InspectionController;
use BothDecks\Taxonomy\Internal\Http\InspectionGate;
use BothDecks\Taxonomy\Internal\Ingestion\EscoImporter;
use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use BothDecks\Tests\Integration\Support\FixtureCopy;
use BothDecks\Tests\Integration\Support\ImportedFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pulsar\Testing\Http\TestRequestBuilder;
use Pulsar\Testing\Http\TestResponse;

/**
 * The inspection screen: closed without a token, locked without the token, and every page rendering with it.
 */
#[CoversClass(InspectionGate::class)]
#[CoversClass(InspectionController::class)]
final class InspectionScreenTest extends TestCase
{
    private const string TOKEN = 'a-token-of-at-least-twenty-four-characters';

    public function testWithoutAConfiguredTokenTheScreenDoesNotExist(): void
    {
        // Arrange: the shipped configuration reads the token from the environment, which the suite leaves unset.
        $fixture = ImportedFixture::load();
        $request = TestRequestBuilder::get('/console/taxonomy')->build();

        // Act
        $response = new TestResponse($fixture->kernel->handle($request));

        // Assert
        $response->assertNotFound();
    }

    public function testWithATokenARequestWithoutItIsAskedToAuthenticate(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());
        $request = TestRequestBuilder::get('/console/taxonomy')->build();

        // Act
        $response = new TestResponse($fixture->kernel->handle($request));

        // Assert
        $response->assertStatus(401)->assertHeader('WWW-Authenticate', 'Basic realm="Taxonomy inspection", charset="UTF-8"');
    }

    public function testAnotherAuthenticationSchemeIsRefusedLikeNone(): void
    {
        // Arrange: the right credentials under a scheme of the same length as the expected one, so that only
        // the scheme's name tells them apart.
        $fixture = self::withToken(ImportedFixture::load());
        $request = TestRequestBuilder::get('/console/taxonomy')
            ->withHeader('Authorization', 'Token ' . base64_encode('inspector:' . self::TOKEN))
            ->build();

        // Act
        $response = new TestResponse($fixture->kernel->handle($request));

        // Assert
        $response->assertStatus(401);
    }

    public function testTheOverviewShowsTheMigrationOnceASecondVersionExists(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());
        $first = $fixture->kernel->container()->get(CurrentVersion::class)->id();
        $copy = FixtureCopy::withoutOccupation('7212.3');
        $fixture->kernel->container()->get(EscoImporter::class)->import(Snapshot::open($copy->directory), static function (string $line): void {});
        $fixture->kernel->container()->get(CurrentVersion::class)->refresh();

        // Act
        $response = self::get($fixture, '/console/taxonomy?locale=en');

        // Assert
        $response->assertOk();
        $body = $response->body();
        self::assertStringContainsString('Since version ' . $first, $body);
        self::assertStringContainsString('0 preferred labels changed in en', $body);

        $copy->remove();
    }

    public function testAWrongTokenIsRefusedTheSameWayAsNone(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());
        $request = TestRequestBuilder::get('/console/taxonomy')
            ->withHeader('Authorization', 'Basic ' . base64_encode('anyone:not-the-token'))
            ->build();

        // Act
        $response = new TestResponse($fixture->kernel->handle($request));

        // Assert
        $response->assertStatus(401);
    }

    public function testTheOverviewRendersTheVersionAndTheMeasures(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());

        // Act
        $response = self::get($fixture, '/console/taxonomy?locale=fr');

        // Assert
        $response->assertOk();
        $body = $response->body();
        self::assertStringContainsString('<html lang="fr">', $body);
        self::assertStringContainsString('esco v1.2.1', $body);
        self::assertStringContainsString('CC-BY-4.0', $body);
        self::assertStringContainsString('/assets/design/generated/colors.css', $body);
        self::assertStringNotContainsString('style="', $body);
    }

    public function testTheSearchListsOccupationsByAnyLabelInTheLocale(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());

        // Act
        $response = self::get($fixture, '/console/taxonomy/occupations?q=architekt&locale=de');

        // Assert
        $response->assertOk();
        self::assertStringContainsString('2512.3', $response->body());
    }

    public function testAnOccupationPageShowsItsBubbleWithSignalsAndCorrections(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());
        $fixture->kernel->container()->get(BubbleComputer::class)->compute(static function (string $line): void {});
        $id = $fixture->conceptId('2512.3');

        // Act
        $response = self::get($fixture, '/console/taxonomy/occupations/' . $id . '?locale=en');

        // Assert
        $response->assertOk();
        $body = $response->body();
        self::assertStringContainsString('software architect', $body);
        self::assertStringContainsString('2512.4', $body);
        self::assertStringContainsString('taxonomy:bubble:override', $body);
        self::assertStringContainsString('Hierarchy', $body);
    }

    public function testAnOccupationPageListsEachCorrectionWithItsNeighbourWeightAuthorAndDate(): void
    {
        // Arrange: one neighbour excluded, one added by hand, both recorded with a date.
        $fixture = self::withToken(ImportedFixture::load());
        $fixture->connection->execute(
            <<<'SQL'
                INSERT INTO occupation_cluster_override (occupation_uri, neighbour_uri, action, weight, reason, author, created_at)
                SELECT a.uri, d.uri, 'exclude', NULL, 'Reviewed: not substitutable here', 'reviewer', TIMESTAMPTZ '2026-09-01 10:00:00+00'
                FROM taxonomy_concept a, taxonomy_concept d WHERE a.code = '2512.3' AND d.code = '2512.4'
                UNION ALL
                SELECT a.uri, w.uri, 'include', 0.42, 'Reviewed: a real move seen in the field', 'reviewer', TIMESTAMPTZ '2026-09-02 11:30:00+00'
                FROM taxonomy_concept a, taxonomy_concept w WHERE a.code = '2512.3' AND w.code = '7212.3'
                SQL,
        );
        $fixture->kernel->container()->get(BubbleComputer::class)->compute(static function (string $line): void {});

        // Act
        $response = self::get($fixture, '/console/taxonomy/occupations/' . $fixture->conceptId('2512.3') . '?locale=en');

        // Assert
        $response->assertOk();
        $body = $response->body();
        self::assertStringContainsString('<code>2512.4</code> software developer', $body);
        self::assertStringContainsString('<td>exclude</td>', $body);
        self::assertStringContainsString('<td class="num">0.42</td>', $body);
        self::assertStringContainsString('<td>Reviewed: a real move seen in the field</td>', $body);
        self::assertStringContainsString('<td>reviewer</td>', $body);
        self::assertStringContainsString('2026-09-01', $body);
        self::assertStringContainsString('2026-09-02', $body);
    }

    public function testTheResolvePageResolvesSkillsWhenAsked(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());

        // Act
        $response = self::get($fixture, '/console/taxonomy/resolve?q=3D+lighting&locale=en&kind=skill');

        // Assert
        $response->assertOk();
        $body = $response->body();
        self::assertStringContainsString('<em>exact</em>', $body);
        self::assertStringContainsString('http://data.europa.eu/esco/skill/6e53fd99-b646-4327-9580-ac062ab21188', $body);
    }

    public function testThePagesAnswerToGetOnly(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());
        $request = TestRequestBuilder::post('/console/taxonomy')
            ->withHeader('Authorization', 'Basic ' . base64_encode('inspector:' . self::TOKEN))
            ->build();

        // Act
        $response = new TestResponse($fixture->kernel->handle($request));

        // Assert
        $response->assertStatus(405);
    }

    public function testAnUnknownOccupationIsNotFoundAndSaysSo(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());

        // Act
        $response = self::get($fixture, '/console/taxonomy/occupations/999999?locale=en');

        // Assert
        $response->assertNotFound();
        self::assertStringContainsString('No occupation with identifier 999999', $response->body());
    }

    public function testTheResolvePageRunsTheCascadeAndShowsThePath(): void
    {
        // Arrange
        $fixture = self::withToken(ImportedFixture::load());

        // Act: the text is trimmed as a person's paste would need.
        $response = self::get($fixture, '/console/taxonomy/resolve?q=%20lasser%20&locale=nl&kind=occupation');

        // Assert
        $response->assertOk();
        $body = $response->body();
        self::assertStringContainsString('<em>exact</em>', $body);
        self::assertStringContainsString('7212.3', $body);
        self::assertStringContainsString('value="lasser"', $body);
    }

    public function testTheAssetsThePagesReferenceArePublished(): void
    {
        // The page links three stylesheets; each must exist under public/ once the assets are published, or
        // the screen renders unstyled and nobody notices until they open it.

        // Arrange
        $root = \dirname(__DIR__, 3);
        $publish = [];
        exec(\sprintf('%s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($root . '/tools/publish-assets.php')), $publish, $status);

        self::assertSame(0, $status, implode("\n", $publish));

        // Act
        $layout = (string) file_get_contents($root . '/resources/views/console/layout.pulse.php');
        preg_match_all('/href="(\/assets\/[^"]+)"/', $layout, $matches);

        // Assert
        self::assertNotEmpty($matches[1]);

        foreach ($matches[1] as $href) {
            self::assertFileExists($root . '/public' . $href, $href . ' is referenced by the layout and not published.');
        }
    }

    /**
     * The fixture's kernel with the inspection token configured, as a deployment would set it.
     */
    private static function withToken(ImportedFixture $fixture): ImportedFixture
    {
        $container = $fixture->kernel->container();
        $root = \dirname(__DIR__, 3);
        $config = require $root . '/config/taxonomy.php';

        self::assertIsArray($config);

        $inspection = $config['inspection'] ?? [];
        self::assertIsArray($inspection);
        $inspection['token'] = self::TOKEN;
        $config['inspection'] = $inspection;

        $container->instance(TaxonomyConfig::class, TaxonomyConfig::fromArray($config, $root));
        $container->forgetInstance(InspectionGate::class);

        return $fixture;
    }

    private static function get(ImportedFixture $fixture, string $path): TestResponse
    {
        // The front controller hands the query string to the request as parameters; the test builder does
        // not, so the query part is parsed here the way a request would carry it.
        $parsed = [];
        parse_str((string) parse_url($path, PHP_URL_QUERY), $parsed);
        $query = [];

        foreach ($parsed as $key => $value) {
            $query[(string) $key] = $value;
        }

        $request = TestRequestBuilder::get($path)
            ->withQuery($query)
            ->withHeader('Authorization', 'Basic ' . base64_encode('inspector:' . self::TOKEN))
            ->build();

        return new TestResponse($fixture->kernel->handle($request));
    }
}
