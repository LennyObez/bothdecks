<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: the declared PHP extensions cover what the dependencies need, and every environment installs them.
 *
 * A missing extension is not a subtle defect. `composer install` refuses to resolve, so no gate runs at all and
 * the pipeline is red before it has measured anything, while a developer whose machine happens to have the
 * extension sees nothing wrong. The same applies to a contributor following the setup guide.
 *
 * Three sources must therefore agree: what the installed dependencies require, what this application declares,
 * and what each continuous-integration workflow installs. This test is the only thing that keeps them in step.
 */
#[Group('guarantee')]
final class DeclaredExtensionsCoverWhatIsNeededTest extends TestCase
{
    public function testEveryExtensionADependencyRequiresIsDeclaredHere(): void
    {
        // Arrange
        $declared = self::declaredExtensions();

        // Act
        $required = self::extensionsRequiredByDependencies();
        $undeclared = array_values(array_diff($required, $declared));

        // Assert
        self::assertSame(
            [],
            $undeclared,
            "These extensions are required by an installed dependency but declared nowhere in composer.json.\n"
            . "Without them `composer install` refuses to resolve and no gate runs:\n  - "
            . implode("\n  - ", $undeclared),
        );
    }

    public function testEveryWorkflowInstallsEveryDeclaredExtension(): void
    {
        // A workflow that installs less than the manifest declares fails at the install step, before any gate
        // has measured anything: the most expensive possible moment to discover a typo.

        // Arrange
        $declared = self::declaredExtensions();
        $missing = [];

        // Act
        foreach (self::workflowExtensionLists() as $workflow => $installed) {
            foreach (array_diff($declared, $installed) as $extension) {
                $missing[] = $workflow . ' does not install ' . $extension;
            }
        }

        // Assert
        self::assertSame([], $missing, implode("\n", $missing));
    }

    public function testTheSetupGuideListsEveryDeclaredExtension(): void
    {
        // Arrange
        $declared = self::declaredExtensions();
        $guide = file_get_contents(self::repositoryRoot() . '/docs/getting-started.md');

        self::assertIsString($guide, 'docs/getting-started.md must be readable.');

        // Act
        $absent = array_values(array_filter(
            $declared,
            static fn(string $extension): bool => !str_contains($guide, '`' . $extension . '`'),
        ));

        // Assert
        self::assertSame(
            [],
            $absent,
            "docs/getting-started.md omits extensions the application declares, so a contributor following it\n"
            . "cannot install:\n  - " . implode("\n  - ", $absent),
        );
    }

    public function testAtLeastOneWorkflowWasActuallyRead(): void
    {
        // Without this, a change to the workflow layout would make the parity check above pass by finding no
        // workflow to compare against.

        // Act
        $lists = self::workflowExtensionLists();

        // Assert
        self::assertNotEmpty($lists, 'No workflow declared a PHP extension list; the parity check is inert.');

        foreach ($lists as $workflow => $installed) {
            self::assertNotEmpty($installed, $workflow . ' declared an empty extension list.');
        }
    }

    /**
     * Extension names declared by this application, without the `ext-` prefix.
     *
     * @return list<string>
     */
    private static function declaredExtensions(): array
    {
        return self::extensionsFromRequire(self::readJson(self::serverRoot() . '/composer.json'));
    }

    /**
     * Extension names every installed dependency requires, without the `ext-` prefix.
     *
     * Read from the lock file rather than from the vendor tree, so the check does not depend on which packages
     * happen to be installed on the machine running it.
     *
     * @return list<string>
     */
    private static function extensionsRequiredByDependencies(): array
    {
        $lock = self::readJson(self::serverRoot() . '/composer.lock');

        $packages = $lock['packages'] ?? null;

        if (!\is_array($packages)) {
            self::fail('composer.lock must list packages.');
        }

        $required = [];

        foreach ($packages as $package) {
            if (\is_array($package)) {
                $required = [...$required, ...self::extensionsFromRequire($package)];
            }
        }

        $unique = array_values(array_unique($required));
        sort($unique);

        return $unique;
    }

    /**
     * Extension names from a manifest's `require` section, without the `ext-` prefix.
     *
     * The parameter is deliberately typed as an arbitrary array: it is fed decoded JSON, whose shape nothing
     * guarantees, and every key is validated below rather than assumed.
     *
     * @param array<mixed, mixed> $manifest
     *
     * @return list<string>
     */
    private static function extensionsFromRequire(array $manifest): array
    {
        $require = $manifest['require'] ?? [];

        if (!\is_array($require)) {
            return [];
        }

        $extensions = [];

        foreach (array_keys($require) as $name) {
            if (\is_string($name) && str_starts_with($name, 'ext-')) {
                $extensions[] = substr($name, 4);
            }
        }

        sort($extensions);

        return $extensions;
    }

    /**
     * Extension lists declared by each workflow that sets PHP up.
     *
     * @return array<string, list<string>>
     */
    private static function workflowExtensionLists(): array
    {
        $lists = [];

        foreach (glob(self::repositoryRoot() . '/.github/workflows/*.yml') ?: [] as $path) {
            $contents = file_get_contents($path);

            if (!\is_string($contents)) {
                continue;
            }

            if (preg_match('/^\s*extensions:\s*(.+)$/m', $contents, $matches) !== 1) {
                continue;
            }

            $names = array_values(array_filter(array_map(
                static fn(string $name): string => trim($name),
                explode(',', $matches[1]),
            )));

            sort($names);
            $lists[basename($path)] = $names;
        }

        return $lists;
    }

    /**
     * @return array<string, mixed>
     */
    private static function readJson(string $path): array
    {
        $raw = file_get_contents($path);

        if (!\is_string($raw)) {
            self::fail(\sprintf('Could not read %s.', $path));
        }

        $decoded = json_decode($raw, true);

        if (!\is_array($decoded)) {
            self::fail(\sprintf('%s is not a JSON object.', $path));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function serverRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
