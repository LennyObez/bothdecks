<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Ingestion;

/**
 * The one way a label or a query is normalised before it is compared.
 *
 * Labels are normalised when imported and queries when resolved, by this same function, so two spellings
 * that differ only in case, accents, punctuation or spacing compare equal. Accents are removed on purpose:
 * a job title typed on a phone or a foreign keyboard often lacks them, and the cost, two words that differ
 * only by an accent colliding, is paid as a question to the user rather than as a wrong code, because an
 * exact match on more than one concept is never resolved silently.
 *
 * Only the letters and digits of the text survive, separated by single spaces. Everything the function does
 * is deterministic and locale-independent, so a label normalised on one machine equals the same label
 * normalised on another.
 */
final class LabelNormaliser
{
    public static function normalise(string $text): string
    {
        // Compatibility decomposition first, so ligatures and width variants become their plain letters,
        // then lowercase, then the combining marks are dropped: that is what removes the accents.
        $decomposed = \Normalizer::normalize(mb_strtolower($text, 'UTF-8'), \Normalizer::FORM_KD);

        if (!\is_string($decomposed)) {
            throw new \InvalidArgumentException('The text is not valid UTF-8.');
        }

        $stripped = preg_replace('/\p{Mn}+/u', '', $decomposed);
        $letters = preg_replace('/[^\p{L}\p{N}]+/u', ' ', \is_string($stripped) ? $stripped : '');

        // Recomposed so a letter that has a precomposed form without a mark (a German sharp s, a Polish
        // stroked l) is stored the way a keyboard produces it.
        $composed = \Normalizer::normalize(trim(\is_string($letters) ? $letters : ''), \Normalizer::FORM_C);

        return \is_string($composed) ? $composed : '';
    }
}
