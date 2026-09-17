<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Embedding;

/**
 * The embedding provider did not answer, or answered with something other than one vector of the expected
 * dimension per text.
 */
final class EmbeddingException extends \RuntimeException {}
