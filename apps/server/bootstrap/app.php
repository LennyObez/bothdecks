<?php

declare(strict_types=1);

use BothDecks\Shared\Config\ApplicationIdentity;
use BothDecks\Shared\Module\ModuleRegistry;
use Pulsar\Config\ConfigManager;
use Pulsar\Core\Kernel;

/**
 * Build the application.
 *
 * The front controller, the console entry point and the integration tests all go through here, so a service
 * that is missing a binding fails in the test suite rather than in production. Constructor injection resolves
 * at dispatch time, not at compile time: nothing but an integration test that actually dispatches a request
 * can prove a route works.
 *
 * The kernel is given the project's configuration directory and its environment file. With those, the kernel
 * wires the database, reads `routes/web.php` itself during boot, and resolves every `DB_*` variable with the
 * process environment winning over the file. The router is the kernel's to fill from that file; this file
 * does not touch it.
 *
 * The optional identity override exists so a test can render the product under a different name and prove the
 * rename really is a configuration change. It is the only seam of its kind, and it takes a fully built value
 * object rather than raw configuration, so production has no path that bypasses validation.
 *
 * @return callable(?ApplicationIdentity): Kernel
 */
return static function (?ApplicationIdentity $identity = null): Kernel {
    $root = dirname(__DIR__);

    $configManager = new ConfigManager(
        configPath: $root . '/config',
        envFilePath: $root . '/.env',
    );

    $kernel = new Kernel(configManager: $configManager);

    if ($identity === null) {
        $identityConfig = require $root . '/config/identity.php';

        if (!is_array($identityConfig)) {
            throw new LogicException('config/identity.php must return an array.');
        }

        $identity = ApplicationIdentity::fromConfig($identityConfig);
    }

    $kernel->container()->instance(ApplicationIdentity::class, $identity);

    // Every module registers its bindings here, before the kernel boots, so a request and a console command
    // resolve from the same container. The list of modules is the registry's; nothing is discovered.
    ModuleRegistry::register($kernel->container());

    return $kernel;
};
