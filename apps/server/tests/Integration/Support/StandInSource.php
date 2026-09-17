<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Support;

use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use PHPUnit\Framework\Assert;

/**
 * The stand-in for the source's API (esco-stand-in.php), started on a free port by PHP's built-in server,
 * with the request log and the misbehaviour switch a test needs.
 */
final class StandInSource
{
    private const int BOOT_TIMEOUT_SECONDS = 10;

    /** @var resource|null */
    private $process;

    private function __construct(
        public readonly int $port,
        public readonly string $root,
        public readonly string $log,
        private readonly string $modeFile,
    ) {}

    public static function start(): self
    {
        $root = sys_get_temp_dir() . '/bothdecks-stand-in-' . bin2hex(random_bytes(6));
        mkdir($root, 0o750, true);
        $port = self::freePort();
        $log = $root . '/requests.log';
        $modeFile = $root . '/mode';
        $server = new self($port, $root, $log, $modeFile);

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/esco-stand-in.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            ['STAND_IN_LOG' => $log, 'STAND_IN_MODE' => $modeFile, 'PATH' => (string) getenv('PATH')],
        );

        Assert::assertIsResource($process, 'Could not start the stand-in server.');
        $server->process = $process;

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $deadline = microtime(true) + self::BOOT_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            $probe = @file_get_contents('http://127.0.0.1:' . $port . '/search?type=occupation&limit=1&offset=0&selectedVersion=v1.2.1');

            if (\is_string($probe)) {
                file_put_contents($log, '');

                return $server;
            }

            usleep(100_000);
        }

        Assert::fail('The stand-in server did not answer within ' . self::BOOT_TIMEOUT_SECONDS . ' seconds.');
    }

    /**
     * Make the stand-in misbehave in one named way from now on; see esco-stand-in.php for the names.
     */
    public function misbehave(string $mode): void
    {
        file_put_contents($this->modeFile, $mode);
    }

    public function stop(): void
    {
        if (\is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    /**
     * Every URL the stand-in was asked, in order.
     *
     * @return list<string>
     */
    public function requests(): array
    {
        return array_map(static fn(array $entry): string => $entry[0], $this->entries());
    }

    /**
     * The user agent presented on each request, in order.
     *
     * @return list<string>
     */
    public function userAgents(): array
    {
        return array_map(static fn(array $entry): string => $entry[1], $this->entries());
    }

    /**
     * @return list<array{string, string}>
     */
    private function entries(): array
    {
        $entries = [];

        foreach (file($this->log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $parts = explode("\t", $line, 2);
            $entries[] = [$parts[0], $parts[1] ?? ''];
        }

        return $entries;
    }

    /**
     * The product's configuration pointed at this stand-in, with the fetch parameters given.
     */
    public function config(string $release = 'v1.2.1', int $batchSize = 3, int $concurrency = 2, int $attempts = 2, int $pageSize = 5): TaxonomyConfig
    {
        $base = require \dirname(__DIR__, 3) . '/config/taxonomy.php';
        Assert::assertIsArray($base);
        $source = $base['source'] ?? null;
        Assert::assertIsArray($source);
        $fetch = $base['fetch'] ?? null;
        Assert::assertIsArray($fetch);

        $source['api'] = 'http://127.0.0.1:' . $this->port;
        $source['version'] = $release;
        $fetch['page_size'] = $pageSize;
        $fetch['batch_size'] = $batchSize;
        $fetch['concurrency'] = $concurrency;
        $fetch['attempts'] = $attempts;
        $fetch['timeout_seconds'] = 10;
        $base['source'] = $source;
        $base['fetch'] = $fetch;
        $base['snapshot_path'] = 'snapshots';

        return TaxonomyConfig::fromArray($base, $this->root);
    }

    public function removeTree(): void
    {
        self::remove($this->root);
    }

    private static function remove(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            is_dir($path) ? self::remove($path) : unlink($path);
        }

        rmdir($directory);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        Assert::assertIsResource($socket, 'Could not reserve a port: ' . $error);
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        Assert::assertIsString($name);
        $separator = strrpos($name, ':');
        Assert::assertIsInt($separator);

        return (int) substr($name, $separator + 1);
    }
}
