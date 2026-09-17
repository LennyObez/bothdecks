<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Snapshot;

/**
 * What a snapshot says about itself: which source and release, when it was taken, and the digest of every
 * file, from which the snapshot's own digest is derived.
 *
 * The digest is the dated identity of a snapshot. Two snapshots of the same release taken on different days
 * are the same data if and only if their digests agree, and the version row an import creates carries the
 * digest so the database can always say which bytes it was built from.
 */
final readonly class SnapshotManifest
{
    /**
     * @param array<string, array{records: int, sha256: string, bytes: int}> $files Keyed by file name.
     */
    public function __construct(
        public string $source,
        public string $version,
        public string $api,
        public string $licence,
        public string $attribution,
        public \DateTimeImmutable $takenAt,
        public array $files,
    ) {}

    /**
     * SHA-256 over the source, the release, the address it was read from, the terms it is reused under and
     * the file digests in file-name order, so the value depends on what the data is, where it came from and
     * on the content of every file, and on nothing else: not on the time the manifest was written, not on
     * the machine.
     */
    public function digest(): string
    {
        $files = $this->files;
        ksort($files);

        $material = implode("\n", [$this->source, $this->version, $this->api, $this->licence, $this->attribution]) . "\n";

        foreach ($files as $name => $entry) {
            $material .= $name . ':' . $entry['sha256'] . "\n";
        }

        return hash('sha256', $material);
    }

    public function records(string $file): int
    {
        return $this->files[$file]['records'] ?? throw new \OutOfBoundsException('The snapshot has no file named ' . $file);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'version' => $this->version,
            'api' => $this->api,
            'licence' => $this->licence,
            'attribution' => $this->attribution,
            'taken_at' => $this->takenAt->format(\DateTimeInterface::RFC3339),
            'files' => $this->files,
            'digest' => $this->digest(),
        ];
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['source', 'version', 'api', 'licence', 'attribution', 'taken_at', 'digest'] as $key) {
            if (!\is_string($data[$key] ?? null) || $data[$key] === '') {
                throw new \InvalidArgumentException('The manifest lacks a non-empty "' . $key . '".');
            }
        }

        $takenAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339, $data['taken_at']);

        if ($takenAt === false) {
            throw new \InvalidArgumentException('The manifest\'s taken_at is not an RFC 3339 timestamp.');
        }

        $rawFiles = $data['files'] ?? null;

        if (!\is_array($rawFiles) || $rawFiles === []) {
            throw new \InvalidArgumentException('The manifest lists no files.');
        }

        $files = [];

        foreach ($rawFiles as $name => $entry) {
            if (!\is_string($name) || !\is_array($entry)
                || !\is_int($entry['records'] ?? null) || !\is_string($entry['sha256'] ?? null) || !\is_int($entry['bytes'] ?? null)
            ) {
                throw new \InvalidArgumentException('The manifest entry for ' . var_export($name, true) . ' is malformed.');
            }

            $files[$name] = ['records' => $entry['records'], 'sha256' => $entry['sha256'], 'bytes' => $entry['bytes']];
        }

        $manifest = new self(
            $data['source'],
            $data['version'],
            $data['api'],
            $data['licence'],
            $data['attribution'],
            $takenAt,
            $files,
        );

        if ($manifest->digest() !== $data['digest']) {
            throw new \InvalidArgumentException('The manifest\'s digest does not match its file digests; the manifest was edited.');
        }

        return $manifest;
    }
}
