<?php

declare(strict_types=1);

/**
 * Security configuration, in the shape the framework reads.
 *
 * A reserved name on purpose: the framework requires this file and reads it into its own typed object, and
 * a guarantee test proves this file loads through that object. The framework's defaults are secure and are
 * left alone wherever they fit; what is written here is what this product decides differently, with the
 * reason, so a reviewer reads decisions and not a copy of the defaults.
 */
return [
    'session' => [
        /*
         * The cookie carries no framework name. A cookie name is visible to every client, and it says which
         * software runs the site.
         */
        'cookie_name' => 'bd_session',

        /*
         * A person reading a deck is in the middle of decisions; a session that expires under them loses the
         * card they were about to answer. Fifteen minutes idle is a payment-card rule, not a job-search one.
         * Two hours idle, twelve hours absolute, and the cookie is always secure: this product has no
         * non-TLS surface, in development included.
         */
        'idle_timeout' => 7200,
        'lifetime' => 43200,
        'cookie_secure' => true,
        'cookie_samesite' => 'Lax',
    ],

    'headers' => [
        /*
         * The pages are server-rendered from this origin and load nothing from another one: no font service,
         * no script host, no analytics. The policy says so. Frames are refused entirely rather than allowed
         * from self, because nothing here is meant to be embedded.
         */
        'csp' => [
            'enabled' => true,
            'report_only' => false,
            'default_src' => "'self'",
            'script_src' => "'self'",
            'style_src' => "'self'",
            'object_src' => "'none'",
            'base_uri' => "'self'",
            'frame_ancestors' => "'none'",
        ],
        'X-Frame-Options' => 'DENY',
    ],

    'rate_limiting' => [
        /*
         * The default window covers a deck session: a person answering twelve cards and reading their details
         * makes well under a hundred requests a minute, and an importer walking the public pages does not.
         */
        'enabled' => true,
        'default_limit' => 120,
        'default_window' => 60,
    ],
];
