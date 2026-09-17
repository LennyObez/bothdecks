<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Domain;

/**
 * The status of a label on a concept, in one language.
 *
 * Every concept has exactly one preferred label per language it is translated into; it is what the interface
 * renders. Alternative labels are the other names the same thing goes by, and they are what most free-text
 * queries actually match. Hidden labels are matched but never rendered: misspellings and forms the source
 * keeps for retrieval only.
 */
enum LabelKind: string
{
    case Preferred = 'preferred';
    case Alternative = 'alternative';
    case Hidden = 'hidden';
}
