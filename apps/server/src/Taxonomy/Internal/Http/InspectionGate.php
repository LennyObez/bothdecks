<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Http;

use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Pulsar\Http\Message\Response;

/**
 * Who may see the inspection screen, until accounts exist.
 *
 * With no token configured the screen is closed: the routes answer 404 and render nothing of the screen, so
 * a deployment that never set a token exposes no data; the path itself is in the source and is not a
 * secret. With a token, a request presents it as the password of HTTP basic authentication, which every
 * browser knows how to ask for; the user name is not read. The comparison does not depend on where two
 * strings of equal length differ, and a wrong token is answered without saying which part was wrong.
 */
final readonly class InspectionGate implements MiddlewareInterface
{
    private const string REALM = 'Taxonomy inspection';

    public function __construct(
        private TaxonomyConfig $config,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->config->inspectionToken === '') {
            return Response::html('<!doctype html><title>Not found</title><h1>Not found</h1>', 404);
        }

        if (!$this->presentsTheToken($request)) {
            return Response::html('<!doctype html><title>Authentication required</title><h1>Authentication required</h1>', 401)
                ->withHeader('WWW-Authenticate', 'Basic realm="' . self::REALM . '", charset="UTF-8"');
        }

        return $handler->handle($request);
    }

    private function presentsTheToken(ServerRequestInterface $request): bool
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($header, 6), true);

        if ($decoded === false) {
            return false;
        }

        $separator = strpos($decoded, ':');

        if ($separator === false) {
            return false;
        }

        return hash_equals($this->config->inspectionToken, substr($decoded, $separator + 1));
    }
}
