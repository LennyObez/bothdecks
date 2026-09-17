<?php

declare(strict_types=1);

namespace BothDecks\Tests\Unit\Taxonomy\Configuration;

use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxonomyConfig::class)]
final class TaxonomyConfigTest extends TestCase
{
    public function testTheShippedConfigurationFileLoads(): void
    {
        // The file under config/ is what production reads; if it stopped satisfying the loader, the module
        // would fail at boot and every command with it.

        // Arrange
        $root = \dirname(__DIR__, 4);
        $config = require $root . '/config/taxonomy.php';

        self::assertIsArray($config);

        // Act
        $typed = TaxonomyConfig::fromArray($config, $root);

        // Assert
        self::assertSame('esco', $typed->sourceName);
        self::assertSame($root . '/var/taxonomy/esco/' . $typed->sourceVersion, $typed->snapshotDirectory());
    }

    public function testEveryValueIsReadFromItsOwnKey(): void
    {
        // Arrange
        $config = self::valid();

        // Act
        $typed = TaxonomyConfig::fromArray($config, '/srv/app');

        // Assert
        self::assertSame('v9.8.7', $typed->sourceVersion);
        self::assertSame('https://source.example', $typed->sourceApi);
        self::assertSame('LIC-1', $typed->sourceLicence);
        self::assertSame('Attribution text', $typed->sourceAttribution);
        self::assertSame('/srv/app/var/snap', $typed->snapshotPath);
        self::assertSame(50, $typed->fetchPageSize);
        self::assertSame(7, $typed->fetchBatchSize);
        self::assertSame(3, $typed->fetchConcurrency);
        self::assertSame(45, $typed->fetchTimeoutSeconds);
        self::assertSame(2, $typed->fetchAttempts);
        self::assertSame('openai-compatible', $typed->embeddingProvider);
        self::assertSame('http://embed.example', $typed->embeddingUrl);
        self::assertSame('key-x', $typed->embeddingApiKey);
        self::assertSame('model-x', $typed->embeddingModel);
        self::assertSame(256, $typed->embeddingDimensions);
        self::assertSame(8, $typed->embeddingBatchSize);
        self::assertSame(['hierarchy' => 0.1, 'skills' => 0.2, 'vector' => 0.3, 'mobility' => 0.4], $typed->bubbleWeights);
        self::assertSame(0.5, $typed->bubbleMinimumScore);
        self::assertSame(9, $typed->bubbleMaximumNeighbours);
        self::assertSame(0.6, $typed->approximateMinimumSimilarity);
        self::assertSame(0.9, $typed->approximateTrustedSimilarity);
        self::assertSame(0.7, $typed->vectorMinimumSimilarity);
        self::assertSame(4, $typed->candidatesWhenAsking);
    }

    public function testTheInspectionTokenIsReadWhenLongEnough(): void
    {
        // Arrange
        $config = self::withValueAt(self::valid(), ['inspection', 'token'], 'a-token-of-twenty-four-c');

        // Act
        $typed = TaxonomyConfig::fromArray($config, '/srv/app');

        // Assert
        self::assertSame('a-token-of-twenty-four-c', $typed->inspectionToken);
    }

    /**
     * @return iterable<string, array{list<string>, mixed, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        // The whole message each time: a message is what a person reads at boot, and it names the key.
        yield 'unknown key' => [['extra'], 1, 'config/taxonomy.php has keys nothing reads: extra'];
        yield 'weights not summing to one' => [['bubbles', 'weights', 'skills'], 0.9, 'config/taxonomy.php: bubbles.weights must sum to 1; they sum to 1.7'];
        yield 'weight above one' => [['bubbles', 'weights', 'skills'], 1.2, 'config/taxonomy.php: bubbles.weights.skills must be between 0 and 1.'];
        yield 'weight below zero' => [['bubbles', 'weights', 'hierarchy'], -0.1, 'config/taxonomy.php: bubbles.weights.hierarchy must be between 0 and 1.'];
        yield 'weight that is not a number' => [['bubbles', 'weights', 'vector'], '0.3', 'config/taxonomy.php: bubbles.weights.vector must be a number.'];
        yield 'version without the v prefix' => [['source', 'version'], '1.2.1', 'config/taxonomy.php: source.version must look like v1.2.1; got 1.2.1'];
        yield 'version with a suffix' => [['source', 'version'], 'v1.2.1-rc', 'config/taxonomy.php: source.version must look like v1.2.1; got v1.2.1-rc'];
        yield 'version with a prefix' => [['source', 'version'], 'esco v1.2.1', 'config/taxonomy.php: source.version must look like v1.2.1; got esco v1.2.1'];
        yield 'api with a trailing slash' => [['source', 'api'], 'https://source.example/', 'config/taxonomy.php: source.api must be an https URL without a trailing slash.'];
        yield 'api over plain http to a remote host' => [['source', 'api'], 'http://source.example', 'config/taxonomy.php: source.api must be an https URL without a trailing slash.'];
        yield 'api over plain http to the machine, trailing slash' => [['source', 'api'], 'http://127.0.0.1:8080/', 'config/taxonomy.php: source.api must be an https URL without a trailing slash.'];
        yield 'empty source name' => [['source', 'name'], '', 'config/taxonomy.php: source.name must be a non-empty string.'];
        yield 'absolute snapshot path' => [['snapshot_path'], '/var/snap', 'config/taxonomy.php: snapshot_path is relative to the project root.'];
        yield 'zero batch size' => [['fetch', 'batch_size'], 0, 'config/taxonomy.php: fetch.batch_size must be a positive integer.'];
        yield 'batch size as a string' => [['fetch', 'batch_size'], '5', 'config/taxonomy.php: fetch.batch_size must be a positive integer.'];
        yield 'unknown embedding provider' => [['embedding', 'provider'], 'other', 'config/taxonomy.php: embedding.provider must be "ollama" or "openai-compatible".'];
        yield 'compatible provider without a key' => [['embedding', 'api_key'], '', 'config/taxonomy.php: the compatible embedding provider needs embedding.api_key; set TAXONOMY_EMBEDDING_API_KEY.'];
        yield 'api key that is not a string' => [['embedding', 'api_key'], 12, 'config/taxonomy.php: embedding.api_key must be a string, empty for a provider that needs none.'];
        yield 'similarity above one' => [['resolution', 'vector_minimum_similarity'], 1.5, 'config/taxonomy.php: resolution.vector_minimum_similarity must be between 0 and 1.'];
        yield 'similarity below zero' => [['resolution', 'approximate_minimum_similarity'], -0.5, 'config/taxonomy.php: resolution.approximate_minimum_similarity must be between 0 and 1.'];
        yield 'missing section' => [['resolution'], null, 'config/taxonomy.php: "resolution" must be an array.'];
        yield 'short inspection token' => [['inspection', 'token'], 'short', 'config/taxonomy.php: inspection.token is too short to protect anything; use at least 24 characters.'];
        yield 'inspection token of twenty-three characters' => [['inspection', 'token'], str_repeat('x', 23), 'config/taxonomy.php: inspection.token is too short to protect anything; use at least 24 characters.'];
        yield 'trusted similarity below the minimum' => [['resolution', 'approximate_trusted_similarity'], 0.5, 'config/taxonomy.php: resolution.approximate_trusted_similarity is at least the minimum similarity.'];
        yield 'inspection token that is not a string' => [['inspection', 'token'], 42, 'config/taxonomy.php: inspection.token must be a string, empty to keep the screen closed.'];
    }

    public function testATrustedSimilarityEqualToTheMinimumIsAccepted(): void
    {
        // Arrange
        $config = self::withValueAt(self::withValueAt(self::valid(), ['resolution', 'approximate_minimum_similarity'], 0.7), ['resolution', 'approximate_trusted_similarity'], 0.7);

        // Act
        $typed = TaxonomyConfig::fromArray($config, '/srv/app');

        // Assert
        self::assertSame(0.7, $typed->approximateTrustedSimilarity);
    }

    public function testWeightsSummingToOneWithinRoundingAreAccepted(): void
    {
        // Arrange
        $config = self::withValueAt(self::valid(), ['bubbles', 'weights'], ['hierarchy' => 0.1, 'skills' => 0.2, 'vector' => 0.3, 'mobility' => 0.40001]);

        // Act
        $typed = TaxonomyConfig::fromArray($config, '/srv/app');

        // Assert
        self::assertEqualsWithDelta(1.0, array_sum($typed->bubbleWeights), 0.001);
    }

    public function testPlainHttpToTheMachineItselfIsAcceptedForTheSource(): void
    {
        // Arrange
        $config = self::withValueAt(self::valid(), ['source', 'api'], 'http://127.0.0.1:8080');

        // Act
        $typed = TaxonomyConfig::fromArray($config, '/srv/app');

        // Assert
        self::assertSame('http://127.0.0.1:8080', $typed->sourceApi);
    }

    public function testTheProjectRootIsJoinedWithoutADoubleSlash(): void
    {
        // Act
        $typed = TaxonomyConfig::fromArray(self::valid(), '/srv/app/');

        // Assert
        self::assertSame('/srv/app/var/snap', $typed->snapshotPath);
        self::assertSame('/srv/app/var/snap/src/v9.8.7', $typed->snapshotDirectory());
    }

    public function testAnInspectionTokenOfTwentyFourCharactersIsAccepted(): void
    {
        // Act
        $typed = TaxonomyConfig::fromArray(self::withValueAt(self::valid(), ['inspection', 'token'], str_repeat('y', 24)), '/srv/app');

        // Assert
        self::assertSame(str_repeat('y', 24), $typed->inspectionToken);
    }

    public function testTheBoundariesOfEveryRangeAreInside(): void
    {
        // Zero and one are valid weights and similarities; one is a valid count. A check written with the
        // wrong comparison would refuse a configuration on its edge.

        // Arrange
        $config = self::valid();
        $config = self::withValueAt($config, ['bubbles', 'weights'], ['hierarchy' => 0.0, 'skills' => 1.0, 'vector' => 0.0, 'mobility' => 0.0]);
        $config = self::withValueAt($config, ['resolution', 'approximate_minimum_similarity'], 0.0);
        $config = self::withValueAt($config, ['resolution', 'approximate_trusted_similarity'], 1.0);
        $config = self::withValueAt($config, ['resolution', 'vector_minimum_similarity'], 1.0);
        $config = self::withValueAt($config, ['bubbles', 'minimum_score'], 0.0);
        $config = self::withValueAt($config, ['fetch', 'attempts'], 1);

        // Act
        $typed = TaxonomyConfig::fromArray($config, '/srv/app');

        // Assert
        self::assertSame(['hierarchy' => 0.0, 'skills' => 1.0, 'vector' => 0.0, 'mobility' => 0.0], $typed->bubbleWeights);
        self::assertSame(0.0, $typed->approximateMinimumSimilarity);
        self::assertSame(1.0, $typed->approximateTrustedSimilarity);
        self::assertSame(1.0, $typed->vectorMinimumSimilarity);
        self::assertSame(0.0, $typed->bubbleMinimumScore);
        self::assertSame(1, $typed->fetchAttempts);
    }

    public function testWeightsOffByMoreThanTheToleranceAreRefusedEitherWay(): void
    {
        // Arrange
        $short = self::withValueAt(self::valid(), ['bubbles', 'weights'], ['hierarchy' => 0.1, 'skills' => 0.2, 'vector' => 0.3, 'mobility' => 0.399]);

        // Act
        $failure = self::capture(static fn() => TaxonomyConfig::fromArray($short, '/srv/app'));

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $failure);
        self::assertStringStartsWith('config/taxonomy.php: bubbles.weights must sum to 1; they sum to 0.999', $failure->getMessage());
    }

    public function testTheLocalProviderNeedsNoKey(): void
    {
        // Act
        $typed = TaxonomyConfig::fromArray(self::withValueAt(self::withValueAt(self::valid(), ['embedding', 'provider'], 'ollama'), ['embedding', 'api_key'], ''), '/srv/app');

        // Assert
        self::assertSame('ollama', $typed->embeddingProvider);
        self::assertSame('', $typed->embeddingApiKey);
    }

    /**
     * @param list<string> $path The key path to overwrite in an otherwise valid configuration.
     */
    #[DataProvider('invalidConfigurations')]
    public function testAnInvalidConfigurationIsRefusedWithTheKeyNamed(array $path, mixed $value, string $expectedMessage): void
    {
        // Arrange
        $config = self::withValueAt(self::valid(), $path, $value);

        // Act
        $failure = self::capture(static fn() => TaxonomyConfig::fromArray($config, '/srv/app'));

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $failure);
        self::assertStringContainsString($expectedMessage, $failure->getMessage());
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $path
     *
     * @return array<string, mixed>
     */
    private static function withValueAt(array $config, array $path, mixed $value): array
    {
        $key = array_shift($path);

        self::assertIsString($key);

        if ($path === []) {
            $config[$key] = $value;

            return $config;
        }

        $child = $config[$key] ?? [];

        self::assertIsArray($child);

        /** @var array<string, mixed> $child */
        $config[$key] = self::withValueAt($child, $path, $value);

        return $config;
    }

    /**
     * @param callable(): mixed $build
     */
    private static function capture(callable $build): ?\Throwable
    {
        try {
            $build();
        } catch (\Throwable $caught) {
            return $caught;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return [
            'source' => [
                'name' => 'src',
                'version' => 'v9.8.7',
                'api' => 'https://source.example',
                'licence' => 'LIC-1',
                'attribution' => 'Attribution text',
            ],
            'snapshot_path' => 'var/snap',
            'fetch' => ['page_size' => 50, 'batch_size' => 7, 'concurrency' => 3, 'timeout_seconds' => 45, 'attempts' => 2],
            'embedding' => [
                'provider' => 'openai-compatible',
                'url' => 'http://embed.example',
                'api_key' => 'key-x',
                'model' => 'model-x',
                'dimensions' => 256,
                'batch_size' => 8,
            ],
            'bubbles' => [
                'weights' => ['hierarchy' => 0.1, 'skills' => 0.2, 'vector' => 0.3, 'mobility' => 0.4],
                'minimum_score' => 0.5,
                'maximum_neighbours' => 9,
            ],
            'resolution' => [
                'approximate_minimum_similarity' => 0.6,
                'approximate_trusted_similarity' => 0.9,
                'vector_minimum_similarity' => 0.7,
                'candidates_when_asking' => 4,
            ],
            'inspection' => ['token' => ''],
        ];
    }
}
