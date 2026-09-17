<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Support;

use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use BothDecks\Taxonomy\Internal\Snapshot\SnapshotManifest;

/**
 * A modified copy of the fixture snapshot in a temporary directory, with a manifest that matches it.
 *
 * Tests that need a snapshot with a hole, a concept fewer, or a label changed build one here rather than
 * committing a second fixture per case. The copy is deleted by the test that made it.
 */
final readonly class FixtureCopy
{
    private function __construct(
        public string $directory,
    ) {}

    /**
     * The callable receives each file name and record, and returns the record to keep, possibly changed, or
     * null to drop it.
     *
     * @param callable(string, array<string, mixed>): (array<string, mixed>|null) $rewrite
     */
    public static function rewriting(callable $rewrite): self
    {
        $directory = sys_get_temp_dir() . '/bothdecks-fixture-' . bin2hex(random_bytes(6));
        mkdir($directory, 0o750, true);

        foreach (Snapshot::FILES as $file) {
            $lines = '';

            foreach (Snapshot::open(ImportedFixture::snapshot())->records($file) as $record) {
                $kept = $rewrite($file, $record);

                if ($kept !== null) {
                    $lines .= json_encode($kept, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
                }
            }

            file_put_contents($directory . '/' . $file, $lines);
        }

        self::writeManifest($directory);

        return new self($directory);
    }

    /**
     * The fixture without the occupation carrying this code, and without any link to it.
     */
    public static function withoutOccupation(string $code): self
    {
        $uri = null;

        foreach (Snapshot::open(ImportedFixture::snapshot())->records(Snapshot::OCCUPATIONS) as $record) {
            if (($record['code'] ?? null) === $code) {
                $uri = $record['uri'] ?? null;
            }
        }

        if (!\is_string($uri)) {
            throw new \InvalidArgumentException('The fixture holds no occupation ' . $code);
        }

        return self::rewriting(static function (string $file, array $record) use ($uri): ?array {
            if (($record['uri'] ?? null) === $uri) {
                return null;
            }

            return $record;
        });
    }

    public function remove(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }

        rmdir($this->directory);
    }

    /**
     * A manifest for whatever the directory now holds, keeping everything but the file digests.
     */
    private static function writeManifest(string $directory): void
    {
        $original = Snapshot::open(ImportedFixture::snapshot())->manifest;
        $files = [];

        foreach (Snapshot::FILES as $file) {
            $content = (string) file_get_contents($directory . '/' . $file);
            $files[$file] = ['records' => substr_count($content, "\n"), 'sha256' => hash('sha256', $content), 'bytes' => \strlen($content)];
        }

        $manifest = new SnapshotManifest(
            $original->source,
            $original->version,
            $original->api,
            $original->licence,
            $original->attribution,
            $original->takenAt,
            $files,
        );

        file_put_contents($directory . '/' . Snapshot::MANIFEST, json_encode($manifest->toArray(), JSON_THROW_ON_ERROR));
    }
}
