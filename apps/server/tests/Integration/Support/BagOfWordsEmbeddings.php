<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Support;

use BothDecks\Taxonomy\Internal\Embedding\EmbeddingProvider;

/**
 * A deterministic stand-in for the embedding model: each word lands on one of the dimensions by hash, so
 * two texts sharing words have similar vectors and two texts sharing none are orthogonal.
 *
 * It proves the plumbing, from the text a concept is embedded from to the vector the cascade compares,
 * without a model server in the test run. What the real model is worth is measured by the precision
 * report, on the real model, and never by this class.
 */
final readonly class BagOfWordsEmbeddings implements EmbeddingProvider
{
    private const int DIMENSIONS = 1024;

    public function embed(array $texts): array
    {
        return array_map(self::vector(...), $texts);
    }

    public function model(): string
    {
        return 'bag-of-words-test';
    }

    public function dimensions(): int
    {
        return self::DIMENSIONS;
    }

    /**
     * @return list<float>
     */
    private static function vector(string $text): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $slot = (crc32($word) & 0x7fffffff) % self::DIMENSIONS;
            $vector[$slot] = ($vector[$slot] ?? 0.0) + 1.0;
        }

        $norm = sqrt(array_sum(array_map(static fn(float $v): float => $v * $v, $vector)));

        if ($norm === 0.0) {
            $vector[0] = 1.0;

            return array_values($vector);
        }

        return array_values(array_map(static fn(float $v): float => $v / $norm, $vector));
    }
}
