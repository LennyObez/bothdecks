<?php

declare(strict_types=1);

namespace BothDecks\Tests\Unit\Taxonomy\Snapshot;

use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use BothDecks\Taxonomy\Internal\Snapshot\SnapshotException;
use BothDecks\Taxonomy\Internal\Snapshot\SnapshotManifest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Snapshot::class)]
#[CoversClass(SnapshotManifest::class)]
final class SnapshotTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bothdecks-snapshot-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o750, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testASnapshotOpensAndYieldsItsRecordsInOrder(): void
    {
        // Arrange
        self::writeSnapshot($this->directory, [
            Snapshot::OCCUPATIONS => [['uri' => 'o:1'], ['uri' => 'o:2']],
            Snapshot::SKILLS => [['uri' => 's:1']],
            Snapshot::ISCO_GROUPS => [['uri' => 'g:1']],
            Snapshot::SKILL_GROUPS => [['uri' => 'h:1']],
        ]);

        // Act
        $snapshot = Snapshot::open($this->directory);

        // Assert
        self::assertSame(['o:1', 'o:2'], array_column(iterator_to_array($snapshot->records(Snapshot::OCCUPATIONS), false), 'uri'));
        self::assertSame(2, $snapshot->manifest->records(Snapshot::OCCUPATIONS));
        self::assertSame('v1.2.1', $snapshot->manifest->version);
    }

    public function testTheDigestDependsOnTheContentAndNotOnWhenItWasTaken(): void
    {
        // Two snapshots of the same data must be the same version whenever they were taken.

        // Arrange
        $files = ['a.ndjson' => ['records' => 1, 'sha256' => str_repeat('a', 64), 'bytes' => 10]];
        $morning = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', new \DateTimeImmutable('2026-01-01T08:00:00Z'), $files);
        $evening = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', new \DateTimeImmutable('2026-01-01T20:00:00Z'), $files);

        // Act
        $same = $morning->digest() === $evening->digest();

        // Assert
        self::assertTrue($same);
    }

    public function testTheDigestChangesWhenAnyFileChanges(): void
    {
        // Arrange
        $files = ['a.ndjson' => ['records' => 1, 'sha256' => str_repeat('a', 64), 'bytes' => 10]];
        $other = ['a.ndjson' => ['records' => 1, 'sha256' => str_repeat('b', 64), 'bytes' => 10]];
        $taken = new \DateTimeImmutable('2026-01-01T08:00:00Z');

        // Act
        $one = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', $taken, $files)->digest();
        $two = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', $taken, $other)->digest();

        // Assert
        self::assertNotSame($one, $two);
    }

    public function testATruncatedFileIsRefusedBeforeAnyRecordIsRead(): void
    {
        // Arrange
        self::writeSnapshot($this->directory, [
            Snapshot::OCCUPATIONS => [['uri' => 'o:1'], ['uri' => 'o:2']],
            Snapshot::SKILLS => [['uri' => 's:1']],
            Snapshot::ISCO_GROUPS => [['uri' => 'g:1']],
            Snapshot::SKILL_GROUPS => [['uri' => 'h:1']],
        ]);
        file_put_contents($this->directory . '/' . Snapshot::SKILLS, '');

        // Act
        $failure = self::capture(fn() => Snapshot::open($this->directory));

        // Assert
        self::assertInstanceOf(SnapshotException::class, $failure);
        self::assertSame('The snapshot file skills.ndjson does not match its digest in the manifest; the snapshot is corrupt.', $failure->getMessage());
    }

    public function testAnEditedManifestIsRefused(): void
    {
        // Arrange
        self::writeSnapshot($this->directory, [
            Snapshot::OCCUPATIONS => [['uri' => 'o:1']],
            Snapshot::SKILLS => [['uri' => 's:1']],
            Snapshot::ISCO_GROUPS => [['uri' => 'g:1']],
            Snapshot::SKILL_GROUPS => [['uri' => 'h:1']],
        ]);
        $manifest = json_decode((string) file_get_contents($this->directory . '/' . Snapshot::MANIFEST), true);
        self::assertIsArray($manifest);
        $manifest['version'] = 'v9.9.9';
        file_put_contents($this->directory . '/' . Snapshot::MANIFEST, json_encode($manifest));

        // Act
        $failure = self::capture(fn() => Snapshot::open($this->directory));

        // Assert
        // The release is part of the digest, so relabelling a snapshot as another release is caught the same
        // way as a changed file.
        self::assertInstanceOf(\InvalidArgumentException::class, $failure);
        self::assertStringContainsString('digest', $failure->getMessage());
    }

    public function testTheDigestCoversWhatTheDataIsAndWhereItCameFrom(): void
    {
        // Arrange
        $files = ['a.ndjson' => ['records' => 1, 'sha256' => str_repeat('a', 64), 'bytes' => 10]];
        $taken = new \DateTimeImmutable('2026-01-01T08:00:00Z');
        $base = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', $taken, $files);

        // Act
        $digests = [
            'source' => new SnapshotManifest('t', 'v1.0.0', 'https://x', 'L', 'A', $taken, $files)->digest(),
            'version' => new SnapshotManifest('s', 'v1.0.1', 'https://x', 'L', 'A', $taken, $files)->digest(),
            'api' => new SnapshotManifest('s', 'v1.0.0', 'https://y', 'L', 'A', $taken, $files)->digest(),
            'licence' => new SnapshotManifest('s', 'v1.0.0', 'https://x', 'M', 'A', $taken, $files)->digest(),
            'attribution' => new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'B', $taken, $files)->digest(),
            'file name' => new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', $taken, ['b.ndjson' => $files['a.ndjson']])->digest(),
        ];

        // Assert
        foreach ($digests as $field => $digest) {
            self::assertNotSame($base->digest(), $digest, 'A change of ' . $field . ' must change the digest.');
        }

        self::assertCount(\count($digests), array_unique($digests), 'Each field must contribute on its own.');
    }

    public function testTheDigestIsTheDocumentedHashOfTheDocumentedMaterial(): void
    {
        // The digest is written into version rows and compared across machines, so its material is a
        // format, not an implementation detail: the source, the release, the address, the licence, the
        // attribution, then one line per file in name order, each `name:sha256`, every line ending in a
        // newline.

        // Arrange
        $a = ['records' => 1, 'sha256' => str_repeat('a', 64), 'bytes' => 10];
        $b = ['records' => 2, 'sha256' => str_repeat('b', 64), 'bytes' => 20];
        $manifest = new SnapshotManifest('esco', 'v1.2.1', 'https://x', 'CC-BY-4.0', 'Source', new \DateTimeImmutable('2026-01-01T08:00:00Z'), ['b.ndjson' => $b, 'a.ndjson' => $a]);
        $material = "esco\nv1.2.1\nhttps://x\nCC-BY-4.0\nSource\na.ndjson:" . str_repeat('a', 64) . "\nb.ndjson:" . str_repeat('b', 64) . "\n";

        // Act
        $digest = $manifest->digest();

        // Assert
        self::assertSame(hash('sha256', $material), $digest);
    }

    public function testTheDigestDoesNotDependOnTheOrderTheFilesAreListedIn(): void
    {
        // Arrange
        $a = ['records' => 1, 'sha256' => str_repeat('a', 64), 'bytes' => 10];
        $b = ['records' => 2, 'sha256' => str_repeat('b', 64), 'bytes' => 20];
        $taken = new \DateTimeImmutable('2026-01-01T08:00:00Z');

        // Act
        $one = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', $taken, ['a.ndjson' => $a, 'b.ndjson' => $b])->digest();
        $two = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', $taken, ['b.ndjson' => $b, 'a.ndjson' => $a])->digest();

        // Assert
        self::assertSame($one, $two);
    }

    /**
     * Each case overrides keys of a valid manifest array; a null override removes the key.
     *
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformedManifests(): iterable
    {
        yield 'no source' => [['source' => null], 'The manifest lacks a non-empty "source".'];
        yield 'empty version' => [['version' => ''], 'The manifest lacks a non-empty "version".'];
        yield 'no digest' => [['digest' => null], 'The manifest lacks a non-empty "digest".'];
        yield 'unparseable time' => [['taken_at' => 'yesterday'], 'The manifest\'s taken_at is not an RFC 3339 timestamp.'];
        yield 'no files' => [['files' => []], 'The manifest lists no files.'];
        yield 'file entry without records' => [
            ['files' => ['a.ndjson' => ['sha256' => str_repeat('a', 64), 'bytes' => 1]]],
            'The manifest entry for \'a.ndjson\' is malformed.',
        ];
        yield 'file entry with a numeric name' => [
            ['files' => [['records' => 1, 'sha256' => str_repeat('a', 64), 'bytes' => 1]]],
            'The manifest entry for 0 is malformed.',
        ];
        yield 'file entry with a digest that is not a string' => [
            ['files' => ['a.ndjson' => ['records' => 1, 'sha256' => 12, 'bytes' => 1]]],
            'The manifest entry for \'a.ndjson\' is malformed.',
        ];
        yield 'digest that does not match' => [['digest' => str_repeat('0', 64)], 'The manifest\'s digest does not match its file digests; the manifest was edited.'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('malformedManifests')]
    public function testAMalformedManifestIsRefusedWithTheFieldNamed(array $overrides, string $message): void
    {
        // Arrange
        $manifest = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', new \DateTimeImmutable('2026-01-01T08:00:00Z'), [
            'a.ndjson' => ['records' => 1, 'sha256' => str_repeat('a', 64), 'bytes' => 10],
        ])->toArray();

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($manifest[$key]);
            } else {
                $manifest[$key] = $value;
            }
        }

        // Act
        $failure = self::capture(static fn() => SnapshotManifest::fromArray($manifest));

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $failure);
        self::assertSame($message, $failure->getMessage());
    }

    public function testAManifestRoundTripsThroughItsArrayForm(): void
    {
        // Arrange
        $manifest = new SnapshotManifest('esco', 'v1.2.1', 'https://x', 'CC-BY-4.0', 'Source', new \DateTimeImmutable('2026-09-17T10:00:00+00:00'), [
            'a.ndjson' => ['records' => 3, 'sha256' => str_repeat('c', 64), 'bytes' => 30],
        ]);

        // Act
        $read = SnapshotManifest::fromArray($manifest->toArray());

        // Assert
        self::assertSame($manifest->digest(), $read->digest());
        self::assertSame('2026-09-17T10:00:00+00:00', $read->takenAt->format(\DateTimeInterface::RFC3339));
        self::assertSame(3, $read->records('a.ndjson'));
        self::assertSame(['esco', 'v1.2.1', 'https://x', 'CC-BY-4.0', 'Source'], [$read->source, $read->version, $read->api, $read->licence, $read->attribution]);
    }

    public function testAskingForAFileTheManifestDoesNotListFails(): void
    {
        // Arrange
        $manifest = new SnapshotManifest('s', 'v1.0.0', 'https://x', 'L', 'A', new \DateTimeImmutable('2026-01-01T08:00:00Z'), [
            'a.ndjson' => ['records' => 1, 'sha256' => str_repeat('a', 64), 'bytes' => 10],
        ]);

        // Act
        $failure = self::capture(static fn() => $manifest->records('zz.ndjson'));

        // Assert
        self::assertInstanceOf(\OutOfBoundsException::class, $failure);
        self::assertSame('The snapshot has no file named zz.ndjson', $failure->getMessage());
    }

    public function testAFileTheManifestListsButTheDirectoryLacksIsNamed(): void
    {
        // Arrange
        self::writeSnapshot($this->directory, [
            Snapshot::OCCUPATIONS => [['uri' => 'o:1']],
            Snapshot::SKILLS => [['uri' => 's:1']],
            Snapshot::ISCO_GROUPS => [['uri' => 'g:1']],
            Snapshot::SKILL_GROUPS => [['uri' => 'h:1']],
        ]);
        unlink($this->directory . '/' . Snapshot::ISCO_GROUPS);

        // Act
        $failure = self::capture(fn() => Snapshot::open($this->directory));

        // Assert
        self::assertInstanceOf(SnapshotException::class, $failure);
        self::assertSame('The snapshot file isco_groups.ndjson is listed in the manifest and missing from disk.', $failure->getMessage());
    }

    public function testAManifestListingFewerFilesThanTheSnapshotNeedsIsRefused(): void
    {
        // Arrange
        self::writeSnapshot($this->directory, [
            Snapshot::OCCUPATIONS => [['uri' => 'o:1']],
            Snapshot::SKILLS => [['uri' => 's:1']],
            Snapshot::ISCO_GROUPS => [['uri' => 'g:1']],
        ]);

        // Act
        $failure = self::capture(fn() => Snapshot::open($this->directory));

        // Assert
        self::assertInstanceOf(SnapshotException::class, $failure);
        self::assertSame('The manifest does not list skill_groups.ndjson', $failure->getMessage());
    }

    public function testBlankLinesAreSkippedAndANonObjectLineIsNamedByNumber(): void
    {
        // Arrange
        self::writeSnapshot($this->directory, [
            Snapshot::OCCUPATIONS => [['uri' => 'o:1'], ['uri' => 'o:2']],
            Snapshot::SKILLS => [['uri' => 's:1']],
            Snapshot::ISCO_GROUPS => [['uri' => 'g:1']],
            Snapshot::SKILL_GROUPS => [['uri' => 'h:1']],
        ]);
        $occupations = $this->directory . '/' . Snapshot::OCCUPATIONS;
        file_put_contents($occupations, "\n" . (string) file_get_contents($occupations) . "42\n");
        self::rewriteManifestFor($this->directory);
        $snapshot = Snapshot::open($this->directory);

        // Act
        $read = [];
        $failure = self::capture(static function () use ($snapshot, &$read): void {
            foreach ($snapshot->records(Snapshot::OCCUPATIONS) as $record) {
                $read[] = $record['uri'] ?? null;
            }
        });

        // Assert
        self::assertSame(['o:1', 'o:2'], $read);
        self::assertInstanceOf(SnapshotException::class, $failure);
        self::assertSame('occupations.ndjson line 4 is not a JSON object.', $failure->getMessage());
    }

    public function testAMissingManifestNamesTheCommandThatTakesASnapshot(): void
    {
        // Act
        $failure = self::capture(fn() => Snapshot::open($this->directory));

        // Assert
        self::assertInstanceOf(SnapshotException::class, $failure);
        self::assertSame('No snapshot at ' . $this->directory . ': the manifest is missing. Take one with taxonomy:snapshot.', $failure->getMessage());
    }

    /**
     * @param array<string, list<array<string, mixed>>> $files
     */
    private static function writeSnapshot(string $directory, array $files): void
    {
        $entries = [];

        foreach ($files as $name => $records) {
            $path = $directory . '/' . $name;
            $content = implode('', array_map(static fn(array $r): string => json_encode($r, JSON_THROW_ON_ERROR) . "\n", $records));
            file_put_contents($path, $content);
            $entries[$name] = ['records' => \count($records), 'sha256' => hash('sha256', $content), 'bytes' => \strlen($content)];
        }

        $manifest = new SnapshotManifest('esco', 'v1.2.1', 'https://source.example', 'CC-BY-4.0', 'Source', new \DateTimeImmutable('2026-09-17T10:00:00Z'), $entries);
        file_put_contents($directory . '/' . Snapshot::MANIFEST, json_encode($manifest->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * A manifest matching whatever the directory's files now hold.
     */
    private static function rewriteManifestFor(string $directory): void
    {
        $entries = [];

        foreach (Snapshot::FILES as $name) {
            $content = (string) file_get_contents($directory . '/' . $name);
            $entries[$name] = ['records' => substr_count($content, "\n"), 'sha256' => hash('sha256', $content), 'bytes' => \strlen($content)];
        }

        $manifest = new SnapshotManifest('esco', 'v1.2.1', 'https://source.example', 'CC-BY-4.0', 'Source', new \DateTimeImmutable('2026-09-17T10:00:00Z'), $entries);
        file_put_contents($directory . '/' . Snapshot::MANIFEST, json_encode($manifest->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * @param callable(): mixed $build
     */
    private static function capture(callable $build): ?\Throwable
    {
        try {
            $build();
        } catch (\Throwable $caught) {
            return $caught;
        }

        return null;
    }
}
