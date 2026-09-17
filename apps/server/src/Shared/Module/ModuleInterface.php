<?php

declare(strict_types=1);

namespace BothDecks\Shared\Module;

use Pulsar\Console\CommandInterface;
use Pulsar\Container\ContainerInterface;
use Pulsar\Routing\Router;

/**
 * What a module tells the composition root about itself.
 *
 * A module is a directory under src/ with a public surface and private internals, fenced by the boundary
 * check. It reaches the application through this interface and nothing else: its bindings are registered
 * here, and its console commands are listed here. Nothing is discovered by scanning a directory, so a class
 * that exists without anyone having decided it is wired is a class that is not wired.
 */
interface ModuleInterface
{
    /**
     * Bind the module's services. Called once, before the kernel boots, with the container every request
     * and every command will resolve from.
     */
    public static function register(ContainerInterface $container): void;

    /**
     * The module's console commands, in the order the console lists them. Each is resolved through the
     * container, so a command takes what it needs in its constructor.
     *
     * @return list<class-string<CommandInterface>>
     */
    public static function commands(): array;

    /**
     * Register the module's routes. Called once while the kernel boots, from the routes file, with the
     * application's router; handlers are resolved through the container at dispatch time.
     */
    public static function routes(Router $router): void;
}
