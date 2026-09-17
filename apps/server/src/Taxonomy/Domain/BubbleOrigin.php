<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Domain;

/**
 * Whether a bubble member was computed or decided by a person.
 *
 * No automatic computation survives contact with the real market without corrections, so a person may add a
 * neighbour the signals missed or remove one they got wrong. A correction is recorded against the source
 * identifiers, so it survives a recomputation and a version change, and the member it produces says so.
 */
enum BubbleOrigin: string
{
    case Computed = 'computed';
    case Added = 'added';
}
