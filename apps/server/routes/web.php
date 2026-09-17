<?php

declare(strict_types=1);

use BothDecks\Shared\Module\ModuleRegistry;
use Pulsar\Routing\Router;

/**
 * Web and API routes.
 *
 * Every route belongs to a module and is registered by that module; this file only hands the router to the
 * registry. A route that is registered nowhere else does not exist, which is what keeps the list of modules
 * the one place that says what the application serves.
 */
return static function (Router $router): void {
    ModuleRegistry::routes($router);
};
