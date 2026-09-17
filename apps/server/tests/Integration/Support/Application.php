<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Support;

use PHPUnit\Framework\Assert;
use Pulsar\Core\Kernel;

/**
 * Builds the application the way production builds it.
 *
 * Every integration test goes through `bootstrap/app.php` and nothing else, so a binding that is missing
 * fails here rather than on the first request a visitor makes. The kernel is built fresh for each call: a
 * kernel shared between tests would carry one test's container state into the next.
 */
final class Application
{
    public static function kernel(): Kernel
    {
        $bootstrap = require \dirname(__DIR__, 3) . '/bootstrap/app.php';

        Assert::assertIsCallable($bootstrap, 'bootstrap/app.php must return a callable building the kernel.');

        $kernel = $bootstrap();

        Assert::assertInstanceOf(Kernel::class, $kernel);

        return $kernel;
    }

    public static function bootedKernel(): Kernel
    {
        $kernel = self::kernel();
        $kernel->boot();

        return $kernel;
    }
}
