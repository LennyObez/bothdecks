<?php

declare(strict_types=1);

/**
 * Taxonomy configuration.
 *
 * Not a name the framework reserves; this file is read by the Taxonomy module alone, through its typed
 * configuration object, and an unknown key stops the boot there.
 *
 * The source version is written here rather than discovered, because the source's API answers for an old
 * version when none is named: a snapshot taken without naming one would silently be of the wrong release. A
 * move to a newer release is therefore a change to this file, reviewed as a diff, followed by a snapshot, an
 * import and the migration report between the two.
 */
return [
    'source' => [
        /*
         * The classification, its release, and where it is fetched from. The base URL takes no trailing
         * slash. Every request names the release; see the module documentation for why.
         */
        'name' => 'esco',
        'version' => 'v1.2.1',
        'api' => 'https://ec.europa.eu/esco/api',

        /*
         * The terms under which the data is reused, as the publisher states them, and the attribution the
         * publisher asks for. Both are copied onto every imported version and every concept, so a row can
         * always say where it came from and on what terms.
         */
        'licence' => 'CC-BY-4.0',
        'attribution' => 'ESCO, European Commission, DG Employment, Social Affairs and Inclusion',
    ],

    /*
     * Where snapshots are written, relative to the project root, and never tracked. One directory per
     * source and release, holding the raw records and a manifest with their digests.
     */
    'snapshot_path' => 'var/taxonomy',

    /*
     * How many resources one request asks for, and how many requests run at once. The source's cost is
     * linear in the number of resources whatever the batching, so these bound politeness rather than speed.
     */
    'fetch' => [
        'page_size' => 100,
        'batch_size' => 20,
        'concurrency' => 4,
        'timeout_seconds' => 120,
        'attempts' => 4,
    ],

    /*
     * The model that turns a concept's text into a vector, for the proximity signal of the bubbles and for
     * the vector step of the resolution cascade. The provider is reached over HTTP through the framework's
     * client for it; the URL is read from the environment because it names a machine, not a decision.
     *
     * The dimension count is written, not discovered: the table column is typed with it, and a model that
     * answers with another count is refused rather than stored.
     */
    'embedding' => [
        /*
         * The provider, the address and the key come from the environment, because they name a machine and
         * a contract, not a decision: a workstation runs a local runtime, a hosted environment a provider
         * under European law. The model and its dimension count are the decision, and stay here.
         */
        'provider' => env('TAXONOMY_EMBEDDING_PROVIDER', 'ollama'),
        'url' => env('TAXONOMY_EMBEDDING_URL', 'http://127.0.0.1:11434'),
        'api_key' => env('TAXONOMY_EMBEDDING_API_KEY', ''),
        'model' => 'bge-m3',
        'dimensions' => 1024,
        'batch_size' => 16,
    ],

    /*
     * The four signals a bubble is computed from, with their weights. A signal whose data is absent for a
     * release takes weight zero for that release and the version report says so; the weights here are what a
     * release with every signal uses. They sum to one.
     */
    'bubbles' => [
        'weights' => [
            'hierarchy' => 0.25,
            'skills' => 0.40,
            'vector' => 0.25,
            'mobility' => 0.10,
        ],
        /*
         * A neighbour below this combined score is not in the bubble, and a bubble holds at most this many
         * neighbours, ranked by score. Both are the starting values; the inspection screen exists to
         * challenge them.
         */
        'minimum_score' => 0.35,
        'maximum_neighbours' => 12,
    ],

    /*
     * The resolution cascade. An approximate match is considered from the minimum similarity up, stands
     * alone from the trusted similarity up (a typo away from a label), and between the two is trusted only
     * when the vector step names the same concept. A vector match alone must clear its own floor. Below
     * every floor the cascade asks the user, with the best three candidates. The evaluation is what
     * justifies these values, and what to run after changing one.
     */
    'resolution' => [
        'approximate_minimum_similarity' => 0.60,
        'approximate_trusted_similarity' => 0.85,
        'vector_minimum_similarity' => 0.60,
        'candidates_when_asking' => 3,
    ],

    /*
     * The inspection screen, where a person reads a bubble, its signals and the corrections made to it.
     * It is served only where a token is configured, and only to a request that presents it; with no
     * token the routes answer as if they did not exist. This holds until accounts and roles arrive, when
     * the screen moves behind them.
     */
    'inspection' => [
        'token' => env('TAXONOMY_INSPECTION_TOKEN', ''),
    ],
];
