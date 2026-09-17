<?php

declare(strict_types=1);

/**
 * Template engine configuration, in the shape the framework reads.
 *
 * A reserved name on purpose, and a guarantee test proves this file loads through the framework's typed
 * object. The template path is derived from this file's location rather than written relative, because the
 * engine reads it as given and a relative path resolves against the working directory, which under a
 * process manager is the document root.
 *
 * Templates are trusted: they are tracked files, reviewed as diffs. The sandbox that interprets untrusted
 * templates stays off, and `@php` blocks stay forbidden because a template that can run arbitrary code is a
 * controller nobody tests.
 */
return [
    'template_paths' => [dirname(__DIR__) . '/resources/views'],
    'cache_path' => 'var/cache/views',
    'auto_escape' => true,
    'php_directive_allowed' => false,
];
