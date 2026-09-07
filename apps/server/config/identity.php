<?php

declare(strict_types=1);

/**
 * Application identity.
 *
 * The product name lives here and nowhere else, so a rebrand is a change to this one value. No template,
 * translation catalogue, mobile string file or error message may repeat it; a guarantee test fails the build
 * if one does.
 *
 * The file is deliberately **not** named `app.php`: the framework reserves that name, along with twenty-two
 * others, for its own typed configuration, and would read this file expecting a different shape entirely. A
 * guarantee test fails if any file in this directory takes a reserved name.
 *
 * @see docs/adr/0004-the-product-name-lives-in-one-place.md
 */
return [
    /*
     * The user-visible product name. Rendered wherever the interface names the product, always by reading
     * this value, never by writing the literal.
     */
    'name' => 'Both Decks',

    /*
     * The stable technical identifier. Unlike the name above, this one cannot change after publication: it
     * appears in package identifiers, database names and log streams. It is deliberately neutral and is not
     * user-visible, so it takes no part in a rename.
     */
    'slug' => 'bothdecks',

    /*
     * The canonical public origin, used to build absolute URLs in exports, notifications and verification
     * pages. Read from the environment so that each deployment states its own.
     */
    'url' => env('APP_URL', 'http://localhost:8080'),

    /*
     * Active environment. Anything other than 'production' relaxes error rendering; nothing else depends on
     * it, because behaviour that differs between environments is behaviour no environment tests.
     */
    'env' => env('APP_ENV', 'local'),

    /*
     * The locale served when a request expresses no preference. Every locale the product ships in is listed
     * in config/i18n.php; this one only decides the fallback.
     */
    'default_locale' => 'en',
];
