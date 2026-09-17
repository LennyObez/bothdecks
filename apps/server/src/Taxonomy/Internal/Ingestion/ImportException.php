<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Ingestion;

/**
 * A snapshot cannot be imported as it is: a record lacks what the schema requires, a relation points at a
 * concept the snapshot does not hold, or the snapshot was imported already.
 */
final class ImportException extends \RuntimeException {}
