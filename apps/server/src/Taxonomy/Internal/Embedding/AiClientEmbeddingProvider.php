<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Embedding;

use Pulsar\AI\AiClientInterface;
use Pulsar\AI\Config\AiRequestOptions;

/**
 * The embedding port, over one of the framework's model clients.
 *
 * The framework's clients answer an unreachable server or a refused request with an empty result rather
 * than an error. That is the right behaviour for a chat reply that can degrade, and the wrong one for a
 * vector that will be stored and compared for months: here, anything but one vector of the configured
 * dimension per text is a failure with the provider named.
 */
final readonly class AiClientEmbeddingProvider implements EmbeddingProvider
{
    /**
     * @param positive-int $dimensions
     */
    public function __construct(
        private AiClientInterface $client,
        private string $model,
        private int $dimensions,
        private int $timeoutSeconds,
    ) {}

    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        foreach ($texts as $index => $text) {
            if (trim($text) === '') {
                throw new EmbeddingException(\sprintf('Text %d is empty; an empty text has no meaning to embed.', $index));
            }
        }

        $result = $this->client->embed($texts, new AiRequestOptions(model: $this->model, timeoutSeconds: $this->timeoutSeconds));

        if ($result->count() !== \count($texts)) {
            throw new EmbeddingException(\sprintf(
                'The %s provider answered %d vector(s) for %d text(s) with model %s; the server may be unreachable or the model missing.',
                $this->client->providerName(),
                $result->count(),
                \count($texts),
                $this->model,
            ));
        }

        $vectors = [];

        foreach ($result->embeddings as $index => $embedding) {
            if ($embedding->dimensions() !== $this->dimensions) {
                throw new EmbeddingException(\sprintf(
                    'Model %s answered a vector of %d dimensions; the configuration and the table expect %d.',
                    $this->model,
                    $embedding->dimensions(),
                    $this->dimensions,
                ));
            }

            $vectors[$index] = $embedding->values;
        }

        return array_values($vectors);
    }

    public function model(): string
    {
        return $this->model;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
