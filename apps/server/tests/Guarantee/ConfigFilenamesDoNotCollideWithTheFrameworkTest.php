<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: no configuration file takes a name the framework reserves.
 *
 * The framework reads a fixed set of configuration filenames into its own typed objects, each with a schema of
 * its own. A project file that happens to share one of those names is read as that object, and the mismatch
 * surfaces as an unrelated boot failure, or worse, as silently ignored keys.
 *
 * The trap is dormant right now, because the configuration pipeline is only wired once the application needs
 * it. That is exactly why it deserves a test: a defect that cannot fire today, in a file nobody will revisit,
 * is the kind that costs an afternoon in six months.
 *
 * The reserved names are read from the framework's own source rather than copied here, so the check follows
 * the framework instead of drifting from it.
 */
#[Group('guarantee')]
final class ConfigFilenamesDoNotCollideWithTheFrameworkTest extends TestCase
{
    public function testNoProjectConfigFileTakesAReservedName(): void
    {
        // Arrange
        $reserved = self::reservedConfigNames();

        // Act
        $collisions = array_values(array_intersect(self::projectConfigNames(), $reserved));

        // Assert
        self::assertSame(
            [],
            $collisions,
            "These configuration files take names the framework reserves for its own typed configuration.\n"
            . "It will read them expecting a different shape entirely. Rename them:\n  - "
            . implode("\n  - ", array_map(static fn(string $n): string => $n . '.php', $collisions)),
        );
    }

    public function testTheReservedNamesWereActuallyReadFromTheFramework(): void
    {
        // If the extraction stopped matching (because the framework changed how it names its loader calls),
        // the check above would pass by comparing against an empty list.

        // Act
        $reserved = self::reservedConfigNames();

        // Assert
        self::assertNotEmpty($reserved, 'No reserved configuration name was found in the framework source.');
        self::assertContains('app', $reserved, 'The framework is known to reserve "app"; the scan missed it.');
        self::assertGreaterThan(
            5,
            \count($reserved),
            'Only ' . \count($reserved) . ' reserved names found, far fewer than the framework declares.',
        );
    }

    public function testTheProjectActuallyHasConfigurationToCheck(): void
    {
        // Act
        $names = self::projectConfigNames();

        // Assert
        self::assertNotEmpty($names, 'No project configuration file was found; the check above is inert.');
    }

    /**
     * Configuration filenames the framework loads into its own typed objects, without the `.php` suffix.
     *
     * @return list<string>
     */
    private static function reservedConfigNames(): array
    {
        $source = file_get_contents(
            self::serverRoot() . '/vendor/pulsar/framework/src/Config/ConfigManager.php',
        );

        if (!\is_string($source)) {
            self::fail('Could not read the framework configuration manager to learn its reserved names.');
        }

        preg_match_all("/loadConfigFile\('([a-z_]+)'\)/", $source, $matches);

        $names = array_values(array_unique($matches[1]));
        sort($names);

        return $names;
    }

    /**
     * Configuration filenames this project declares, without the `.php` suffix.
     *
     * @return list<string>
     */
    private static function projectConfigNames(): array
    {
        $names = [];

        foreach (glob(self::serverRoot() . '/config/*.php') ?: [] as $path) {
            $names[] = basename($path, '.php');
        }

        sort($names);

        return $names;
    }

    private static function serverRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
