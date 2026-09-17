<?php

declare(strict_types=1);

/**
 * Cache configuration, in the shape the framework reads.
 *
 * A reserved name on purpose, and a guarantee test proves this file loads through the framework's typed
 * object. The cache exists at this milestone for one reason: the framework's abuse controls (duplicate
 * detection, reputation cooldowns, single-use challenge tokens) are inert without a tagged cache, and an
 * abuse control that is configured but inert is the worst of both, since it reads as present.
 *
 * One pool, on the local filesystem under `var/`, which is created by the deployment and never tracked. A
 * shared store arrives with the first environment that runs more than one process, and is a change to this
 * file and nothing else.
 */
return [
    'enabled' => true,
    'default_pool' => 'default',
    'path' => 'var/cache',
    'pools' => [
        'default' => [
            'driver' => 'filesystem',
            'serializer' => 'json',
            'default_ttl_seconds' => 3600,
            'critical' => false,
            'encrypted' => false,
            'tags_strategy' => 'auto',
        ],
    ],
];
