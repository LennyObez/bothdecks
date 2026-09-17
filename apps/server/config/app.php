<?php

declare(strict_types=1);

/**
 * Application configuration, in the shape the framework reads.
 *
 * This file takes a name the framework reserves, on purpose: the framework requires it and reads it into its
 * own typed object, and a guarantee test proves this file loads through that object. Every key the framework
 * defaults sensibly is left out; what is here is what this product decides differently, with the reason.
 *
 * The name is read from `identity.php` rather than written, because the product name lives in one place
 * (ADR-0004). Without this the framework would name the application after itself in error pages and mail.
 */
$identity = require __DIR__ . '/identity.php';

if (!is_array($identity) || !is_string($identity['name'] ?? null)) {
    throw new LogicException('config/identity.php must return an array with a string name.');
}

return [
    'name' => $identity['name'],

    /*
     * The environment and the debug switch come from the process (`APP_ENV`, `APP_DEBUG`). The values here
     * are what an unset process gets, and an unset process is treated as production: a server nobody
     * configured must not show a stack trace to the first visitor.
     */
    'env' => 'production',
    'debug' => false,

    'timezone' => 'UTC',
    'locale' => 'en',

    /*
     * No generator tag and no author tag. The framework is not part of what a visitor is told, and the owner
     * is named on the legal pages rather than in a meta element on every one.
     */
    'signature' => [
        'generator' => false,
        'author' => '',
    ],

    /*
     * A key the framework does not recognise stops the boot rather than being ignored with a warning. A
     * misspelt key that reads as a pass is how a setting nobody applied looks applied.
     */
    'config' => ['strict_keys' => true],
];
