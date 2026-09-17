<?php

declare(strict_types=1);

use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\Migration\MigrationInterface;

/**
 * The taxonomy: versions, concepts, labels per language, relations, embeddings, bubbles and the log of every
 * resolution.
 *
 * Written as SQL rather than through the schema builder because the product runs on one engine, PostgreSQL,
 * and leans on what only it offers: identity columns, partial unique indexes, the `vector` type, trigram
 * indexes. A builder that abstracts over engines the product will never run on would hide exactly the
 * features the schema is built on.
 *
 * Every statement is idempotent, so the migration can be re-run after an interruption; on this engine the
 * runner also wraps it in a transaction, so an interruption leaves nothing behind.
 *
 * @see docs/adr/0005-codes-not-strings.md
 * @see docs/taxonomy.md
 */
return new class implements MigrationInterface {
    /**
     * One extension is created here because the engine lets a database owner create it; two more must
     * already be there, because creating them takes a superuser, and a missing one is refused with its name
     * rather than discovered later as an unknown type.
     */
    private const array EXTENSIONS_CREATED = ['pg_trgm'];
    private const array EXTENSIONS_REQUIRED = ['vector', 'postgis'];

    public function up(ConnectionInterface $connection): void
    {
        $this->requireExtensions($connection);

        foreach (self::EXTENSIONS_CREATED as $extension) {
            $connection->execute('CREATE EXTENSION IF NOT EXISTS ' . $extension);
        }

        foreach ($this->statements() as $statement) {
            $connection->execute($statement);
        }
    }

    public function down(ConnectionInterface $connection): void
    {
        foreach (array_reverse($this->tables()) as $table) {
            $connection->execute('DROP TABLE IF EXISTS ' . $table . ' CASCADE');
        }
    }

    private function requireExtensions(ConnectionInterface $connection): void
    {
        $installed = $connection
            ->query('SELECT extname FROM pg_extension')
            ->pluck('extname');

        $missing = array_values(array_diff(self::EXTENSIONS_REQUIRED, array_map(strval(...), $installed)));

        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'The database lacks the %s extension(s). A superuser creates them once: %s',
                implode(' and ', $missing),
                implode('; ', array_map(static fn(string $e): string => 'CREATE EXTENSION ' . $e, $missing)),
            ));
        }
    }

    /**
     * Every table this migration owns, in creation order.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            'taxonomy_version',
            'taxonomy_concept',
            'concept_label',
            'concept_description',
            'concept_broader',
            'occupation',
            'skill',
            'occupation_skill',
            'concept_embedding',
            'occupation_transition',
            'occupation_cluster',
            'occupation_cluster_member',
            'occupation_cluster_override',
            'product_skill',
            'taxonomy_resolution_log',
        ];
    }

    /**
     * @return list<string>
     */
    private function statements(): array
    {
        return [
            // One row per imported snapshot. The digest is what makes a version a version: the same release
            // imported from a different snapshot is a different row. At most one row is current.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS taxonomy_version (
                id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                source            text NOT NULL,
                source_version    text NOT NULL,
                snapshot_digest   text NOT NULL,
                snapshot_taken_at timestamptz NOT NULL,
                licence           text NOT NULL,
                attribution       text NOT NULL,
                imported_at       timestamptz NOT NULL,
                is_current        boolean NOT NULL DEFAULT false,
                UNIQUE (source, source_version, snapshot_digest)
            )
            SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS taxonomy_version_one_current ON taxonomy_version (is_current) WHERE is_current',

            // Every concept of every kind, in one table, so labels, descriptions, hierarchy edges and
            // embeddings reference one identifier whatever the kind. A concept the product itself owns has
            // no version: it outlives releases, and its identifier is a URN under the product's namespace.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS taxonomy_concept (
                id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                version_id  bigint REFERENCES taxonomy_version (id) ON DELETE CASCADE,
                kind        text NOT NULL CHECK (kind IN ('occupation', 'skill', 'isco_group', 'skill_group')),
                uri         text NOT NULL,
                code        text,
                status      text NOT NULL,
                source      text NOT NULL,
                source_uri  text NOT NULL,
                licence     text NOT NULL,
                CHECK ((source = 'product') = (version_id IS NULL)),
                UNIQUE NULLS NOT DISTINCT (version_id, uri)
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS taxonomy_concept_version_kind ON taxonomy_concept (version_id, kind)',
            'CREATE INDEX IF NOT EXISTS taxonomy_concept_kind_code ON taxonomy_concept (kind, code)',

            // Labels, one row per concept, language and wording. The normalised column is what exact and
            // approximate matching read: the query is normalised the same way, so a match never depends on
            // case, accents or spacing.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS concept_label (
                id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                concept_id  bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                locale      text NOT NULL CHECK (locale ~ '^[a-z]{2}$'),
                kind        text NOT NULL CHECK (kind IN ('preferred', 'alternative', 'hidden')),
                label       text NOT NULL CHECK (label <> ''),
                normalised  text NOT NULL CHECK (normalised <> ''),
                UNIQUE (concept_id, locale, kind, label)
            )
            SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS concept_label_one_preferred ON concept_label (concept_id, locale) WHERE kind = \'preferred\'',
            'CREATE INDEX IF NOT EXISTS concept_label_locale_normalised ON concept_label (locale, normalised)',
            'CREATE INDEX IF NOT EXISTS concept_label_normalised_trigram ON concept_label USING gin (normalised gin_trgm_ops)',

            <<<'SQL'
            CREATE TABLE IF NOT EXISTS concept_description (
                concept_id   bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                locale       text NOT NULL CHECK (locale ~ '^[a-z]{2}$'),
                description  text NOT NULL CHECK (description <> ''),
                PRIMARY KEY (concept_id, locale)
            )
            SQL,

            // Hierarchy edges of every kind: an occupation's group and parent occupation, a skill's groups, a
            // group's parent group. The hierarchy distance between two occupations is read from here.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS concept_broader (
                concept_id  bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                broader_id  bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                PRIMARY KEY (concept_id, broader_id),
                CHECK (concept_id <> broader_id)
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS concept_broader_broader ON concept_broader (broader_id)',

            <<<'SQL'
            CREATE TABLE IF NOT EXISTS occupation (
                concept_id    bigint PRIMARY KEY REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                isco_group_id bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE RESTRICT,
                regulated     text
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS occupation_isco_group ON occupation (isco_group_id)',

            // Type and reuse level are what the source states; a handful of umbrella skills state neither,
            // and null is what the source said rather than a value invented for them.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS skill (
                concept_id   bigint PRIMARY KEY REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                skill_type   text,
                reuse_level  text
            )
            SQL,

            // A skill is essential or optional for an occupation, never both.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS occupation_skill (
                occupation_id  bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                skill_id       bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                relation       text NOT NULL CHECK (relation IN ('essential', 'optional')),
                PRIMARY KEY (occupation_id, skill_id)
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS occupation_skill_skill ON occupation_skill (skill_id)',

            // One vector per concept, from the model named on the row. The dimension count is fixed in the
            // type; a model that answers with another count is refused before a row is written.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS concept_embedding (
                concept_id   bigint PRIMARY KEY REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                model        text NOT NULL,
                embedding    vector(1024) NOT NULL,
                embedded_at  timestamptz NOT NULL
            )
            SQL,

            // Observed moves from one occupation to another in candidates' own histories. Empty until the
            // product has histories to observe; the bubble computation reads it and reports its weight as
            // zero while it is empty.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS occupation_transition (
                from_uri        text NOT NULL,
                to_uri          text NOT NULL,
                observations    integer NOT NULL CHECK (observations > 0),
                observed_until  date NOT NULL,
                PRIMARY KEY (from_uri, to_uri)
            )
            SQL,

            // A bubble per occupation and version, with the weights and thresholds it was computed under, so
            // two computations can be told apart.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS occupation_cluster (
                id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                version_id     bigint NOT NULL REFERENCES taxonomy_version (id) ON DELETE CASCADE,
                occupation_id  bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                computed_at    timestamptz NOT NULL,
                parameters     jsonb NOT NULL,
                UNIQUE (version_id, occupation_id)
            )
            SQL,

            // Members carry the four signal scores beside the combined weight, so the inspection screen can
            // say why each neighbour is there, and an origin says whether a person put it there.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS occupation_cluster_member (
                cluster_id    bigint NOT NULL REFERENCES occupation_cluster (id) ON DELETE CASCADE,
                neighbour_id  bigint NOT NULL REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                weight        numeric(5, 4) NOT NULL CHECK (weight >= 0 AND weight <= 1),
                hierarchy     numeric(5, 4) NOT NULL CHECK (hierarchy >= 0 AND hierarchy <= 1),
                skills        numeric(5, 4) NOT NULL CHECK (skills >= 0 AND skills <= 1),
                vector        numeric(5, 4) NOT NULL CHECK (vector >= 0 AND vector <= 1),
                mobility      numeric(5, 4) NOT NULL CHECK (mobility >= 0 AND mobility <= 1),
                origin        text NOT NULL CHECK (origin IN ('computed', 'added')),
                PRIMARY KEY (cluster_id, neighbour_id)
            )
            SQL,

            // Corrections by a person, keyed by source identifiers so they survive a recomputation and a
            // version change. Applied on top of every computation; never silently.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS occupation_cluster_override (
                id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                occupation_uri  text NOT NULL,
                neighbour_uri   text NOT NULL,
                action          text NOT NULL CHECK (action IN ('include', 'exclude')),
                weight          numeric(5, 4) CHECK (weight IS NULL OR (weight >= 0 AND weight <= 1)),
                reason          text NOT NULL CHECK (reason <> ''),
                author          text NOT NULL CHECK (author <> ''),
                created_at      timestamptz NOT NULL,
                UNIQUE (occupation_uri, neighbour_uri),
                CHECK (action = 'include' OR weight IS NULL)
            )
            SQL,

            // Skills the product owns because the source lacks them. Each is a concept in the table above
            // with no version; this table carries its lifecycle, up to its promotion to a source concept.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS product_skill (
                concept_id       bigint PRIMARY KEY REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                status           text NOT NULL CHECK (status IN ('proposed', 'accepted', 'promoted', 'retired')),
                reason           text NOT NULL CHECK (reason <> ''),
                author           text NOT NULL CHECK (author <> ''),
                created_at       timestamptz NOT NULL,
                promoted_to_uri  text,
                promoted_at      timestamptz,
                CHECK ((status = 'promoted') = (promoted_to_uri IS NOT NULL)),
                CHECK ((promoted_to_uri IS NULL) = (promoted_at IS NULL))
            )
            SQL,

            // Every resolution, with its path, score and version. A resolution that cannot be explained
            // afterwards is a defect; this is what makes explaining possible.
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS taxonomy_resolution_log (
                id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                version_id    bigint NOT NULL REFERENCES taxonomy_version (id) ON DELETE CASCADE,
                requested_at  timestamptz NOT NULL,
                locale        text NOT NULL,
                kind          text NOT NULL,
                query         text NOT NULL,
                normalised    text NOT NULL,
                path          text NOT NULL CHECK (path IN ('exact', 'approximate', 'vector', 'ask')),
                concept_id    bigint REFERENCES taxonomy_concept (id) ON DELETE CASCADE,
                score         numeric(6, 5),
                candidates    jsonb NOT NULL,
                elapsed_ms    integer NOT NULL CHECK (elapsed_ms >= 0),
                CHECK ((path = 'ask') = (concept_id IS NULL))
            )
            SQL,
            'CREATE INDEX IF NOT EXISTS taxonomy_resolution_log_requested ON taxonomy_resolution_log (requested_at)',
            'CREATE INDEX IF NOT EXISTS taxonomy_resolution_log_path ON taxonomy_resolution_log (version_id, path)',
        ];
    }
};
