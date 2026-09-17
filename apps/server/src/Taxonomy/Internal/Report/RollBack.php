<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Report;

/**
 * Thrown on purpose at the end of a measuring transaction, so the connection rolls it back. Not an error.
 */
final class RollBack extends \RuntimeException {}
