<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Domain;

/**
 * One occupation in another occupation's bubble, with the weight of the proximity and where it came from.
 *
 * The weight is directed: it says how acceptable an offer for the neighbour is to someone whose occupation
 * is the bubble's centre, and the reverse pair may carry another weight or not exist at all. The four signal
 * scores are kept beside the combined weight so the inspection screen can show why a neighbour is there,
 * and an origin says whether a person put it there.
 */
final readonly class BubbleNeighbour
{
    public function __construct(
        public int $conceptId,
        public string $uri,
        public ?string $code,
        public string $label,
        public float $weight,
        public float $hierarchy,
        public float $skills,
        public float $vector,
        public float $mobility,
        public BubbleOrigin $origin,
    ) {}
}
