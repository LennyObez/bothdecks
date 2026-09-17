<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Contracts;

use BothDecks\Taxonomy\Domain\BubbleNeighbour;

/**
 * The occupations near a given one, with directed weights.
 *
 * A bubble is computed offline, versioned and stored; this reads it. The discovery module widens a
 * candidate's deck with it, and the candidate sees the same list in their preferences, in plain job titles,
 * and may add to it or remove from it. A widening that is never shown would break the predictability the
 * product promises.
 */
interface OccupationBubbles
{
    /**
     * Neighbours of the occupation, heaviest first. Empty when the occupation has no bubble yet.
     *
     * @return list<BubbleNeighbour>
     */
    public function neighboursOf(int $occupationConceptId, string $locale): array;
}
