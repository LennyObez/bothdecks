<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration;

use BothDecks\Shared\Http\HealthController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pulsar\Core\Kernel;
use Pulsar\Testing\Http\TestRequestBuilder;
use Pulsar\Testing\Http\TestResponse;

/**
 * The application boots, resolves its own services, and answers its routes.
 *
 * This test exists because a suite of unit tests can be entirely green while the application cannot serve a
 * single request: constructor injection of a service the container has no binding for fails at dispatch time,
 * not at compile time, and no unit test goes near it. Every route the product adds gets a test at this level
 * for the same reason.
 */
#[CoversClass(HealthController::class)]
final class HealthEndpointTest extends TestCase
{
    public function testTheApplicationBootsAndAnswersTheHealthRoute(): void
    {
        // Arrange
        $kernel = self::bootedKernel();
        $request = TestRequestBuilder::get('/health')->build();

        // Act
        $response = new TestResponse($kernel->handle($request));

        // Assert
        $response->assertOk()->assertJson();
    }

    public function testTheHealthRouteReportsTheConfiguredApplicationIdentity(): void
    {
        // The endpoint's purpose is to prove the application can read its own configuration, so the payload is
        // asserted against the configuration file rather than against a literal repeated here.

        // Arrange
        $kernel = self::bootedKernel();
        $request = TestRequestBuilder::get('/health')->build();

        // Act
        $response = new TestResponse($kernel->handle($request));

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('application', self::configuredValue('name'))
            ->assertJsonPath('environment', self::configuredValue('env'));
    }

    public function testAnUnknownRouteIsNotFound(): void
    {
        // Confirms the router is actually dispatching rather than a catch-all answering everything, which
        // would make the assertions above meaningless.

        // Arrange
        $kernel = self::bootedKernel();
        $request = TestRequestBuilder::get('/no-such-route')->build();

        // Act
        $response = new TestResponse($kernel->handle($request));

        // Assert
        $response->assertNotFound();
    }

    /**
     * Read a value from the application configuration, so the payload is asserted against the file the
     * application actually reads rather than against a literal repeated in this test.
     */
    private static function configuredValue(string $key): string
    {
        $config = require \dirname(__DIR__, 2) . '/config/identity.php';

        self::assertIsArray($config, 'config/identity.php must return an array.');

        $value = $config[$key] ?? null;

        self::assertIsString($value, \sprintf('config/identity.php must define a string "%s".', $key));

        return $value;
    }

    private static function bootedKernel(): Kernel
    {
        $bootstrap = require \dirname(__DIR__, 2) . '/bootstrap/app.php';

        self::assertIsCallable($bootstrap, 'bootstrap/app.php must return a callable building the kernel.');

        $kernel = $bootstrap();

        self::assertInstanceOf(Kernel::class, $kernel);

        return $kernel;
    }
}
