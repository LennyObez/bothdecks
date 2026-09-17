<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration;

use BothDecks\Tests\Integration\Support\Application;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Pulsar\Database\ConnectionInterface;
use Pulsar\Database\ConnectionManagerInterface;

/**
 * The application reaches its database, and the database is the one the product needs.
 *
 * Three things are proved here and nowhere else. The kernel wires a connection from `config/database.php`
 * and the environment, so a configuration file the framework accepts but nothing reads would stay unnoticed
 * without a query going through it. The suite talks to its own database, never the development one, and the
 * name is asserted rather than assumed. And the two server extensions the schema depends on are present,
 * because a migration that fails on a missing extension fails on the first table, and the message a
 * contributor sees then is about a type, not about what to install.
 */
#[CoversNothing]
final class DatabaseTest extends TestCase
{
    public function testTheKernelWiresAConnectionThatAnswersAQuery(): void
    {
        // Arrange
        $connection = self::connection();

        // Act
        $row = $connection->query('select 1 as one')->firstOrFail();

        // Assert
        self::assertSame(1, $row->getInt('one'));
    }

    public function testTheSuiteTalksToItsOwnDatabase(): void
    {
        // The name is fixed in phpunit.xml with `force`, so nothing in a shell or an environment file can
        // point the suite elsewhere. This asserts that the value actually reached the connection.

        // Arrange
        $connection = self::connection();

        // Act
        $row = $connection->query('select current_database() as name')->firstOrFail();

        // Assert
        self::assertSame('bothdecks_test', $row->getString('name'));
    }

    public function testTheVectorExtensionIsInstalled(): void
    {
        // Arrange
        $connection = self::connection();

        // Act
        $installed = $connection
            ->query('select extversion from pg_extension where extname = :name', ['name' => 'vector'])
            ->first();

        // Assert
        self::assertNotNull(
            $installed,
            "The `vector` extension is not installed in the test database. Install pgvector for this server\n"
            . "and run `create extension vector;` in `bothdecks_test`; see docs/getting-started.md.",
        );
    }

    public function testThePostgisExtensionIsInstalled(): void
    {
        // Arrange
        $connection = self::connection();

        // Act
        $installed = $connection
            ->query('select extversion from pg_extension where extname = :name', ['name' => 'postgis'])
            ->first();

        // Assert
        self::assertNotNull(
            $installed,
            "The `postgis` extension is not installed in the test database. Install PostGIS for this server\n"
            . "and run `create extension postgis;` in `bothdecks_test`; see docs/getting-started.md.",
        );
    }

    private static function connection(): ConnectionInterface
    {
        $container = Application::bootedKernel()->container();

        self::assertTrue(
            $container->has(ConnectionManagerInterface::class),
            'The kernel booted without a database. config/database.php is what makes it wire one.',
        );

        return $container->get(ConnectionManagerInterface::class)->connection();
    }
}
