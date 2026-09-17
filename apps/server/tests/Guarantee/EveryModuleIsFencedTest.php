<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: every module under src/ is fenced by the boundary check, as a public layer and an internal one.
 *
 * The boundary check only sees the layers its configuration names. A module added to src/ without a pair of
 * layers is a module whose internals every other module may reach, and the check stays green while it does,
 * because a class that belongs to no layer is not a class the check reasons about. This test closes that gap
 * from the other side: the modules are read from the directory tree, the layers from the configuration, and
 * the two must agree.
 *
 * The layers are read from the configuration file rather than from a list here, so the test follows the
 * configuration instead of drifting from it; and the modules are read from disk, so a directory nobody
 * remembered is exactly what gets caught.
 */
#[Group('guarantee')]
final class EveryModuleIsFencedTest extends TestCase
{
    public function testEveryModuleDirectoryHasAPublicLayerAndAnInternalLayer(): void
    {
        // Arrange
        $layers = self::configuredLayers();
        $missing = [];

        // Act
        foreach (self::moduleDirectories() as $module) {
            foreach ([$module, $module . '_Internal'] as $layer) {
                if (!\in_array($layer, $layers, true)) {
                    $missing[] = $layer;
                }
            }
        }

        // Assert
        self::assertSame(
            [],
            $missing,
            "These layers are missing from deptrac.yaml, so the module they belong to is not fenced:\n  - "
            . implode("\n  - ", $missing),
        );
    }

    public function testEveryProductLayerCorrespondsToAModuleDirectory(): void
    {
        // A layer whose module no longer exists on disk is a fence around nothing, kept in the belief that
        // it protects something.

        // Arrange
        $modules = self::moduleDirectories();
        $orphans = [];

        // Act
        foreach (self::configuredLayers() as $layer) {
            $module = str_ends_with($layer, '_Internal') ? substr($layer, 0, -\strlen('_Internal')) : $layer;

            if (\in_array($module, ['CompositionRoot', 'Framework'], true)) {
                continue;
            }

            if (!\in_array($module, $modules, true)) {
                $orphans[] = $layer;
            }
        }

        // Assert
        self::assertSame(
            [],
            $orphans,
            "These layers in deptrac.yaml name a module that has no directory under src/:\n  - "
            . implode("\n  - ", $orphans),
        );
    }

    public function testTheConfigurationActuallyDeclaresLayers(): void
    {
        // If the extraction stopped matching (because the configuration changed shape), the checks above
        // would compare against an empty list and pass.

        // Act
        $layers = self::configuredLayers();

        // Assert
        self::assertContains('Shared', $layers, 'The Shared layer is known to exist; the scan missed it.');
        self::assertContains('Shared_Internal', $layers, 'The Shared_Internal layer is known to exist; the scan missed it.');
    }

    public function testTheTreeActuallyHoldsModules(): void
    {
        // Act
        $modules = self::moduleDirectories();

        // Assert
        self::assertContains('Shared', $modules, 'src/Shared is known to exist; the scan missed it.');
    }

    /**
     * Layer names declared in deptrac.yaml.
     *
     * @return list<string>
     */
    private static function configuredLayers(): array
    {
        $source = file_get_contents(self::serverRoot() . '/deptrac.yaml');

        if (!\is_string($source)) {
            self::fail('Could not read deptrac.yaml to learn its layers.');
        }

        preg_match_all('/^\s+- name: ([A-Za-z_]+)$/m', $source, $matches);

        $names = array_values(array_unique($matches[1]));
        sort($names);

        return $names;
    }

    /**
     * Top-level directories under src/, which are the modules.
     *
     * @return list<string>
     */
    private static function moduleDirectories(): array
    {
        $modules = [];

        foreach (glob(self::serverRoot() . '/src/*', GLOB_ONLYDIR) ?: [] as $path) {
            $modules[] = basename($path);
        }

        sort($modules);

        return $modules;
    }

    private static function serverRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
