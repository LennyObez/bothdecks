<?php

declare(strict_types=1);

/**
 * The locales the product serves, in the shape the framework reads.
 *
 * A reserved name on purpose: the framework reads this file into its own typed object when it exists, and a
 * guarantee test proves this file loads through that object. The product reads the same object for its own
 * closed list of locales, so the framework's locale negotiation and the product's per-language data cannot
 * disagree about which languages exist.
 *
 * The list is the twenty-four official languages of the European Union, by their two-letter codes, and it is
 * closed: a label in a language not listed here is not imported, a request for one is answered in the
 * default, and adding one is a change to this file that the typeface guarantee then measures.
 *
 * The default is the locale served when a request expresses no preference and when a concept carries no
 * label in the requested one; the source classification names its reference language, and it is this one.
 */
return [
    'default_locale' => 'en',
    'supported_locales' => [
        'bg', 'cs', 'da', 'de', 'el', 'en', 'es', 'et', 'fi', 'fr', 'ga', 'hr',
        'hu', 'it', 'lt', 'lv', 'mt', 'nl', 'pl', 'pt', 'ro', 'sk', 'sl', 'sv',
    ],
    'fallback_locales' => ['en'],

    /*
     * A missing translation is an error, never a silent fallback to the key: a key rendered as text is how
     * an untranslated string reaches a screen unnoticed.
     */
    'strict_mode' => true,
];
