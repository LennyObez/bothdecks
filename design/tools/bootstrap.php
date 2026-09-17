<?php

declare(strict_types=1);

/**
 * The whole dependency list.
 *
 * The generator is written in the language the repository already requires of a contributor, and it uses
 * nothing a contributor does not already have. There is no package manifest here, no lock file and no
 * install step: `php design/tools/generate-tokens.php` on a clean checkout is the entire setup.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'BothDecks\\Design\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

if (PHP_VERSION_ID < 80300) {
    fwrite(STDERR, "This tool needs PHP 8.3 or newer; the repository builds on 8.5.\n");

    exit(1);
}

/**
 * Where the source is, and where the artefacts go.
 *
 * Both can be pointed elsewhere for a dry run, `BD_TOKEN_SOURCE=/tmp/experiment.json php ...`, which is how
 * the refusal paths are exercised without touching the tracked source.
 */
define('BD_TOKEN_SOURCE', getenv('BD_TOKEN_SOURCE') ?: __DIR__ . '/../tokens/tokens.json');
define('BD_OUTPUT_DIRECTORY', getenv('BD_OUTPUT_DIRECTORY') ?: __DIR__ . '/../tokens/generated');
