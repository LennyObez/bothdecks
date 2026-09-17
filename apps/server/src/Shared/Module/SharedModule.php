<?php

declare(strict_types=1);

namespace BothDecks\Shared\Module;

use BothDecks\Shared\Http\HealthController;
use BothDecks\Shared\I18n\ProductLocales;
use BothDecks\Shared\Time\SystemClock;
use Psr\Clock\ClockInterface;
use Pulsar\Config\I18nConfig;
use Pulsar\Container\ContainerInterface;
use Pulsar\Routing\Router;

/**
 * The bindings every other module may rely on.
 *
 * The identity is bound by `bootstrap/app.php` itself, because a test overrides it there to prove the rename
 * is a configuration change; everything else the shared module owns is bound here.
 */
final class SharedModule implements ModuleInterface
{
    public static function register(ContainerInterface $container): void
    {
        $container->singleton(ClockInterface::class, SystemClock::class);

        // Resolved lazily on purpose: the framework binds its typed locale configuration while the kernel
        // boots, after the modules have registered, and this reads that object rather than the file again.
        $container->singleton(
            ProductLocales::class,
            static fn(ContainerInterface $c): ProductLocales => ProductLocales::fromFramework($c->get(I18nConfig::class)),
        );
    }

    public static function commands(): array
    {
        return [];
    }

    public static function routes(Router $router): void
    {
        $router->get('/health', [HealthController::class, 'show'], 'health');
    }
}
