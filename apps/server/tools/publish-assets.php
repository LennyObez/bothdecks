<?php

declare(strict_types=1);

/**
 * Copy the static assets a page references into the document root.
 *
 * The design tokens' stylesheets and typefaces live in the design directory at the repository root, where
 * they are generated and checked; the console stylesheet lives under resources/. None of them is served
 * from where it lives: `public/` holds the front controller and what this script puts beside it, and
 * nothing under `public/assets/` is tracked. Run after `composer install` and by the deployment script.
 *
 * Copies rather than symbolic links, because a link from the document root into the repository is one
 * misconfigured alias away from serving the repository.
 */

$root = dirname(__DIR__);
$repository = dirname($root, 2);
$target = $root . '/public/assets';

$sources = [
    // The generated stylesheets reference the typefaces as ../fonts/, so the two directories stay siblings.
    $repository . '/design/tokens/generated' => $target . '/design/generated',
    $repository . '/design/tokens/fonts' => $target . '/design/fonts',
    $root . '/resources/css' => $target,
];

$served = ['css', 'woff', 'woff2'];
$copied = 0;

foreach ($sources as $from => $to) {
    if (!is_dir($from)) {
        fwrite(STDERR, "Missing source directory: {$from}\n");
        exit(1);
    }

    if (!is_dir($to) && !mkdir($to, 0o755, true) && !is_dir($to)) {
        fwrite(STDERR, "Cannot create {$to}\n");
        exit(1);
    }

    foreach (scandir($from) ?: [] as $entry) {
        $path = $from . '/' . $entry;

        if (!is_file($path) || !in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $served, true)) {
            continue;
        }

        if (!copy($path, $to . '/' . $entry)) {
            fwrite(STDERR, "Cannot copy {$path}\n");
            exit(1);
        }

        $copied++;
    }
}

echo "{$copied} asset(s) published to {$target}\n";
