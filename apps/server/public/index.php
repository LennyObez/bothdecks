<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * The only PHP file the web server executes. Everything else is reached through the router. The application is
 * assembled in bootstrap/app.php so that the integration tests exercise the same wiring this file does.
 */

/*
 * Project root, before anything can need it.
 *
 * The framework's path helpers resolve against this rather than the process working directory, which differs
 * between a web server, a worker and `php -S -t public`. Only set when unset, so a pool environment, a unit
 * file or a container can override it without editing this file.
 */
$basePath = dirname(__DIR__);

if (getenv('PULSAR_BASE_PATH') === false || getenv('PULSAR_BASE_PATH') === '') {
    putenv('PULSAR_BASE_PATH=' . $basePath);
}

/*
 * Error display, before anything can fail.
 *
 * Left to php.ini, a distribution default of On turns any uncaught error into a page carrying the exception
 * class, its message, absolute source paths and the call stack. That page is appended to whatever was already
 * written, so the response can even leave with a 200. Only the real process environment opts back in; the
 * dotenv file has not been read yet and must not decide this.
 */
$debug = in_array(strtolower((string) getenv('APP_DEBUG')), ['1', 'true', 'on', 'yes'], true);

ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

$autoload = $basePath . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    error_log("Dependencies are not installed. Run 'composer install' in apps/server.");
    exit(1);
}

require $autoload;

use Pulsar\Core\Kernel;

/*
 * Nothing below may reach the client as an unhandled error: an exception escaping to the SAPI is rendered by
 * whatever php.ini decides, which is the one thing the settings above exist to prevent.
 */
try {
    $bootstrap = require $basePath . '/bootstrap/app.php';

    if (!is_callable($bootstrap)) {
        throw new LogicException('bootstrap/app.php must return a callable that builds the kernel.');
    }

    $kernel = $bootstrap();

    if (!$kernel instanceof Kernel) {
        throw new LogicException('bootstrap/app.php must return a callable that builds a Kernel.');
    }

    $kernel->run();
} catch (Throwable $failure) {
    error_log('Unhandled failure in the front controller: ' . $failure);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Internal Server Error';
}
