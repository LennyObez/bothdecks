<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy;

use BothDecks\Shared\Config\ApplicationIdentity;
use BothDecks\Shared\Module\ModuleInterface;
use BothDecks\Taxonomy\Contracts\OccupationBubbles;
use BothDecks\Taxonomy\Contracts\TaxonomyResolver;
use BothDecks\Taxonomy\Internal\Bubbles\BubbleComputer;
use BothDecks\Taxonomy\Internal\Bubbles\StoredBubbles;
use BothDecks\Taxonomy\Internal\Catalogue\ConceptFinder;
use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Console\BubbleOverrideCommand;
use BothDecks\Taxonomy\Internal\Console\BubblesCommand;
use BothDecks\Taxonomy\Internal\Console\EmbedCommand;
use BothDecks\Taxonomy\Internal\Console\EvaluateCommand;
use BothDecks\Taxonomy\Internal\Console\ImportCommand;
use BothDecks\Taxonomy\Internal\Console\ReportCommand;
use BothDecks\Taxonomy\Internal\Console\ResolveCommand;
use BothDecks\Taxonomy\Internal\Console\SnapshotCommand;
use BothDecks\Taxonomy\Internal\Embedding\AiClientEmbeddingProvider;
use BothDecks\Taxonomy\Internal\Embedding\ConceptEmbedder;
use BothDecks\Taxonomy\Internal\Embedding\EmbeddingProvider;
use BothDecks\Taxonomy\Internal\Esco\EscoApi;
use BothDecks\Taxonomy\Internal\Http\InspectionController;
use BothDecks\Taxonomy\Internal\Http\InspectionGate;
use BothDecks\Taxonomy\Internal\Ingestion\EscoImporter;
use BothDecks\Taxonomy\Internal\Report\ResolutionEvaluation;
use BothDecks\Taxonomy\Internal\Report\VersionReport;
use BothDecks\Taxonomy\Internal\Resolution\CascadeResolver;
use BothDecks\Taxonomy\Internal\Versioning\CurrentVersion;
use Pulsar\AI\Provider\OllamaProvider;
use Pulsar\AI\Provider\OpenAiProvider;
use Pulsar\Container\ContainerInterface;
use Pulsar\Http\Method;
use Pulsar\Routing\Route;
use Pulsar\Routing\Router;

/**
 * Occupation and skill concepts, their labels per language, their relations, versions, bubbles and the
 * resolution of free text to a code.
 *
 * The public surface is what the contracts and domain namespaces hold. Everything else is wired here and
 * reached through them.
 */
final class TaxonomyModule implements ModuleInterface
{
    public static function register(ContainerInterface $container): void
    {
        $container->singleton(TaxonomyConfig::class, static function (): TaxonomyConfig {
            $root = \dirname(__DIR__, 2);
            $config = require $root . '/config/taxonomy.php';

            if (!\is_array($config)) {
                throw new \LogicException('config/taxonomy.php must return an array.');
            }

            return TaxonomyConfig::fromArray($config, $root);
        });

        $container->singleton(EscoApi::class, static fn(ContainerInterface $c): EscoApi => new EscoApi(
            $c->get(TaxonomyConfig::class),
            self::userAgent($c->get(ApplicationIdentity::class)),
        ));

        // The module's services take the framework's default connection, which it binds during boot and
        // only when a database is configured; a service resolved without one fails on that binding, by name.
        $container->singleton(EscoImporter::class, EscoImporter::class);
        $container->singleton(CurrentVersion::class, CurrentVersion::class);
        $container->singleton(ConceptEmbedder::class, ConceptEmbedder::class);
        $container->singleton(ConceptFinder::class, ConceptFinder::class);
        $container->singleton(BubbleComputer::class, BubbleComputer::class);
        $container->singleton(OccupationBubbles::class, StoredBubbles::class);
        $container->singleton(TaxonomyResolver::class, CascadeResolver::class);
        $container->singleton(VersionReport::class, VersionReport::class);
        $container->singleton(ResolutionEvaluation::class, ResolutionEvaluation::class);
        $container->singleton(InspectionGate::class, InspectionGate::class);
        $container->singleton(InspectionController::class, InspectionController::class);

        // One of the framework's model clients, chosen by configuration. The local runtime is reached over
        // plain HTTP on a private address, which the client allows only when told so; the compatible
        // provider is reached over HTTPS with a key that lives in the environment and nowhere else.
        $container->singleton(EmbeddingProvider::class, static function (ContainerInterface $c): EmbeddingProvider {
            $config = $c->get(TaxonomyConfig::class);

            $client = match ($config->embeddingProvider) {
                'ollama' => new OllamaProvider(model: $config->embeddingModel, baseUrl: $config->embeddingUrl, allowLocalhost: true),
                'openai-compatible' => new OpenAiProvider(
                    apiKey: $config->embeddingApiKey,
                    model: $config->embeddingModel,
                    baseUrl: $config->embeddingUrl,
                ),
                default => throw new \LogicException('The configuration accepts two embedding providers; got ' . $config->embeddingProvider),
            };

            return new AiClientEmbeddingProvider($client, $config->embeddingModel, $config->embeddingDimensions, $config->fetchTimeoutSeconds);
        });
    }

    /**
     * How the product names itself to the source, as its terms of use ask: the slug, then the site in
     * brackets so that whoever reads the source's logs can find out who is asking.
     *
     * @return non-empty-string
     */
    public static function userAgent(ApplicationIdentity $identity): string
    {
        return $identity->slug() . ' (' . $identity->url() . ')';
    }

    public static function commands(): array
    {
        return [
            SnapshotCommand::class,
            ImportCommand::class,
            EmbedCommand::class,
            BubblesCommand::class,
            BubbleOverrideCommand::class,
            ResolveCommand::class,
            ReportCommand::class,
            EvaluateCommand::class,
        ];
    }

    /**
     * The inspection screen, every route behind the gate. The gate answers 404 while no token is
     * configured, so registering the routes unconditionally exposes no data.
     */
    public static function routes(Router $router): void
    {
        $pages = [
            ['/console/taxonomy', 'overview', 'console.taxonomy'],
            ['/console/taxonomy/occupations', 'occupations', 'console.taxonomy.occupations'],
            ['/console/taxonomy/occupations/{id}', 'occupation', 'console.taxonomy.occupation'],
            ['/console/taxonomy/resolve', 'resolve', 'console.taxonomy.resolve'],
        ];

        foreach ($pages as [$path, $action, $name]) {
            $router->add(new Route(
                methods: [Method::GET],
                path: $path,
                handler: [InspectionController::class, $action],
                name: $name,
                middleware: [InspectionGate::class],
                constraints: str_contains($path, '{id}') ? ['id' => '[0-9]+'] : [],
            ));
        }
    }
}
