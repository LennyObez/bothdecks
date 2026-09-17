<?php

declare(strict_types=1);

/**
 * Database configuration.
 *
 * This file deliberately takes a name the framework reserves: the framework reads `config/database.php` into
 * its own typed configuration, and a database exists for the kernel only when this file does. It therefore
 * follows the framework's schema exactly, and a guarantee test proves that by loading it through the
 * framework's own loader rather than trusting the shape by eye.
 *
 * One connection, PostgreSQL. The product's tables lean on two extensions, `vector` for retrieval and
 * `postgis` for commute geometry, and a migration refuses to run where either is missing. There is no
 * second driver: a second driver is a second dialect to keep every query correct against, for a product that
 * will only ever run on one.
 *
 * Every value here is a development default. The process environment overrides each of them (`DB_HOST`,
 * `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CONNECTION`), and the ignored `.env` file at the
 * project root is read for the same keys, with the process winning over the file.
 */
return [
    'default' => 'pgsql',

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'bothdecks',
            'username' => 'bothdecks',
            'password' => '',
            'charset' => 'utf8',
            'collation' => '',
            'options' => [],
        ],
    ],

    'migrations' => [
        'table' => 'schema_migrations',
        'path' => 'database/migrations',
    ],
];
