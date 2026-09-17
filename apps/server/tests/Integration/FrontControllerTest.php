<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The front controller serves a real HTTP request.
 *
 * Every other test reaches the application through `bootstrap/app.php` and the kernel's `handle()`. The front
 * controller takes a different path: it sets the project root and the error-display policy, then runs `run()`,
 * which writes status, headers and body to the output stream. Nothing else exercises that path, so a defect in
 * it (a header sent too late, output emitted before the response, a fatal during autoload) would reach
 * production with a green suite behind it.
 *
 * This is the only test that starts a server. It is deliberately narrow, and it uses cURL rather than the
 * stream wrappers so it depends on an extension the application already declares instead of on the
 * `allow_url_fopen` setting, which a hardened deployment turns off.
 */
#[CoversNothing]
final class FrontControllerTest extends TestCase
{
    private const int BOOT_TIMEOUT_SECONDS = 10;

    /** @var resource|null */
    private $server;

    /** @var array<int, resource> */
    private array $pipes = [];

    private int $port = 0;

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) {
            if (\is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->pipes = [];

        if (\is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        $this->server = null;
    }

    public function testTheFrontControllerAnswersOverHttp(): void
    {
        // Arrange
        $this->startServer();

        // Act
        $response = $this->request('/health');

        // Assert
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('"status":"ok"', $response['body']);
        self::assertStringNotContainsString('Internal Server Error', $response['body']);
    }

    public function testTheFrontControllerSendsAJsonContentType(): void
    {
        // Status and headers are written by the front controller's own output path, not by the kernel's
        // response object, so they are asserted here rather than in the handler-level tests.

        // Arrange
        $this->startServer();

        // Act
        $response = $this->request('/health');

        // Assert
        self::assertStringContainsString('application/json', strtolower($response['headers']));
    }

    public function testAnUnknownRouteIsNotFoundAndLeaksNothing(): void
    {
        // A 404 rendered by the framework's error page must not carry a stack trace or an absolute path: with
        // display_errors left to php.ini, a distribution default of On turns any failure into a disclosure.

        // Arrange
        $this->startServer();

        // Act
        $response = $this->request('/no-such-route');

        // Assert
        // The repository root is derived rather than written down: the assertion then holds on any machine,
        // and this file carries no absolute path of its own.
        self::assertSame(404, $response['status']);
        self::assertStringNotContainsString(\dirname(__DIR__, 4), $response['body']);
        self::assertStringNotContainsString('Stack trace', $response['body']);
        self::assertStringNotContainsString('#0 ', $response['body']);
    }

    /**
     * @return array{status: int, headers: string, body: string}
     */
    private function request(string $path): array
    {
        $response = $this->tryRequest($path);

        self::assertIsArray(
            $response,
            \sprintf("The front controller did not answer %s.\n%s", $path, $this->drainDiagnostics()),
        );

        return $response;
    }

    private function startServer(): void
    {
        $root = \dirname(__DIR__, 2);
        $this->port = self::freePort();

        // The child runs with the production posture whatever the developer's own environment file says: the
        // process environment wins over the file, so a machine whose `.env` switches debugging on still
        // exercises the pages a visitor would see. What this test asserts about a 404 is only true of those.
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, '-t', $root . '/public'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            ['APP_ENV' => 'production', 'APP_DEBUG' => '0', 'PATH' => (string) getenv('PATH')],
        );

        self::assertIsResource($process, 'Could not start the built-in server.');

        $this->server = $process;
        $this->pipes = $pipes;

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $deadline = microtime(true) + self::BOOT_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            // A dead child must fail immediately with its own diagnostics rather than after the full timeout,
            // and a port that answers while the child is dead belongs to someone else.
            $status = proc_get_status($process);

            if ($status['running'] === false) {
                self::fail(\sprintf(
                    "The built-in server exited with code %d before answering.\n%s",
                    $status['exitcode'],
                    $this->drainDiagnostics(),
                ));
            }

            // Readiness is proved with a real request rather than a bare connection. The built-in server
            // handles one connection at a time, and an opened-then-closed socket carrying no request is work
            // it has to unwind before it can serve anything else.
            if ($this->tryRequest('/health') !== null) {
                return;
            }

            usleep(100_000);
        }

        self::fail(\sprintf(
            "The built-in server did not answer within %d seconds.\n%s",
            self::BOOT_TIMEOUT_SECONDS,
            $this->drainDiagnostics(),
        ));
    }

    /**
     * One request attempt, or null when the server is not answering yet.
     *
     * @return array{status: int, headers: string, body: string}|null
     */
    private function tryRequest(string $path): ?array
    {
        $handle = curl_init($this->url($path));

        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);

        $raw = curl_exec($handle);

        if (!\is_string($raw)) {
            return null;
        }

        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);

        return [
            'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            'headers' => substr($raw, 0, $headerSize),
            'body' => substr($raw, $headerSize),
        ];
    }

    private function drainDiagnostics(): string
    {
        if (!isset($this->pipes[2]) || !\is_resource($this->pipes[2])) {
            return '(no diagnostics captured)';
        }

        return (string) stream_get_contents($this->pipes[2]);
    }

    /**
     * Ask the kernel for a free port, rather than hoping a fixed one is free.
     *
     * A hard-coded port makes the test fail when anything else on the machine holds it, and, worse, lets it
     * pass against a stranger's service that happens to answer there.
     */
    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        self::assertIsResource($socket, 'Could not reserve a port: ' . $error);

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        self::assertIsString($name, 'Could not read back the reserved port.');

        $separator = strrpos($name, ':');

        self::assertIsInt($separator);

        $port = (int) substr($name, $separator + 1);

        self::assertGreaterThan(0, $port);

        return $port;
    }

    private function url(string $path): string
    {
        return \sprintf('http://127.0.0.1:%d%s', $this->port, $path);
    }
}
