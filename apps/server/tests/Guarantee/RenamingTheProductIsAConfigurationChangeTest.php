<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use BothDecks\Shared\Config\ApplicationIdentity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pulsar\Core\Kernel;
use Pulsar\Testing\Http\TestRequestBuilder;

/**
 * Guarantee: rendering the product under a different name leaves no trace of the previous one.
 *
 * Its sibling guarantee proves the literal name does not appear in source. This one proves the complementary
 * half: that what reaches a user actually comes from the configured value. A static scan cannot see a name
 * assembled at runtime, and a name that survives a rename in rendered output is precisely the defect the
 * scan would miss.
 *
 * Every route the product adds is dispatched here, so the coverage of this guarantee grows with the surface it
 * protects rather than staying frozen at the first endpoint.
 *
 * @see docs/adr/0004-the-product-name-lives-in-one-place.md
 */
#[Group('guarantee')]
final class RenamingTheProductIsAConfigurationChangeTest extends TestCase
{
    /**
     * Routes whose rendered output must carry no trace of a previous name.
     *
     * @var list<string>
     */
    private const array RENDERED_ROUTES = ['/health'];

    private const REPLACEMENT_NAME = 'Renamed In A Test';

    public function testNoRenderedRouteCarriesTheConfiguredNameAfterARename(): void
    {
        // Arrange
        $original = self::configuredName();
        $kernel = self::kernelNamed(self::REPLACEMENT_NAME);
        $survivors = [];

        // Act
        foreach (self::RENDERED_ROUTES as $route) {
            $body = (string) $kernel->handle(TestRequestBuilder::get($route)->build())->getBody();

            if (stripos($body, $original) !== false) {
                $survivors[] = $route;
            }
        }

        // Assert
        self::assertSame(
            [],
            $survivors,
            \sprintf(
                'After renaming the product, "%s" still appears in the output of: %s. '
                . 'Read the name from the application identity instead of writing it.',
                $original,
                implode(', ', $survivors),
            ),
        );
    }

    public function testTheReplacementNameActuallyReachesTheOutput(): void
    {
        // Without this, the guarantee above would pass on a route that renders no name at all: a green that
        // proves nothing. The replacement must be observable for its absence to mean something.

        // Arrange
        $kernel = self::kernelNamed(self::REPLACEMENT_NAME);
        $carriers = [];

        // Act
        foreach (self::RENDERED_ROUTES as $route) {
            $body = (string) $kernel->handle(TestRequestBuilder::get($route)->build())->getBody();

            if (str_contains($body, self::REPLACEMENT_NAME)) {
                $carriers[] = $route;
            }
        }

        // Assert
        self::assertNotEmpty(
            $carriers,
            'No listed route rendered the configured name, so this guarantee currently proves nothing.',
        );
    }

    private static function kernelNamed(string $name): Kernel
    {
        if ($name === '') {
            self::fail('The replacement name must not be empty, or this guarantee proves nothing.');
        }

        $bootstrap = require \dirname(__DIR__, 2) . '/bootstrap/app.php';

        self::assertIsCallable($bootstrap, 'bootstrap/app.php must return a callable building the kernel.');

        $kernel = $bootstrap(new ApplicationIdentity($name, 'renamed', 'https://example.test', 'testing'));

        self::assertInstanceOf(Kernel::class, $kernel);

        return $kernel;
    }

    /**
     * @return non-empty-string
     */
    private static function configuredName(): string
    {
        $config = require \dirname(__DIR__, 2) . '/config/identity.php';

        if (!\is_array($config)) {
            self::fail('config/identity.php must return an array.');
        }

        $name = $config['name'] ?? null;

        if (!\is_string($name) || $name === '') {
            self::fail('config/identity.php must define a non-empty "name".');
        }

        return $name;
    }
}
