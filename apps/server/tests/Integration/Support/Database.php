<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Support;

use PHPUnit\Framework\Assert;
use Pulsar\Config\DatabaseConfig;
use Pulsar\Core\Kernel;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\ConnectionManagerInterface;
use Pulsar\Database\Migration\MigrationPathResolver;
use Pulsar\Database\Migration\MigrationRepository;
use Pulsar\Database\Migration\MigrationRunner;

/**
 * The test database, brought to the current schema once per process and emptied between tests.
 *
 * Migrations run through the same runner the console uses, so a test exercises the schema a deployment
 * gets and not a hand-written copy of it. Emptying is a truncate of the product's tables, never a drop, so
 * a suite of a hundred tests pays for the schema once.
 */
final class Database
{
    private static bool $migrated = false;

    public static function connection(Kernel $kernel): ConnectionInterface
    {
        $container = $kernel->container();

        Assert::assertTrue($container->has(ConnectionManagerInterface::class), 'The kernel booted without a database.');

        return $container->get(ConnectionManagerInterface::class)->connection();
    }

    /**
     * Bring the schema up, once per process.
     */
    public static function migrate(Kernel $kernel): void
    {
        if (self::$migrated) {
            return;
        }

        $container = $kernel->container();
        $config = $container->get(DatabaseConfig::class);
        $repository = new MigrationRepository(new MigrationPathResolver($config)->resolve());
        $runner = new MigrationRunner(self::connection($kernel), $repository, $config->migrationsTable);

        $runner->ensureMigrationTable();
        $runner->runPending();

        self::$migrated = true;
    }

    /**
     * Empty every table the taxonomy module owns. Identity sequences restart so identifiers are the same
     * from one test to the next, which keeps assertions on them readable.
     */
    public static function emptyTaxonomy(ConnectionInterface $connection): void
    {
        $connection->execute(
            'TRUNCATE taxonomy_resolution_log, occupation_cluster_override, occupation_cluster_member, occupation_cluster,'
            . ' occupation_transition, concept_embedding, occupation_skill, skill, occupation, concept_broader,'
            . ' concept_description, concept_label, product_skill, taxonomy_concept, taxonomy_version'
            . ' RESTART IDENTITY CASCADE',
        );
    }
}
