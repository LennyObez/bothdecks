<?php

declare(strict_types=1);

namespace BothDecks\Shared\Http;

use BothDecks\Shared\Config\ApplicationIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pulsar\Http\Message\Response;

/**
 * Liveness endpoint.
 *
 * Reports that the application booted and can read its own identity. It deliberately performs no dependency
 * check: a liveness probe that fails because a downstream service is slow causes the restart loop it was meant
 * to prevent. Readiness, which does check dependencies, is a separate concern added with the first dependency.
 */
final readonly class HealthController
{
    public function __construct(
        private ApplicationIdentity $identity,
    ) {}

    /**
     * The kernel injects the request only when the first parameter is typed as a PSR-7 server request; a
     * framework-specific request type is not one, and the handler is then called with no arguments at all.
     *
     * @param array<string, string> $params
     */
    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return Response::json([
            'status' => 'ok',
            'application' => $this->identity->name(),
            'environment' => $this->identity->environment(),
        ]);
    }
}
