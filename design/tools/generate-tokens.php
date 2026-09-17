<?php

declare(strict_types=1);

/**
 * Turn design/tokens/tokens.json into the three platform themes.
 *
 *   php design/tools/generate-tokens.php            write the artefacts
 *   php design/tools/generate-tokens.php --check    fail if what is committed differs from the source
 *
 * Running it twice produces byte-identical files: the artefacts carry no date, no version and no ordering
 * that depends on anything but the source. That is what lets `--check` be a pipeline step rather than an
 * article of faith.
 */

require __DIR__ . '/bootstrap.php';

use BothDecks\Design\Failure;
use BothDecks\Design\Generator;

$check = in_array('--check', $argv, true);
$quiet = in_array('--quiet', $argv, true);

$out = static function (string $line) use ($quiet): void {
    if (!$quiet) {
        fwrite(STDOUT, $line . "\n");
    }
};

try {
    $generator = new Generator(BD_TOKEN_SOURCE, BD_OUTPUT_DIRECTORY);
    $build = $generator->build();

    $out('Source: ' . realpath(BD_TOKEN_SOURCE));
    $out('');
    $out('Coverage, group by group:');

    foreach ($build['coverage'] as $line) {
        $out('  ' . $line);
    }

    if ($build['exclusions'] !== []) {
        $out('');
        $out('Declared exclusions, a target a group does not reach and why:');

        foreach ($build['exclusions'] as $line) {
            $out('  - ' . $line);
        }
    }

    if ($build['pending'] !== []) {
        $out('');
        $out('Groups the manifest knows about and the source does not define yet:');

        foreach ($build['pending'] as $line) {
            $out('  - ' . $line);
        }
    }

    $out('');

    if ($check) {
        $differences = $generator->differences($build['files']);

        if ($differences !== []) {
            fwrite(STDERR, "The committed artefacts do not match the token source:\n");

            foreach ($differences as $difference) {
                fwrite(STDERR, '  - ' . $difference . "\n");
            }

            fwrite(STDERR, "Run `php design/tools/generate-tokens.php` and commit the result.\n");

            exit(1);
        }

        $out('Up to date: every artefact matches the source byte for byte.');

        exit(0);
    }

    $result = $generator->write($build['files']);

    foreach ($result['written'] as $name) {
        $out('  written    ' . $name);
    }

    foreach ($result['unchanged'] as $name) {
        $out('  unchanged  ' . $name);
    }

    exit(0);
} catch (Failure $failure) {
    fwrite(STDERR, "Generation refused.\n\n" . $failure->getMessage() . "\n\nNothing was written.\n");

    exit(1);
}
