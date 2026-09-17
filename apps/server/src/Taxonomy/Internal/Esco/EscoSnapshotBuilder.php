<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Esco;

use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;
use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use BothDecks\Taxonomy\Internal\Snapshot\SnapshotException;
use BothDecks\Taxonomy\Internal\Snapshot\SnapshotManifest;
use Psr\Clock\ClockInterface;

/**
 * Takes a snapshot of the configured release: every occupation, skill and group, as the source returns them.
 *
 * Concepts are enumerated through the search endpoint, which reports a total, and every identifier listed
 * is then fetched in full. A concept the listing announces and the fetch does not return stops the snapshot,
 * as does a total that moves while paging: a snapshot is either complete or it does not exist.
 *
 * Files are written to a scratch directory and moved into place only once the manifest is written, so a
 * snapshot that dies halfway leaves the previous one untouched and nothing an import could mistake for a
 * whole one.
 */
final readonly class EscoSnapshotBuilder
{
    private const string ISCO_SCHEME = 'http://data.europa.eu/esco/concept-scheme/isco';
    private const string SKILL_HIERARCHY_SCHEME = 'http://data.europa.eu/esco/concept-scheme/skills-hierarchy';

    public function __construct(
        private EscoApi $api,
        private TaxonomyConfig $config,
        private ClockInterface $clock,
    ) {}

    /**
     * @param callable(string): void $log Receives one line per step.
     */
    public function build(callable $log): SnapshotManifest
    {
        $target = $this->config->snapshotDirectory();
        $scratch = $this->beside($target, '.partial');

        $this->prepareDirectory($scratch);

        $files = [];

        $files[Snapshot::OCCUPATIONS] = $this->fetchAndWrite(
            $scratch . '/' . Snapshot::OCCUPATIONS,
            'occupation',
            $this->api->listAll('occupation'),
            $log,
            'occupations',
        );

        $files[Snapshot::SKILLS] = $this->fetchAndWrite(
            $scratch . '/' . Snapshot::SKILLS,
            'skill',
            $this->api->listAll('skill'),
            $log,
            'skills',
        );

        $files[Snapshot::ISCO_GROUPS] = $this->fetchAndWrite(
            $scratch . '/' . Snapshot::ISCO_GROUPS,
            'concept',
            $this->api->listAll('concept', self::ISCO_SCHEME),
            $log,
            'occupation groups',
        );

        $files[Snapshot::SKILL_GROUPS] = $this->fetchAndWrite(
            $scratch . '/' . Snapshot::SKILL_GROUPS,
            'concept',
            $this->api->listAll('concept', self::SKILL_HIERARCHY_SCHEME),
            $log,
            'skill groups',
        );

        $manifest = new SnapshotManifest(
            source: $this->config->sourceName,
            version: $this->config->sourceVersion,
            api: $this->config->sourceApi,
            licence: $this->config->sourceLicence,
            attribution: $this->config->sourceAttribution,
            takenAt: $this->clock->now(),
            files: $files,
        );

        $encoded = json_encode($manifest->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($scratch . '/' . Snapshot::MANIFEST, $encoded . "\n") === false) {
            throw new SnapshotException('Could not write the manifest to ' . $scratch);
        }

        $this->replaceDirectory($scratch, $target);

        $log(\sprintf('Snapshot %s written to %s', $manifest->digest(), $target));

        return $manifest;
    }

    /**
     * @param list<string> $listing The identifiers to fetch, in the order the source listed them.
     * @param callable(string): void $log
     *
     * @return array{records: int, sha256: string, bytes: int}
     */
    private function fetchAndWrite(string $path, string $endpoint, array $listing, callable $log, string $what): array
    {
        if ($listing === []) {
            // A release with no concepts of a kind is not a release; an empty listing is the source not
            // knowing the release named, or answering for nothing, and neither is a snapshot.
            throw new SnapshotException(\sprintf('The source listed no %s for %s; no snapshot was written.', $what, $this->config->sourceVersion));
        }

        $log(\sprintf('%d %s listed; fetching', \count($listing), $what));

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new SnapshotException('Could not open ' . $path . ' for writing.');
        }

        $records = 0;

        try {
            // Fetched in slices so that memory holds one slice of full records rather than all of them, and
            // written in the order the listing gave, so two snapshots of the same data are byte-identical.
            foreach (array_chunk($listing, $this->config->fetchBatchSize * $this->config->fetchConcurrency * 4) as $slice) {
                $fetched = $this->api->resources($endpoint, $slice);

                foreach ($slice as $uri) {
                    $record = $fetched[$uri] ?? throw new SnapshotException('The source listed ' . $uri . ' and did not return it.');
                    unset($record['_embedded']);

                    $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    if (fwrite($handle, $line . "\n") === false) {
                        throw new SnapshotException('Could not write to ' . $path);
                    }

                    $records++;
                }

                $log(\sprintf('  %d / %d %s', $records, \count($listing), $what));
            }
        } finally {
            fclose($handle);
        }

        $digest = hash_file('sha256', $path);
        $bytes = filesize($path);

        if (!\is_string($digest) || !\is_int($bytes)) {
            throw new SnapshotException('Could not read back ' . $path);
        }

        return ['records' => $records, 'sha256' => $digest, 'bytes' => $bytes];
    }

    /**
     * A sibling of the snapshot directory, named by suffix. Every directory the builder creates, empties or
     * renames comes from here, checked to be a distinct path under the configured snapshot path, so that
     * nothing outside it can be touched.
     */
    private function beside(string $target, string $suffix): string
    {
        $path = $target . $suffix;

        if ($path === $target || !str_starts_with($path, $this->config->snapshotPath . '/')) {
            throw new \LogicException('Snapshot directories lie under ' . $this->config->snapshotPath . '; refusing ' . $path);
        }

        return $path;
    }

    private function prepareDirectory(string $scratch): void
    {
        if (is_dir($scratch)) {
            $this->removeDirectory($scratch);
        }

        if (!mkdir($scratch, 0o750, true) && !is_dir($scratch)) {
            throw new SnapshotException('Could not create ' . $scratch);
        }
    }

    private function replaceDirectory(string $scratch, string $target): void
    {
        if (is_dir($target)) {
            $previous = $this->beside($target, '.previous');

            if (is_dir($previous)) {
                $this->removeDirectory($previous);
            }

            if (!rename($target, $previous)) {
                throw new SnapshotException('Could not set aside the previous snapshot at ' . $target);
            }
        }

        if (!rename($scratch, $target)) {
            throw new SnapshotException('Could not move the snapshot into ' . $target);
        }
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (!unlink($path)) {
                throw new SnapshotException('Could not remove ' . $path);
            }
        }

        if (!rmdir($directory)) {
            throw new SnapshotException('Could not remove ' . $directory);
        }
    }
}
