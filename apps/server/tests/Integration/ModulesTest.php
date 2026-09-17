<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration;

use BothDecks\Shared\Config\ApplicationIdentity;
use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Shared\Module\ModuleRegistry;
use BothDecks\Shared\Module\SharedModule;
use BothDecks\Taxonomy\Contracts\OccupationBubbles;
use BothDecks\Taxonomy\Contracts\TaxonomyResolver;
use BothDecks\Taxonomy\Internal\Bubbles\BubbleComputer;
use BothDecks\Taxonomy\Internal\Catalogue\ConceptFinder;
use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Embedding\AiClientEmbeddingProvider;
use BothDecks\Taxonomy\Internal\Embedding\ConceptEmbedder;
use BothDecks\Taxonomy\Internal\Embedding\EmbeddingProvider;
use BothDecks\Taxonomy\Internal\Esco\EscoApi;
use BothDecks\Taxonomy\Internal\Http\InspectionController;
use BothDecks\Taxonomy\Internal\Http\InspectionGate;
use BothDecks\Taxonomy\Internal\Ingestion\EscoImporter;
use BothDecks\Taxonomy\Internal\Report\ResolutionEvaluation;
use BothDecks\Taxonomy\Internal\Report\VersionReport;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use BothDecks\Taxonomy\TaxonomyModule;
use BothDecks\Tests\Integration\Support\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Pulsar\Console\CommandInterface;
use Pulsar\Http\Method;
use Pulsar\Routing\Route;
use Pulsar\Routing\Router;

/**
 * The modules wire what they say they wire: every service resolves, every command is one, every route is
 * registered under its name.
 */
#[CoversClass(ModuleRegistry::class)]
#[CoversClass(SharedModule::class)]
#[CoversClass(TaxonomyModule::class)]
final class ModulesTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function services(): iterable
    {
        foreach ([
            ClockInterface::class, ProductLocales::class,
            TaxonomyConfig::class, EscoApi::class, EscoImporter::class, CurrentVersion::class, ConceptEmbedder::class,
            ConceptFinder::class, BubbleComputer::class, OccupationBubbles::class, TaxonomyResolver::class,
            VersionReport::class, ResolutionEvaluation::class, InspectionGate::class, InspectionController::class,
            EmbeddingProvider::class,
        ] as $service) {
            yield $service => [$service];
        }
    }

    /**
     * @param class-string $service
     */
    #[DataProvider('services')]
    public function testEveryServiceAModuleRegistersResolves(string $service): void
    {
        // Arrange
        $container = Application::bootedKernel()->container();

        // Act
        $resolved = $container->get($service);

        // Assert: one instance per process, so a cached version identifier or an open client is shared.
        self::assertInstanceOf($service, $resolved);
        self::assertSame($resolved, $container->get($service));
    }

    public function testTheLocalEmbeddingProviderIsBuiltFromTheConfiguration(): void
    {
        // Arrange
        $container = Application::bootedKernel()->container();

        // Act
        $provider = $container->get(EmbeddingProvider::class);

        // Assert
        self::assertInstanceOf(AiClientEmbeddingProvider::class, $provider);
        self::assertSame($container->get(TaxonomyConfig::class)->embeddingModel, $provider->model());
        self::assertSame($container->get(TaxonomyConfig::class)->embeddingDimensions, $provider->dimensions());
    }

    public function testTheCompatibleEmbeddingProviderIsBuiltWhenConfigured(): void
    {
        // Arrange
        $container = Application::bootedKernel()->container();
        $root = \dirname(__DIR__, 2);
        $config = require $root . '/config/taxonomy.php';
        self::assertIsArray($config);
        $embedding = $config['embedding'] ?? null;
        self::assertIsArray($embedding);
        $embedding['provider'] = 'openai-compatible';
        $embedding['url'] = 'https://embeddings.example/v1';
        $embedding['api_key'] = 'a-key';
        $config['embedding'] = $embedding;
        $container->instance(TaxonomyConfig::class, TaxonomyConfig::fromArray($config, $root));
        $container->forgetInstance(EmbeddingProvider::class);

        // Act
        $provider = $container->get(EmbeddingProvider::class);

        // Assert
        self::assertInstanceOf(AiClientEmbeddingProvider::class, $provider);
        self::assertSame('bge-m3', $provider->model());
    }

    public function testEveryCommandTheModulesListIsACommandWithAName(): void
    {
        // Arrange
        $container = Application::bootedKernel()->container();

        // Act
        $commands = ModuleRegistry::commands($container);

        // Assert
        self::assertCount(8, $commands);
        $names = array_map(static fn(CommandInterface $c): string => $c->name, $commands);
        self::assertSame([
            'taxonomy:snapshot', 'taxonomy:import', 'taxonomy:embed', 'taxonomy:bubbles',
            'taxonomy:bubble:override', 'taxonomy:resolve', 'taxonomy:report', 'taxonomy:evaluate',
        ], $names);
    }

    public function testEveryRouteIsRegisteredUnderItsName(): void
    {
        // Arrange
        $router = new Router();

        // Act
        ModuleRegistry::routes($router);

        // Assert
        self::assertSame(5, $router->count());
        self::assertSame(
            ['health', 'console.taxonomy', 'console.taxonomy.occupations', 'console.taxonomy.occupation', 'console.taxonomy.resolve'],
            array_map(static fn(Route $route): ?string => $route->name, $router->routes()),
        );
        $occupation = $router->getByName('console.taxonomy.occupation');
        $resolve = $router->getByName('console.taxonomy.resolve');
        $health = $router->getByName('health');
        self::assertInstanceOf(Route::class, $occupation);
        self::assertInstanceOf(Route::class, $resolve);
        self::assertInstanceOf(Route::class, $health);
        self::assertSame(['id' => '[0-9]+'], $occupation->constraints);
        self::assertSame([InspectionGate::class], $resolve->middleware);
        self::assertSame([], $health->middleware);
        // A page registered for GET answers HEAD as well; the framework adds it.
        self::assertSame([Method::GET, Method::HEAD], $resolve->methods);
        self::assertSame([InspectionController::class, 'resolve'], $resolve->handler);
    }

    public function testTheProductNamesItselfToTheSourceBySlugAndSite(): void
    {
        // Arrange
        $identity = new ApplicationIdentity('Both Decks', 'bothdecks', 'https://example.test', 'testing');

        // Act
        $agent = TaxonomyModule::userAgent($identity);

        // Assert
        self::assertSame('bothdecks (https://example.test)', $agent);
    }

    public function testTheRegistryListsTheSharedModuleFirst(): void
    {
        // Act
        $modules = ModuleRegistry::modules();

        // Assert
        self::assertSame([SharedModule::class, TaxonomyModule::class], $modules);
    }
}
