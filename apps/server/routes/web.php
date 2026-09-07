<?php

declare(strict_types=1);

use BothDecks\Shared\Http\HealthController;
use Pulsar\Routing\Router;

/**
 * Web and API routes.
 *
 * Routes are registered per module as the product grows; this file wires the module route files together.
 * Only the health endpoint exists at this milestone.
 */
return static function (Router $router): void {
    $router->get('/health', [HealthController::class, 'show'], 'health');
};
