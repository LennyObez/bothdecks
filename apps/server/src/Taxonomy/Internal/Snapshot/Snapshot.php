<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Snapshot;

/**
 * A snapshot on disk: one directory holding a manifest and one file of records per concept kind.
 *
 * Records are stored one JSON object per line, in the shape the source returned them, minus the copies of
 * other resources the source embeds in each answer: those are fetched as records of their own, and keeping
 * the copies would triple the size for data the snapshot already holds once.
 *
 * Opening a snapshot verifies every file against the manifest before a single record is read, so an import
 * never runs on a file that was truncated, edited, or half-written by a snapshot that did not finish.
 */
final readonly class Snapshot
{
    public const string OCCUPATIONS = 'occupations.ndjson';
    public const string SKILLS = 'skills.ndjson';
    public const string ISCO_GROUPS = 'isco_groups.ndjson';
    public const string SKILL_GROUPS = 'skill_groups.ndjson';
    public const string MANIFEST = 'manifest.json';

    /** @var list<string> */
    public const array FILES = [self::OCCUPATIONS, self::SKILLS, self::ISCO_GROUPS, self::SKILL_GROUPS];

    private function __construct(
        public string $directory,
        public SnapshotManifest $manifest,
    ) {}

    /**
     * @throws SnapshotException when the directory holds no snapshot, or one that does not match its manifest.
     */
    public static function open(string $directory): self
    {
        $manifestPath = $directory . '/' . self::MANIFEST;

        if (!is_file($manifestPath)) {
            throw new SnapshotException('No snapshot at ' . $directory . ': the manifest is missing. Take one with taxonomy:snapshot.');
        }

        $raw = file_get_contents($manifestPath);

        if (!\is_string($raw)) {
            throw new SnapshotException('The manifest at ' . $manifestPath . ' cannot be read.');
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new SnapshotException('The manifest at ' . $manifestPath . ' is not a JSON object.');
        }

        $manifest = SnapshotManifest::fromArray($decoded);

        foreach (self::FILES as $file) {
            $path = $directory . '/' . $file;

            if (!isset($manifest->files[$file])) {
                throw new SnapshotException('The manifest does not list ' . $file);
            }

            if (!is_file($path)) {
                throw new SnapshotException('The snapshot file ' . $file . ' is listed in the manifest and missing from disk.');
            }

            $digest = hash_file('sha256', $path);

            if ($digest !== $manifest->files[$file]['sha256']) {
                throw new SnapshotException('The snapshot file ' . $file . ' does not match its digest in the manifest; the snapshot is corrupt.');
            }
        }

        return new self($directory, $manifest);
    }

    /**
     * The records of one file, one at a time, so a file of any size is read in constant memory.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function records(string $file): \Generator
    {
        $handle = fopen($this->directory . '/' . $file, 'rb');

        if ($handle === false) {
            throw new SnapshotException('Cannot open ' . $file);
        }

        try {
            $line = 0;

            while (($raw = fgets($handle)) !== false) {
                $line++;
                $raw = rtrim($raw, "\r\n");

                if ($raw === '') {
                    continue;
                }

                $record = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                if (!\is_array($record)) {
                    throw new SnapshotException(\sprintf('%s line %d is not a JSON object.', $file, $line));
                }

                /** @var array<string, mixed> $record */
                yield $record;
            }
        } finally {
            fclose($handle);
        }
    }
}
