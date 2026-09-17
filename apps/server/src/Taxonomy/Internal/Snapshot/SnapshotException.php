<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Snapshot;

/**
 * A snapshot is missing, incomplete, or does not match its manifest.
 */
final class SnapshotException extends \RuntimeException {}
