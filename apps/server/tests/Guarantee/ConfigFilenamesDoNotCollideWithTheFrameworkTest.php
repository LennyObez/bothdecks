<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pulsar\Config\AppConfig;
use Pulsar\Config\CacheConfig;
use Pulsar\Config\DatabaseConfig;
use Pulsar\Config\Environment;
use Pulsar\Config\I18nConfig;
use Pulsar\Config\ObservabilityConfig;
use Pulsar\Config\SecurityConfig;
use Pulsar\View\ViewConfig;

/**
 * Guarantee: a configuration file takes a name the framework reserves only when it is that framework object,
 * and proves it by loading through the framework's own typed loader.
 *
 * The framework reads a fixed set of configuration filenames into its own typed objects, each with a schema of
 * its own. A project file that happens to share one of those names is read as that object, and the mismatch
 * surfaces as an unrelated boot failure, or worse, as silently ignored keys. The framework also requires
 * three of those files and wires a database only when a fourth exists, so the project has to write some of
 * them. Those are declared here by name, each paired with the loader that must accept the file: a declared
 * file that the loader refuses fails this test, and a reserved name that is not declared fails it too.
 *
 * The reserved names are read from the framework's own source rather than copied here, so the check follows
 * the framework instead of drifting from it.
 */
#[Group('guarantee')]
final class ConfigFilenamesDoNotCollideWithTheFrameworkTest extends TestCase
{
    /**
     * Reserved names this project writes on purpose, and the framework loader each must satisfy.
     *
     * @var array<string, class-string>
     */
    private const array DECLARED = [
        'app' => AppConfig::class,
        'security' => SecurityConfig::class,
        'observability' => ObservabilityConfig::class,
        'database' => DatabaseConfig::class,
        'cache' => CacheConfig::class,
        'i18n' => I18nConfig::class,
        'view' => ViewConfig::class,
    ];

    public function testNoUndeclaredProjectConfigFileTakesAReservedName(): void
    {
        // Arrange
        $reserved = self::reservedConfigNames();

        // Act
        $collisions = array_values(array_diff(
            array_intersect(self::projectConfigNames(), $reserved),
            array_keys(self::DECLARED),
        ));

        // Assert
        self::assertSame(
            [],
            $collisions,
            "These configuration files take names the framework reserves for its own typed configuration and are\n"
            . "not declared as such. Either rename them, or declare each with the loader it satisfies:\n  - "
            . implode("\n  - ", array_map(static fn(string $n): string => $n . '.php', $collisions)),
        );
    }

    public function testEveryDeclaredFileLoadsThroughTheFrameworkObjectItClaimsToBe(): void
    {
        // Arrange
        $environment = Environment::load(null);

        foreach (self::DECLARED as $name => $loader) {
            $path = self::serverRoot() . '/config/' . $name . '.php';
            self::assertFileExists($path, \sprintf('%s.php is declared as a framework file and does not exist.', $name));

            // Act
            $data = require $path;

            // Assert
            self::assertIsArray($data, \sprintf('%s.php must return an array.', $name));
            $object = $loader::fromArray($data, $environment, self::serverRoot());
            self::assertInstanceOf($loader, $object, \sprintf('%s.php did not load as %s.', $name, $loader));
        }
    }

    public function testEveryDeclaredNameIsOneTheFrameworkActuallyReserves(): void
    {
        // A declaration for a name the framework does not read would be a file nobody loads, kept in the
        // belief that the framework does.

        // Act
        $unknown = array_values(array_diff(array_keys(self::DECLARED), self::reservedConfigNames()));

        // Assert
        self::assertSame([], $unknown, 'Declared as framework files, but the framework reserves no such name: ' . implode(', ', $unknown));
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
        self::assertContains('i18n', $reserved, 'The framework is known to reserve "i18n", a name with digits; the scan missed it.');
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
        self::assertContains('identity', $names, 'The identity file is the one project file that must never take a reserved name.');
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

        // Digits are part of the character class because one reserved name carries them, and an extraction
        // that stopped at letters would report that name as free.
        preg_match_all("/loadConfigFile\('([a-z0-9_]+)'\)/", $source, $matches);

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
