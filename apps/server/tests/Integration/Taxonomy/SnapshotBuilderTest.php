<?php

declare(strict_types=1);

namespace BothDecks\Tests\Integration\Taxonomy;

use BothDecks\Shared\Time\SystemClock;
use BothDecks\Taxonomy\Internal\Esco\EscoApi;
use BothDecks\Taxonomy\Internal\Esco\EscoApiException;
use BothDecks\Taxonomy\Internal\Esco\EscoSnapshotBuilder;
use BothDecks\Taxonomy\Internal\Snapshot\Snapshot;
use BothDecks\Taxonomy\Internal\Snapshot\SnapshotException;
use BothDecks\Tests\Integration\Support\StandInSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The client and the snapshot builder against a stand-in for the source, served by PHP's built-in server
 * from the fixture. What is proved: the release is named on every request, listings are paged by page
 * number and checked against the announced total, resources are fetched in batches and written without the
 * embedded copies, a failed request is retried alone as often as configured with growing pauses, the
 * manifest matches the files, a snapshot replaces the previous one only once whole, and each way the
 * source can misbehave is refused with its reason.
 */
#[CoversClass(EscoApi::class)]
#[CoversClass(EscoSnapshotBuilder::class)]
final class SnapshotBuilderTest extends TestCase
{
    private ?StandInSource $source = null;

    protected function tearDown(): void
    {
        if ($this->source !== null) {
            $this->source->stop();
            $this->source->removeTree();
            $this->source = null;
        }
    }

    public function testASnapshotOfTheStandInMatchesTheFixtureRecordForRecord(): void
    {
        // Arrange
        $source = $this->source();
        $builder = self::builder($source);
        $lines = [];

        // Act
        $manifest = $builder->build(static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        // Assert
        $snapshot = Snapshot::open($source->config()->snapshotDirectory());
        self::assertSame($manifest->digest(), $snapshot->manifest->digest());
        self::assertSame(7, $manifest->records(Snapshot::OCCUPATIONS));
        self::assertSame(74, $manifest->records(Snapshot::SKILLS));
        self::assertSame(16, $manifest->records(Snapshot::ISCO_GROUPS));
        self::assertSame(94, $manifest->records(Snapshot::SKILL_GROUPS));

        $written = iterator_to_array($snapshot->records(Snapshot::OCCUPATIONS), false);
        $expected = iterator_to_array(Snapshot::open(self::fixture())->records(Snapshot::OCCUPATIONS), false);
        self::assertSame(array_column($expected, 'uri'), array_column($written, 'uri'));
        self::assertArrayNotHasKey('_embedded', $written[0] ?? ['_embedded' => true]);

        self::assertContains('7 occupations listed; fetching', $lines);
        self::assertContains('  7 / 7 occupations', $lines);
        self::assertSame('Snapshot ' . $manifest->digest() . ' written to ' . $source->config()->snapshotDirectory(), end($lines));
    }

    public function testEveryRequestNamesTheReleaseAndListingsArePagedByPageNumber(): void
    {
        // Arrange: pages of five, so the seven occupations take two pages and the 74 skills fifteen.
        $source = $this->source();

        // Act
        self::builder($source)->build(static function (string $line): void {});
        $requests = $source->requests();

        // Assert
        self::assertNotEmpty($requests);
        $withoutRelease = array_values(array_filter($requests, static fn(string $r): bool => !str_contains($r, 'selectedVersion=v1.2.1')));
        self::assertSame([], $withoutRelease, 'Requests without the release: ' . implode(', ', $withoutRelease));

        $occupationListings = array_values(array_filter($requests, static fn(string $r): bool => str_starts_with($r, '/search?') && str_contains($r, 'type=occupation')));
        self::assertCount(2, $occupationListings);
        self::assertStringContainsString('offset=0', $occupationListings[0] ?? '');
        self::assertStringContainsString('offset=1', $occupationListings[1] ?? '');

        $skillListings = array_values(array_filter($requests, static fn(string $r): bool => str_starts_with($r, '/search?') && str_contains($r, 'type=skill')));
        self::assertCount(15, $skillListings);

        // 74 skills in batches of 3 is 25 resource requests, none asking for more than 3.
        $skillFetches = array_values(array_filter($requests, static fn(string $r): bool => str_starts_with($r, '/resource/skill?')));
        self::assertCount(25, $skillFetches);
        self::assertLessThanOrEqual(3, max([0, ...array_map(static fn(string $r): int => substr_count($r, 'uris='), $skillFetches)]));

        // The source's terms ask that a client name itself.
        self::assertSame(['bothdecks-tests'], array_values(array_unique($source->userAgents())));
    }

    public function testAnIdentifierAskedForTwiceIsFetchedOnceAndCountedOnce(): void
    {
        // Arrange
        $source = $this->source();
        $config = $source->config();
        $listed = array_column(iterator_to_array(Snapshot::open(self::fixture())->records(Snapshot::OCCUPATIONS), false), 'uri');
        $first = $listed[0] ?? null;
        $second = $listed[1] ?? null;
        self::assertIsString($first);
        self::assertIsString($second);
        $progress = [];

        // Act
        $records = new EscoApi($config, 'bothdecks-tests')->resources('occupation', [$first, $second, $first], static function (int $fetched, int $total) use (&$progress): void {
            $progress[] = [$fetched, $total];
        });

        // Assert
        self::assertSame([$first, $second], array_keys($records));
        self::assertSame([[2, 2]], $progress);
        self::assertCount(1, $source->requests());
    }

    public function testTheFilesAreWrittenOneRecordPerLineReadableAsTheyAre(): void
    {
        // A snapshot is reviewed by eye and diffed between releases: pretty manifest, one record per line,
        // slashes and accents as they are, every file ending with a newline.

        // Arrange
        $source = $this->source();

        // Act
        self::builder($source)->build(static function (string $line): void {});
        $directory = $source->config()->snapshotDirectory();
        $manifest = (string) file_get_contents($directory . '/' . Snapshot::MANIFEST);
        $occupations = (string) file_get_contents($directory . '/' . Snapshot::OCCUPATIONS);

        // Assert
        self::assertStringStartsWith("{\n    \"", $manifest);
        self::assertStringEndsWith("}\n", $manifest);
        self::assertStringContainsString('"api": "http://127.0.0.1:', $manifest);
        self::assertStringNotContainsString('\/', $manifest);

        self::assertStringEndsWith("}\n", $occupations);
        $lines = explode("\n", rtrim($occupations, "\n"));
        self::assertCount(7, $lines);
        self::assertSame(array_fill(0, 7, '{'), array_map(static fn(string $l): string => $l[0] ?? '', $lines));
        self::assertStringContainsString('développeur', $occupations);
        self::assertStringNotContainsString(trim(json_encode('é', JSON_THROW_ON_ERROR), '"'), $occupations, 'Accents are written as they are, not escaped.');
        self::assertStringNotContainsString(trim(json_encode('/', JSON_THROW_ON_ERROR), '"'), $occupations, 'Slashes are written as they are, not escaped.');
    }

    public function testAReleaseTheSourceDoesNotKnowYieldsNoSnapshot(): void
    {
        // The stand-in, like the source, answers an unknown release with an empty listing rather than an
        // error; a snapshot of nothing must not be written.

        // Arrange
        $source = $this->source();
        $builder = self::builder($source, release: 'v9.9.9');

        // Act
        $failure = self::capture(static fn() => $builder->build(static function (string $line): void {}));

        // Assert
        self::assertInstanceOf(SnapshotException::class, $failure);
        self::assertSame('The source listed no occupations for v9.9.9; no snapshot was written.', $failure->getMessage());
        self::assertDirectoryDoesNotExist($source->config('v9.9.9')->snapshotDirectory());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function misbehaviours(): iterable
    {
        yield 'a page served twice' => ['duplicate-page', ['The search endpoint listed http://data.europa.eu/esco/occupation/', ' twice for type occupation']];
        yield 'a total that moves while paging' => ['total-drifts', ['The total for type occupation changed from 7 to 8 while paging; the source changed under the snapshot.']];
        yield 'a result without an identifier' => ['result-without-uri', ['A search result carries no URI for type occupation']];
        yield 'pages of another size than asked' => ['pages-of-twenty', ['The search endpoint paged type occupation by 20 where 5 was asked.']];
        yield 'a listed resource not returned' => ['drop-a-resource', ['The source listed http://data.europa.eu/esco/occupation/', ' and then did not return it.']];
        yield 'a truncated body, retried then given up' => ['garbage-body', ['the body is not a JSON object for http://127.0.0.1:']];
        yield 'a server error, retried then given up' => ['server-error', ['HTTP 500 for http://127.0.0.1:']];
        // The address is a reviewed configuration value; a source that moves is reconfigured, not followed.
        yield 'a redirect' => ['redirect', ['HTTP 302 for http://127.0.0.1:']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('misbehaviours')]
    public function testEachWayTheSourceCanMisbehaveIsRefusedWithItsReason(string $mode, array $expected): void
    {
        // Arrange: pages of five, so the paging faults show on the second page of occupations.
        $source = $this->source();
        $source->misbehave($mode);
        $pauses = [];
        $builder = self::builder($source, attempts: 2, pauses: $pauses);

        // Act
        $failure = self::capture(static fn() => $builder->build(static function (string $line): void {}));

        // Assert
        self::assertInstanceOf(EscoApiException::class, $failure);

        foreach ($expected as $fragment) {
            self::assertStringContainsString($fragment, $failure->getMessage());
        }

        self::assertDirectoryDoesNotExist($source->config()->snapshotDirectory());
    }

    public function testARetriedFailureIsAttemptedExactlyAsOftenAsConfiguredWithGrowingPauses(): void
    {
        // Arrange
        $source = $this->source();
        $source->misbehave('server-error');
        $pauses = [];
        $builder = self::builder($source, attempts: 3, pauses: $pauses);

        // Act
        $failure = self::capture(static fn() => $builder->build(static function (string $line): void {}));

        // Assert
        self::assertInstanceOf(EscoApiException::class, $failure);
        self::assertStringStartsWith('1 request(s) still failing after 3 attempts:', $failure->getMessage());
        self::assertCount(3, $source->requests());
        self::assertSame([2, 4], $pauses);
    }

    public function testAListingThatFillsItsLastPageExactlyAsksForNoPageAfterIt(): void
    {
        // Arrange: seven occupations, pages of seven.
        $source = $this->source();
        $api = new EscoApi($source->config(pageSize: 7), 'bothdecks-tests');

        // Act
        $listed = $api->listAll('occupation');

        // Assert
        self::assertCount(7, $listed);
        self::assertCount(1, $source->requests());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function transientFailures(): iterable
    {
        yield 'a server error' => ['fail-first-batch'];
        yield 'a truncated body' => ['garbage-first-batch'];
    }

    #[DataProvider('transientFailures')]
    public function testARequestThatFailsOnceIsRetriedAloneAndTheSnapshotCompletes(string $mode): void
    {
        // Arrange: the first batch of occupations fails once while the batch beside it succeeds.
        $source = $this->source();
        $source->misbehave($mode);
        $pauses = [];

        // Act
        $manifest = self::builder($source, attempts: 2, pauses: $pauses)->build(static function (string $line): void {});

        // Assert
        self::assertSame(7, $manifest->records(Snapshot::OCCUPATIONS));
        $occupationFetches = array_values(array_filter($source->requests(), static fn(string $r): bool => str_starts_with($r, '/resource/occupation?')));
        self::assertCount(4, $occupationFetches, 'Three batches, one of them asked twice.');
        self::assertSame([2], $pauses);
    }

    public function testAFailedBuildLeavesThePreviousSnapshotInPlace(): void
    {
        // Arrange: a whole snapshot, then a build that fails.
        $source = $this->source();
        self::builder($source)->build(static function (string $line): void {});
        $before = Snapshot::open($source->config()->snapshotDirectory())->manifest->digest();
        $source->misbehave('server-error');
        $pauses = [];
        $builder = self::builder($source, attempts: 1, pauses: $pauses);

        // Act
        $failure = self::capture(static fn() => $builder->build(static function (string $line): void {}));

        // Assert
        self::assertInstanceOf(EscoApiException::class, $failure);
        self::assertSame($before, Snapshot::open($source->config()->snapshotDirectory())->manifest->digest());
    }

    public function testASecondBuildSetsThePreviousSnapshotAsideAndAThirdReplacesThatToo(): void
    {
        // Arrange
        $source = $this->source();
        $directory = $source->config()->snapshotDirectory();
        self::builder($source)->build(static function (string $line): void {});
        mkdir($directory . '.partial/leftover', 0o750, true);
        file_put_contents($directory . '.partial/leftover/file', 'from a build that died');

        // Act
        self::builder($source)->build(static function (string $line): void {});
        self::builder($source)->build(static function (string $line): void {});

        // Assert
        self::assertDirectoryExists($directory);
        self::assertDirectoryExists($directory . '.previous');
        self::assertDirectoryDoesNotExist($directory . '.partial');
        self::assertSame(
            Snapshot::open($directory)->manifest->digest(),
            Snapshot::open($directory . '.previous')->manifest->digest(),
        );
    }

    private function source(): StandInSource
    {
        return $this->source ??= StandInSource::start();
    }

    /**
     * With `$pauses` given, the seconds each retry would have waited are appended to it instead of waited.
     *
     * @param list<int>|null $pauses
     */
    private static function builder(StandInSource $source, string $release = 'v1.2.1', int $attempts = 2, ?array &$pauses = null): EscoSnapshotBuilder
    {
        $config = $source->config($release, batchSize: 3, concurrency: 2, attempts: $attempts);
        $pause = $pauses === null ? null : static function (int $seconds) use (&$pauses): void {
            $pauses[] = $seconds;
        };

        return new EscoSnapshotBuilder(new EscoApi($config, 'bothdecks-tests', $pause), $config, new SystemClock());
    }

    private static function fixture(): string
    {
        return \dirname(__DIR__, 2) . '/Fixtures/taxonomy/snapshot';
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
