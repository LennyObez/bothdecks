<?php

declare(strict_types=1);

use BothDecks\Shared\Config\ApplicationIdentity;
use Pulsar\Core\Kernel;

/**
 * Build the application.
 *
 * The front controller and the integration tests both go through here, so a service that is missing a binding
 * fails in the test suite rather than in production. Constructor injection resolves at dispatch time, not at
 * compile time: nothing but an integration test that actually dispatches a request can prove a route works.
 *
 * The optional identity override exists so a test can render the product under a different name and prove the
 * rename really is a configuration change. It is the only seam of its kind, and it takes a fully built value
 * object rather than raw configuration, so production has no path that bypasses validation.
 *
 * @return callable(?ApplicationIdentity): Kernel
 */
return static function (?ApplicationIdentity $identity = null): Kernel {
    $root = dirname(__DIR__);

    $kernel = new Kernel();

    if ($identity === null) {
        $identityConfig = require $root . '/config/identity.php';

        if (!is_array($identityConfig)) {
            throw new LogicException('config/identity.php must return an array.');
        }

        $identity = ApplicationIdentity::fromConfig($identityConfig);
    }

    $kernel->container()->instance(ApplicationIdentity::class, $identity);

    $routeFile = $root . '/routes/web.php';
    $registerRoutes = require $routeFile;

    if (!is_callable($registerRoutes)) {
        throw new LogicException(sprintf('%s must return a callable that registers routes.', $routeFile));
    }

    $registerRoutes($kernel->router());

    return $kernel;
};
