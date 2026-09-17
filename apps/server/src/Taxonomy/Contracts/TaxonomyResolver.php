<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Contracts;

use BothDecks\Taxonomy\Domain\ConceptKind;
use BothDecks\Taxonomy\Domain\Resolution;

/**
 * Turns free text in one of the product's languages into a taxonomy concept, or into a question.
 *
 * This is how every other module gets a code for a word: the profile module for a skill extracted from a
 * document, the offer module for a job title pasted from a page. None of them ever stores the word; they
 * store what this returns. Every call is logged with its path, score and version, so a resolution can be
 * explained afterwards.
 *
 * @see docs/adr/0005-codes-not-strings.md
 */
interface TaxonomyResolver
{
    /**
     * @param string $query Free text, in the given locale.
     * @param string $locale One of the product's locales.
     * @param ConceptKind $kind What the text is supposed to name; one of the resolvable kinds.
     */
    public function resolve(string $query, string $locale, ConceptKind $kind): Resolution;
}
