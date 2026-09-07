<?php

declare(strict_types=1);

namespace BothDecks\Tests\Unit\Shared\Config;

use BothDecks\Shared\Config\ApplicationIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplicationIdentity::class)]
final class ApplicationIdentityTest extends TestCase
{
    public function testItExposesTheConfiguredNameSoNoSurfaceNeedsTheLiteral(): void
    {
        // Arrange
        $config = self::configWith(['name' => 'Rebranded']);

        // Act
        $identity = ApplicationIdentity::fromConfig($config);

        // Assert
        self::assertSame('Rebranded', $identity->name());
    }

    public function testTheTechnicalSlugIsIndependentOfTheDisplayName(): void
    {
        // A rebrand changes the user-visible name; the slug appears in package identifiers and log streams
        // that cannot change after publication, so the two must not be derived from one another.

        // Arrange
        $config = self::configWith(['name' => 'Rebranded', 'slug' => 'bothdecks']);

        // Act
        $identity = ApplicationIdentity::fromConfig($config);

        // Assert
        self::assertSame('Rebranded', $identity->name());
        self::assertSame('bothdecks', $identity->slug());
    }

    public function testEachFieldIsReadBackFromItsOwnConfigurationKey(): void
    {
        // Four constructor parameters of the same type sit next to one another, so a transposed argument would
        // compile, pass a loose test, and surface as the wrong value in an export months later. Every value
        // here is distinct so a swap cannot hide.

        // Arrange
        $config = [
            'name' => 'Display Name',
            'slug' => 'technical-slug',
            'url' => 'https://canonical.example',
            'env' => 'staging',
        ];

        // Act
        $identity = ApplicationIdentity::fromConfig($config);

        // Assert
        self::assertSame('Display Name', $identity->name());
        self::assertSame('technical-slug', $identity->slug());
        self::assertSame('https://canonical.example', $identity->url());
        self::assertSame('staging', $identity->environment());
    }

    #[DataProvider('requiredKeys')]
    public function testItRefusesConfigurationMissingARequiredKey(string $missingKey): void
    {
        // The message must name the offending key: a boot failure that does not say which key is wrong sends
        // the reader searching through every configuration file.

        // Arrange
        $config = self::configWith([]);
        unset($config[$missingKey]);

        // Act
        $caught = self::captureFailure($config);

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $caught);
        self::assertStringContainsString($missingKey, $caught->getMessage());
    }

    #[DataProvider('requiredKeys')]
    public function testItRefusesAnEmptyValueForARequiredKey(string $emptyKey): void
    {
        // An empty product name would render as a blank space in the interface rather than failing loudly,
        // which is the failure mode this check exists to prevent.
        //
        // The message is asserted, not just the failure. An empty URL and an empty environment would each be
        // rejected anyway by the check that follows, but with a message about schemes or about an allowed
        // set, which sends the reader looking for the wrong mistake. Emptiness is reported as emptiness.

        // Arrange
        $config = self::configWith([$emptyKey => '']);

        // Act
        $caught = self::captureFailure($config);

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $caught);
        self::assertStringContainsString($emptyKey, $caught->getMessage());
        self::assertStringContainsString('non-empty', $caught->getMessage());
    }

    #[DataProvider('nonStringValues')]
    public function testItRefusesANonStringValue(mixed $value): void
    {
        // Arrange
        $config = self::configWith(['name' => $value]);

        // Act
        $caught = self::captureFailure($config);

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $caught);
        self::assertStringContainsString('name', $caught->getMessage());
    }

    #[DataProvider('recognisedEnvironments')]
    public function testItAcceptsEveryRecognisedEnvironment(string $environment): void
    {
        // Arrange
        $config = self::configWith(['env' => $environment]);

        // Act
        $identity = ApplicationIdentity::fromConfig($config);

        // Assert
        self::assertSame($environment, $identity->environment());
    }

    #[DataProvider('unrecognisedEnvironments')]
    public function testItRefusesAnUnrecognisedEnvironmentRatherThanTreatingItAsNonProduction(
        string $environment,
    ): void {
        // This is the important one. With an open set, a misspelling answers false to isProduction() and
        // silently relaxes every control that hardens for production. Refusing at construction turns a silent
        // downgrade into a boot failure, which is the only safe direction for this particular mistake.

        // Arrange
        $config = self::configWith(['env' => $environment]);

        // Act
        $caught = self::captureFailure($config);

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $caught);
        self::assertStringContainsString('env', $caught->getMessage());
    }

    public function testOnlyTheProductionEnvironmentIsProduction(): void
    {
        // Arrange
        $production = ApplicationIdentity::fromConfig(self::configWith(['env' => 'production']));
        $staging = ApplicationIdentity::fromConfig(self::configWith(['env' => 'staging']));

        // Act
        $productionIsProduction = $production->isProduction();
        $stagingIsProduction = $staging->isProduction();

        // Assert
        self::assertTrue($productionIsProduction);
        self::assertFalse($stagingIsProduction);
    }

    #[DataProvider('unusableUrls')]
    public function testItRefusesAUrlItCannotBuildAbsoluteLinksFrom(string $url): void
    {
        // The canonical origin is what an export, a notification and the public verification page build their
        // links from, and none of them has a request to resolve a relative value against.

        // Arrange
        $config = self::configWith(['url' => $url]);

        // Act
        $caught = self::captureFailure($config);

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $caught);
        self::assertStringContainsString('url', $caught->getMessage());
    }

    public function testTheConstructorEnforcesTheSameInvariantsAsTheFactory(): void
    {
        // A `non-empty-string` annotation is a claim the language does not enforce, so a caller that builds the
        // object directly (a test, or a future seam) must not be able to produce one the rest of the product
        // could not have produced through configuration.

        // Arrange
        $build = static fn(string $name, string $slug, string $url, string $env): ApplicationIdentity
            => new ApplicationIdentity($name, $slug, $url, $env);

        // Act
        $emptyName = self::capture(static fn() => $build('', 'slug', 'https://example.test', 'local'));
        $emptySlug = self::capture(static fn() => $build('Name', '', 'https://example.test', 'local'));
        $badUrl = self::capture(static fn() => $build('Name', 'slug', 'not-a-url', 'local'));
        $badEnvironment = self::capture(static fn() => $build('Name', 'slug', 'https://example.test', 'prod'));

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $emptyName);
        self::assertInstanceOf(\InvalidArgumentException::class, $emptySlug);
        self::assertInstanceOf(\InvalidArgumentException::class, $badUrl);
        self::assertInstanceOf(\InvalidArgumentException::class, $badEnvironment);
    }

    #[DataProvider('usableUrls')]
    public function testItAcceptsAnAbsoluteHttpUrl(string $url): void
    {
        // Arrange
        $config = self::configWith(['url' => $url]);

        // Act
        $identity = ApplicationIdentity::fromConfig($config);

        // Assert
        self::assertSame($url, $identity->url());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function recognisedEnvironments(): iterable
    {
        yield 'local' => ['local'];
        yield 'testing' => ['testing'];
        yield 'staging' => ['staging'];
        yield 'production' => ['production'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unrecognisedEnvironments(): iterable
    {
        yield 'capitalised' => ['Production'];
        yield 'uppercase' => ['PRODUCTION'];
        yield 'surrounded by space' => [' production '];
        yield 'prefixed' => ['pre-production'];
        yield 'suffixed' => ['production-eu'];
        yield 'unknown word' => ['prod'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableUrls(): iterable
    {
        yield 'relative path' => ['/bothdecks'];
        yield 'host without scheme' => ['example.test'];
        yield 'scheme without host' => ['https://'];
        yield 'unsupported scheme' => ['ftp://example.test'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function usableUrls(): iterable
    {
        yield 'https' => ['https://example.test'];
        yield 'https with port' => ['https://example.test:8443'];
        yield 'http for local use' => ['http://localhost:8080'];
        yield 'with a path' => ['https://example.test/app'];
        // Schemes are case-insensitive per RFC 3986. Without normalisation these are rejected, which would
        // turn a legal configuration value into a boot failure.
        yield 'uppercase scheme' => ['HTTPS://example.test'];
        yield 'mixed-case scheme' => ['HtTpS://example.test'];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonStringValues(): iterable
    {
        yield 'integer' => [42];
        yield 'null' => [null];
        yield 'array' => [['Both Decks']];
        yield 'boolean' => [true];
        yield 'object' => [new \stdClass()];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredKeys(): iterable
    {
        yield 'name' => ['name'];
        yield 'slug' => ['slug'];
        yield 'url' => ['url'];
        yield 'env' => ['env'];
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function captureFailure(array $config): ?\Throwable
    {
        return self::capture(static fn() => ApplicationIdentity::fromConfig($config));
    }

    /**
     * @param callable(): mixed $build
     */
    private static function capture(callable $build): ?\Throwable
    {
        try {
            $build();
        } catch (\Throwable $caught) {
            return $caught;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function configWith(array $overrides): array
    {
        return $overrides + [
            'name' => 'Both Decks',
            'slug' => 'bothdecks',
            'url' => 'https://example.test',
            'env' => 'local',
        ];
    }
}
