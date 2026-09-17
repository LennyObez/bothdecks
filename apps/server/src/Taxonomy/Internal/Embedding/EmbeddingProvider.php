<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Embedding;

/**
 * Turns texts into vectors of a fixed dimension.
 *
 * The module's own port, so the model behind it can change without the callers changing: a local runtime
 * on a workstation, a provider under European law in production.
 *
 * @see docs/adr/0003-eu-only-model-inference.md
 */
interface EmbeddingProvider
{
    /**
     * One vector per text, in the same order. A provider that answers with fewer vectors, or with a vector
     * of another dimension, is refused rather than stored.
     *
     * @param list<string> $texts
     *
     * @return list<list<float>>
     *
     * @throws EmbeddingException
     */
    public function embed(array $texts): array;

    public function model(): string;

    public function dimensions(): int;
}
