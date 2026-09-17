<?php

declare(strict_types=1);

/**
 * Derive the test fixture snapshot from a real one.
 *
 * The fixture under tests/Fixtures/taxonomy/snapshot is a closed slice of a real snapshot: a few occupations
 * named by code, the first essential skills of each, and every group above them, in a handful of languages,
 * with the fields the importer reads and nothing else. Closed means every link points inside the slice,
 * which is what the importer requires of a full snapshot too.
 *
 * Run after taking a real snapshot, from apps/server:
 *
 *     php tools/derive-taxonomy-fixture.php
 *
 * The result is committed. Regenerating it after a source release changes the fixture's content, and the
 * tests that read specific labels say so.
 */

require __DIR__ . '/../vendor/autoload.php';

use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use BothDecks\Taxonomy\Internal\Snapshot\SnapshotManifest;

$root = dirname(__DIR__);
$config = require $root . '/config/taxonomy.php';

if (!is_array($config) || !is_array($config['source'] ?? null)) {
    fwrite(STDERR, "config/taxonomy.php must return the source section.\n");
    exit(1);
}

$sourceName = (string) $config['source']['name'];
$release = (string) $config['source']['version'];
$sourceDirectory = $root . '/' . (string) $config['snapshot_path'] . '/' . $sourceName . '/' . $release;
$target = $root . '/tests/Fixtures/taxonomy/snapshot';

// Six product locales, so labels and descriptions can be asserted in several scripts, and two languages the
// product does not serve, so the tests can prove the import filters by configuration. One of the
// occupations (2512.4.1) sits under another rather than directly under a group, so the import's walk up to
// the group is exercised.
$locales = ['en', 'fr', 'nl', 'de', 'bg', 'el', 'is', 'no'];
$wantedCodes = ['2512.3', '2512.4', '2512.4.1', '2513.1', '3514.1', '7212.3', '2221.1'];
$essentialSkillsPerOccupation = 8;

$keptLinks = [
    'self', 'broaderIscoGroup', 'broaderOccupation', 'broaderConcept', 'broaderHierarchyConcept', 'broaderSkill',
    'hasEssentialSkill', 'hasSkillType', 'hasReuseLevel', 'regulatedProfessionNote',
];

$snapshot = Snapshot::open($sourceDirectory);
$all = [];

foreach (Snapshot::FILES as $file) {
    foreach ($snapshot->records($file) as $record) {
        $uri = $record['uri'] ?? null;

        if (!is_string($uri)) {
            continue;
        }

        $all[$uri] = ['file' => $file, 'record' => $record];
    }
}

$uriByCode = [];

foreach ($all as $uri => $entry) {
    $code = $entry['record']['code'] ?? null;

    if ($entry['file'] === Snapshot::OCCUPATIONS && is_string($code)) {
        $uriByCode[$code] = $uri;
    }
}

/**
 * @param array<string, mixed> $record
 *
 * @return list<string>
 */
$linkedUris = static function (array $record, string $link): array {
    $links = $record['_links'] ?? null;
    $entries = is_array($links) ? ($links[$link] ?? []) : [];
    $uris = [];

    foreach (is_array($entries) ? $entries : [] as $entry) {
        if (is_array($entry) && is_string($entry['uri'] ?? null)) {
            $uris[] = $entry['uri'];
        }
    }

    return $uris;
};

$keep = [];
$queue = [];

foreach ($wantedCodes as $code) {
    if (!isset($uriByCode[$code])) {
        fwrite(STDERR, "No occupation with code {$code} in the snapshot at {$sourceDirectory}.\n");
        exit(1);
    }

    $queue[] = $uriByCode[$code];
}

while ($queue !== []) {
    $uri = array_shift($queue);

    if (isset($keep[$uri])) {
        continue;
    }

    if (!isset($all[$uri])) {
        fwrite(STDERR, "The snapshot links to {$uri} and does not hold it.\n");
        exit(1);
    }

    $keep[$uri] = true;
    $entry = $all[$uri];

    foreach (['broaderIscoGroup', 'broaderOccupation', 'broaderConcept', 'broaderHierarchyConcept', 'broaderSkill'] as $link) {
        foreach ($linkedUris($entry['record'], $link) as $linked) {
            $queue[] = $linked;
        }
    }

    if ($entry['file'] === Snapshot::OCCUPATIONS) {
        foreach (array_slice($linkedUris($entry['record'], 'hasEssentialSkill'), 0, $essentialSkillsPerOccupation) as $linked) {
            $queue[] = $linked;
        }
    }
}

if (!is_dir($target) && !mkdir($target, 0o755, true) && !is_dir($target)) {
    fwrite(STDERR, "Cannot create {$target}\n");
    exit(1);
}

$files = [];

foreach (Snapshot::FILES as $file) {
    $lines = '';
    $count = 0;

    foreach ($all as $uri => $entry) {
        if ($entry['file'] !== $file || !isset($keep[$uri])) {
            continue;
        }

        $record = $entry['record'];
        $trimmed = ['className' => $record['className'] ?? '', 'uri' => $uri, 'title' => $record['title'] ?? ''];

        foreach (['code', 'status'] as $key) {
            if (isset($record[$key])) {
                $trimmed[$key] = $record[$key];
            }
        }

        foreach (['preferredLabel', 'alternativeLabel', 'description'] as $key) {
            if (is_array($record[$key] ?? null)) {
                $trimmed[$key] = array_intersect_key($record[$key], array_flip($locales));
            }
        }

        $trimmed['_links'] = [];
        $links = is_array($record['_links'] ?? null) ? $record['_links'] : [];

        foreach ($keptLinks as $link) {
            if (!isset($links[$link])) {
                continue;
            }

            $value = $links[$link];

            if (is_array($value) && isset($value['href'])) {
                $trimmed['_links'][$link] = ['href' => $value['href'], 'uri' => $value['uri'] ?? null];

                continue;
            }

            $list = [];

            foreach (is_array($value) ? $value : [] as $item) {
                if (!is_array($item) || !is_string($item['uri'] ?? null)) {
                    continue;
                }

                if ($link === 'hasEssentialSkill' && !isset($keep[$item['uri']])) {
                    continue;
                }

                $list[] = ['href' => $item['href'] ?? '', 'uri' => $item['uri'], 'title' => $item['title'] ?? ''];
            }

            $trimmed['_links'][$link] = $list;
        }

        $lines .= json_encode($trimmed, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $count++;
    }

    file_put_contents($target . '/' . $file, $lines);
    $files[$file] = ['records' => $count, 'sha256' => hash('sha256', $lines), 'bytes' => strlen($lines)];
}

$manifest = new SnapshotManifest(
    source: $snapshot->manifest->source,
    version: $snapshot->manifest->version,
    api: $snapshot->manifest->api,
    licence: $snapshot->manifest->licence,
    attribution: $snapshot->manifest->attribution,
    takenAt: $snapshot->manifest->takenAt,
    files: $files,
);

file_put_contents(
    $target . '/' . Snapshot::MANIFEST,
    json_encode($manifest->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);

foreach ($files as $file => $figures) {
    echo sprintf("%-22s %4d records  %8d bytes\n", $file, $figures['records'], $figures['bytes']);
}

echo "Fixture written to {$target}\n";
