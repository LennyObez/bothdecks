<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Configuration;

/**
 * `config/taxonomy.php`, typed and validated once.
 *
 * Every key is checked here rather than where it is used, so a misspelt key or an out-of-range value stops
 * the boot with the key named, instead of surfacing as a snapshot of the wrong release or a bubble computed
 * with weights that do not sum to one. An unknown key is refused for the same reason: a setting nobody reads
 * looks applied.
 */
final readonly class TaxonomyConfig
{
    private const array KNOWN_KEYS = ['source', 'snapshot_path', 'fetch', 'embedding', 'bubbles', 'resolution', 'inspection'];

    /**
     * @param positive-int $fetchPageSize
     * @param positive-int $fetchBatchSize
     * @param positive-int $fetchConcurrency
     * @param positive-int $fetchTimeoutSeconds
     * @param positive-int $fetchAttempts
     * @param positive-int $embeddingDimensions
     * @param positive-int $embeddingBatchSize
     * @param array<string, float> $bubbleWeights Keyed by signal name; sums to one.
     * @param positive-int $bubbleMaximumNeighbours
     * @param positive-int $candidatesWhenAsking
     */
    private function __construct(
        public string $sourceName,
        public string $sourceVersion,
        public string $sourceApi,
        public string $sourceLicence,
        public string $sourceAttribution,
        public string $snapshotPath,
        public int $fetchPageSize,
        public int $fetchBatchSize,
        public int $fetchConcurrency,
        public int $fetchTimeoutSeconds,
        public int $fetchAttempts,
        public string $embeddingProvider,
        public string $embeddingUrl,
        public string $embeddingApiKey,
        public string $embeddingModel,
        public int $embeddingDimensions,
        public int $embeddingBatchSize,
        public array $bubbleWeights,
        public float $bubbleMinimumScore,
        public int $bubbleMaximumNeighbours,
        public float $approximateMinimumSimilarity,
        public float $approximateTrustedSimilarity,
        public float $vectorMinimumSimilarity,
        public int $candidatesWhenAsking,
        public string $inspectionToken,
    ) {}

    /**
     * @param array<mixed, mixed> $config
     */
    public static function fromArray(array $config, string $projectRoot): self
    {
        $unknown = array_diff(array_map(strval(...), array_keys($config)), self::KNOWN_KEYS);

        if ($unknown !== []) {
            throw new \InvalidArgumentException('config/taxonomy.php has keys nothing reads: ' . implode(', ', $unknown));
        }

        $source = self::section($config, 'source');
        $fetch = self::section($config, 'fetch');
        $embedding = self::section($config, 'embedding');
        $bubbles = self::section($config, 'bubbles');
        $resolution = self::section($config, 'resolution');
        $inspection = self::section($config, 'inspection');
        $token = $inspection['token'] ?? null;

        if (!\is_string($token)) {
            throw new \InvalidArgumentException('config/taxonomy.php: inspection.token must be a string, empty to keep the screen closed.');
        }

        if ($token !== '' && \strlen($token) < 24) {
            throw new \InvalidArgumentException('config/taxonomy.php: inspection.token is too short to protect anything; use at least 24 characters.');
        }

        $approximateMinimum = self::unitFloat($resolution, 'approximate_minimum_similarity', 'resolution');
        $approximateTrusted = self::unitFloat($resolution, 'approximate_trusted_similarity', 'resolution');

        if ($approximateTrusted < $approximateMinimum) {
            throw new \InvalidArgumentException('config/taxonomy.php: resolution.approximate_trusted_similarity is at least the minimum similarity.');
        }

        $weights = self::section($bubbles, 'weights');
        $bubbleWeights = [];
        $sum = 0.0;

        foreach (['hierarchy', 'skills', 'vector', 'mobility'] as $signal) {
            $weight = self::float($weights, $signal, 'bubbles.weights');

            if ($weight < 0.0 || $weight > 1.0) {
                throw new \InvalidArgumentException('config/taxonomy.php: bubbles.weights.' . $signal . ' must be between 0 and 1.');
            }

            $bubbleWeights[$signal] = $weight;
            $sum += $weight;
        }

        if (abs($sum - 1.0) > 0.0001) {
            throw new \InvalidArgumentException('config/taxonomy.php: bubbles.weights must sum to 1; they sum to ' . $sum);
        }

        $snapshotPath = self::string($config, 'snapshot_path', '');

        if (str_starts_with($snapshotPath, '/')) {
            throw new \InvalidArgumentException('config/taxonomy.php: snapshot_path is relative to the project root.');
        }

        $version = self::string($source, 'version', 'source');

        if (preg_match('/^v\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new \InvalidArgumentException('config/taxonomy.php: source.version must look like v1.2.1; got ' . $version);
        }

        $api = self::string($source, 'api', 'source');
        $loopback = str_starts_with($api, 'http://127.0.0.1:') || str_starts_with($api, 'http://localhost:');

        // Plain HTTP only to the machine itself, where a stand-in for the source runs during a test.
        if ((!str_starts_with($api, 'https://') && !$loopback) || str_ends_with($api, '/')) {
            throw new \InvalidArgumentException('config/taxonomy.php: source.api must be an https URL without a trailing slash.');
        }

        $provider = self::string($embedding, 'provider', 'embedding');

        if (!\in_array($provider, ['ollama', 'openai-compatible'], true)) {
            throw new \InvalidArgumentException('config/taxonomy.php: embedding.provider must be "ollama" or "openai-compatible".');
        }

        $apiKey = $embedding['api_key'] ?? '';

        if (!\is_string($apiKey)) {
            throw new \InvalidArgumentException('config/taxonomy.php: embedding.api_key must be a string, empty for a provider that needs none.');
        }

        if ($provider === 'openai-compatible' && $apiKey === '') {
            throw new \InvalidArgumentException('config/taxonomy.php: the compatible embedding provider needs embedding.api_key; set TAXONOMY_EMBEDDING_API_KEY.');
        }

        return new self(
            sourceName: self::string($source, 'name', 'source'),
            sourceVersion: $version,
            sourceApi: $api,
            sourceLicence: self::string($source, 'licence', 'source'),
            sourceAttribution: self::string($source, 'attribution', 'source'),
            snapshotPath: rtrim($projectRoot, '/') . '/' . $snapshotPath,
            fetchPageSize: self::positiveInt($fetch, 'page_size', 'fetch'),
            fetchBatchSize: self::positiveInt($fetch, 'batch_size', 'fetch'),
            fetchConcurrency: self::positiveInt($fetch, 'concurrency', 'fetch'),
            fetchTimeoutSeconds: self::positiveInt($fetch, 'timeout_seconds', 'fetch'),
            fetchAttempts: self::positiveInt($fetch, 'attempts', 'fetch'),
            embeddingProvider: $provider,
            embeddingUrl: self::string($embedding, 'url', 'embedding'),
            embeddingApiKey: $apiKey,
            embeddingModel: self::string($embedding, 'model', 'embedding'),
            embeddingDimensions: self::positiveInt($embedding, 'dimensions', 'embedding'),
            embeddingBatchSize: self::positiveInt($embedding, 'batch_size', 'embedding'),
            bubbleWeights: $bubbleWeights,
            bubbleMinimumScore: self::unitFloat($bubbles, 'minimum_score', 'bubbles'),
            bubbleMaximumNeighbours: self::positiveInt($bubbles, 'maximum_neighbours', 'bubbles'),
            approximateMinimumSimilarity: $approximateMinimum,
            approximateTrustedSimilarity: $approximateTrusted,
            vectorMinimumSimilarity: self::unitFloat($resolution, 'vector_minimum_similarity', 'resolution'),
            candidatesWhenAsking: self::positiveInt($resolution, 'candidates_when_asking', 'resolution'),
            inspectionToken: $token,
        );
    }

    /**
     * Where the snapshot of the configured source and release lives.
     */
    public function snapshotDirectory(): string
    {
        return $this->snapshotPath . '/' . $this->sourceName . '/' . $this->sourceVersion;
    }

    /**
     * @param array<mixed, mixed> $config
     *
     * @return array<mixed, mixed>
     */
    private static function section(array $config, string $key): array
    {
        $value = $config[$key] ?? null;

        if (!\is_array($value)) {
            throw new \InvalidArgumentException('config/taxonomy.php: "' . $key . '" must be an array.');
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $section
     */
    private static function string(array $section, string $key, string $path): string
    {
        $value = $section[$key] ?? null;

        if (!\is_string($value) || $value === '') {
            throw new \InvalidArgumentException('config/taxonomy.php: ' . self::dotted($path, $key) . ' must be a non-empty string.');
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $section
     *
     * @return positive-int
     */
    private static function positiveInt(array $section, string $key, string $path): int
    {
        $value = $section[$key] ?? null;

        if (!\is_int($value) || $value < 1) {
            throw new \InvalidArgumentException('config/taxonomy.php: ' . self::dotted($path, $key) . ' must be a positive integer.');
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $section
     */
    private static function float(array $section, string $key, string $path): float
    {
        $value = $section[$key] ?? null;

        if (!\is_float($value) && !\is_int($value)) {
            throw new \InvalidArgumentException('config/taxonomy.php: ' . self::dotted($path, $key) . ' must be a number.');
        }

        return (float) $value;
    }

    /**
     * @param array<mixed, mixed> $section
     */
    private static function unitFloat(array $section, string $key, string $path): float
    {
        $value = self::float($section, $key, $path);

        if ($value < 0.0 || $value > 1.0) {
            throw new \InvalidArgumentException('config/taxonomy.php: ' . self::dotted($path, $key) . ' must be between 0 and 1.');
        }

        return $value;
    }

    private static function dotted(string $path, string $key): string
    {
        return $path === '' ? $key : $path . '.' . $key;
    }
}
