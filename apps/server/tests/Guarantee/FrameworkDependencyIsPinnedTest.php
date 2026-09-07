<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: the framework dependency is pinned to one commit, never to a range.
 *
 * The framework is at release-candidate stage, so its non-stable surface can move. A version range would let
 * an unrelated `composer update` change the foundation of the product without anyone deciding to. Moving it is
 * a decision, and a decision leaves a diff.
 *
 * @see docs/adr/0002-pulsar-as-backend-and-web-renderer.md
 */
#[Group('guarantee')]
final class FrameworkDependencyIsPinnedTest extends TestCase
{
    private const string PACKAGE = 'pulsar/framework';

    public function testTheRequirementNamesAnExactCommit(): void
    {
        // Arrange
        $constraint = self::declaredConstraint();

        // Act
        $hasCommitPin = preg_match('/#[0-9a-f]{40}$/', $constraint) === 1;

        // Assert
        self::assertTrue(
            $hasCommitPin,
            \sprintf(
                'The %s requirement must end with "#" followed by a full 40-character commit hash. Found: %s',
                self::PACKAGE,
                $constraint,
            ),
        );
    }

    public function testTheRequirementUsesNoVersionRangeOperator(): void
    {
        // A range would let an unrelated update move the foundation of the product.

        // Arrange
        $constraint = self::declaredConstraint();
        $rangeOperators = ['^', '~', '>', '<', '*', '||', ' - '];

        // Act
        $found = array_values(array_filter(
            $rangeOperators,
            static fn(string $operator): bool => str_contains($constraint, $operator),
        ));

        // Assert
        self::assertSame(
            [],
            $found,
            \sprintf('The %s requirement must name a commit, not a range. Found: %s', self::PACKAGE, $constraint),
        );
    }

    public function testTheInstalledCommitMatchesTheDeclaredOne(): void
    {
        // A lock file that has drifted from the requirement means the machine running this test is not
        // running the code the repository declares.

        // Arrange
        $constraint = self::declaredConstraint();
        $declared = substr($constraint, strrpos($constraint, '#') + 1);

        // Act
        $locked = self::lockedReference();

        // Assert
        self::assertSame($declared, $locked, 'composer.lock does not hold the commit composer.json declares.');
    }

    /**
     * @return non-empty-string
     */
    private static function declaredConstraint(): string
    {
        $manifest = self::readJson(self::serverRoot() . '/composer.json');

        $require = $manifest['require'] ?? null;

        if (!\is_array($require)) {
            self::fail('composer.json must declare a "require" section.');
        }

        $constraint = $require[self::PACKAGE] ?? null;

        if (!\is_string($constraint) || $constraint === '') {
            self::fail(\sprintf('composer.json must require %s.', self::PACKAGE));
        }

        return $constraint;
    }

    /**
     * @return non-empty-string
     */
    private static function lockedReference(): string
    {
        $lock = self::readJson(self::serverRoot() . '/composer.lock');

        $packages = $lock['packages'] ?? null;

        if (!\is_array($packages)) {
            self::fail('composer.lock must list packages.');
        }

        foreach ($packages as $package) {
            if (!\is_array($package) || ($package['name'] ?? null) !== self::PACKAGE) {
                continue;
            }

            $source = $package['source'] ?? null;

            if (!\is_array($source)) {
                self::fail(\sprintf('composer.lock holds no source for %s.', self::PACKAGE));
            }

            $reference = $source['reference'] ?? null;

            if (!\is_string($reference) || $reference === '') {
                self::fail(\sprintf('composer.lock holds no source reference for %s.', self::PACKAGE));
            }

            return $reference;
        }

        self::fail(\sprintf('composer.lock does not contain %s.', self::PACKAGE));
    }

    /**
     * @return array<string, mixed>
     */
    private static function readJson(string $path): array
    {
        $raw = file_get_contents($path);
        self::assertIsString($raw, \sprintf('Could not read %s.', $path));

        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded, \sprintf('%s is not a JSON object.', $path));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function serverRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
