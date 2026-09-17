<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Domain;

/**
 * One concept a query may resolve to, with how well it matched.
 *
 * The code is carried alongside the identifier because it is what the interface shows next to the label: the
 * product is transparent about what the machine understood, and a code the person can look up is how.
 */
final readonly class ResolutionCandidate
{
    /**
     * @param int $conceptId The concept's row identifier in the current taxonomy version.
     * @param string $uri The concept's stable identifier in the source, which survives a version change.
     * @param string|null $code The human-readable code, when the kind has one.
     * @param string $label The preferred label in the locale of the query.
     * @param float $score Between 0 and 1; 1 is an exact match. Comparable within one path only.
     */
    public function __construct(
        public ConceptKind $kind,
        public int $conceptId,
        public string $uri,
        public ?string $code,
        public string $label,
        public float $score,
    ) {
        if ($score < 0.0 || $score > 1.0) {
            throw new \InvalidArgumentException('A candidate score is between 0 and 1; got ' . $score);
        }
    }
}
