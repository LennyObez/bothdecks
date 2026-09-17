<?php

declare(strict_types=1);

/**
 * Observability configuration, in the shape the framework reads.
 *
 * A reserved name on purpose: the framework requires this file and reads it into its own typed object, and
 * a guarantee test proves this file loads through that object. Only what this product decides differently
 * from the framework's defaults is written here.
 */
return [
    'logging' => [
        /*
         * Logs go to the process's standard error and nowhere else. The process manager collects them, which
         * is where retention and access are decided; a log file the application writes for itself under its
         * own directory is a second copy with a second retention nobody set. The framework's file channel is
         * therefore not declared at all.
         */
        'default_channel' => 'stderr',
        'level' => 'info',
        'channels' => [
            'stderr' => [
                'driver' => 'stream',
                'stream' => 'php://stderr',
            ],
        ],
    ],

    'error_tracking' => [
        'enabled' => true,
        /*
         * Fields that never reach an error report, whatever the exception carried. Candidate data is the
         * product's whole sensitivity, so the list names the fields a profile and a document are made of.
         */
        'sensitive_fields' => [
            'password', 'token', 'authorization', 'cookie',
            'email', 'phone', 'address', 'name', 'given_name', 'family_name',
            'cv', 'document', 'photo',
        ],
    ],
];
