<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Domain;

/**
 * Which step of the cascade answered a query.
 *
 * The cascade goes from the deterministic to the probabilistic and never the other way: an exact match on a
 * label wins over anything a similarity measure says. Every resolution records its path, because a code
 * that was assigned by a vector search is a code a person may want to check, and one assigned by an exact
 * match is not.
 *
 * @see docs/adr/0005-codes-not-strings.md
 */
enum ResolutionPath: string
{
    /** The normalised query equals a normalised label of exactly one concept. */
    case Exact = 'exact';

    /** The query is close enough to a label, by character trigrams, and no other concept is as close. */
    case Approximate = 'approximate';

    /** The query's vector is close enough to a concept's, and no other concept is as close. */
    case Vector = 'vector';

    /** No step was confident; the user is asked, with the best candidates and their codes visible. */
    case Ask = 'ask';

    public function resolved(): bool
    {
        return $this !== self::Ask;
    }
}
