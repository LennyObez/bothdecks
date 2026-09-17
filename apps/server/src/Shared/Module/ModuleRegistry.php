<?php

declare(strict_types=1);

namespace BothDecks\Shared\Module;

use BothDecks\Taxonomy\TaxonomyModule;
use Pulsar\Console\CommandInterface;
use Pulsar\Container\ContainerInterface;
use Pulsar\Routing\Router;

/**
 * The modules the application is made of, in the order they are registered.
 *
 * This is the one class allowed to name every module, and the boundary check exempts it by name for that
 * reason. The list is written rather than discovered: a module found on disk without anyone having decided
 * it exists is a module nobody reviewed. `bootstrap/app.php` registers them; the console lists their commands.
 */
final class ModuleRegistry
{
    /**
     * @var list<class-string<ModuleInterface>>
     */
    private const array MODULES = [
        SharedModule::class,
        TaxonomyModule::class,
    ];

    public static function register(ContainerInterface $container): void
    {
        foreach (self::MODULES as $module) {
            $module::register($container);
        }
    }

    /**
     * @return list<CommandInterface>
     */
    public static function commands(ContainerInterface $container): array
    {
        $commands = [];

        foreach (self::MODULES as $module) {
            foreach ($module::commands() as $class) {
                $command = $container->get($class);

                if (!$command instanceof CommandInterface) {
                    throw new \LogicException(\sprintf('%s lists %s as a console command, and it is not one.', $module, $class));
                }

                $commands[] = $command;
            }
        }

        return $commands;
    }

    public static function routes(Router $router): void
    {
        foreach (self::MODULES as $module) {
            $module::routes($router);
        }
    }

    /**
     * @return list<class-string<ModuleInterface>>
     */
    public static function modules(): array
    {
        return self::MODULES;
    }
}
