<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: no tracked file carries a trace of the machine it was written on.
 *
 * Every tracked file is published. An absolute path from someone's home directory, a drive letter or the name
 * of a personal cloud folder tells a reader nothing about the software and something about its author, and it
 * is the kind of detail that survives review precisely because it looks like part of the code.
 *
 * The rule this enforces is stated in CONTRIBUTING.md. Two of its three parts (no named past defect, nothing
 * that reads as denigrating the product) are judgements a test cannot make. This covers the third, which is
 * mechanical, and it is better to enforce the third than to leave all three to memory.
 *
 * The patterns are assembled from fragments so that this file does not itself contain what it forbids, which
 * would make the check either self-failing or self-excluding. Both are worse than a little indirection.
 */
#[Group('guarantee')]
final class NothingLeaksTheDevelopmentEnvironmentTest extends TestCase
{
    public function testNoTrackedFileCarriesAnAbsolutePathFromADeveloperMachine(): void
    {
        // Arrange
        $patterns = self::forbiddenFragments();

        // Act
        $offenders = self::trackedFilesMatching($patterns);

        // Assert
        self::assertSame(
            [],
            $offenders,
            "These tracked files carry a trace of the machine they were written on. Every tracked file is\n"
            . "published; replace the path with one relative to the repository:\n  - "
            . implode("\n  - ", $offenders),
        );
    }

    public function testTheScanReadsRealTrackedFiles(): void
    {
        // Without this, a change to how tracked files are listed would let the check above pass by reading
        // nothing at all.

        // Act
        $files = self::readableTrackedFiles();

        // Assert
        self::assertNotEmpty($files, 'No tracked file was read; the check above is inert.');
    }

    public function testThePatternsActuallyMatchWhatTheyDescribe(): void
    {
        // A pattern that matches nothing would make the guarantee silently vacuous. Each is exercised against
        // a string it must catch.

        // Arrange
        $samples = [
            '/' . 'home' . '/someone/project/file.php',
            '/' . 'Users' . '/someone/project/file.php',
            'C:' . '\\' . 'Users' . '\\someone',
            '/' . 'mnt' . '/c/projects',
            'One' . 'Drive/Documents',
        ];

        // Act
        $unmatched = [];

        foreach ($samples as $sample) {
            if (!self::containsForbiddenFragment($sample, self::forbiddenFragments())) {
                $unmatched[] = $sample;
            }
        }

        // Assert
        self::assertSame([], $unmatched, 'These samples should have been caught: ' . implode(', ', $unmatched));
    }

    public function testAnOrdinaryRepositoryRelativePathIsNotFlagged(): void
    {
        // The complement of the test above: a pattern that matches everything would be just as useless.

        // Arrange
        $innocent = 'apps/server/src/Shared/Config/ApplicationIdentity.php';

        // Act
        $flagged = self::containsForbiddenFragment($innocent, self::forbiddenFragments());

        // Assert
        self::assertFalse($flagged, 'A repository-relative path must not be flagged.');
    }

    /**
     * Fragments that identify a developer's machine, assembled so this file does not contain them literally.
     *
     * @return list<string>
     */
    private static function forbiddenFragments(): array
    {
        return [
            '/' . 'home' . '/',
            '/' . 'Users' . '/',
            'C:' . '\\' . 'Users',
            '/' . 'mnt' . '/c/',
            'One' . 'Drive',
            '\\\\' . 'wsl',
        ];
    }

    /**
     * @param list<string> $patterns
     */
    private static function containsForbiddenFragment(string $haystack, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (stripos($haystack, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $patterns
     *
     * @return list<string>
     */
    private static function trackedFilesMatching(array $patterns): array
    {
        $offenders = [];

        foreach (self::readableTrackedFiles() as $relativePath => $contents) {
            if (self::containsForbiddenFragment($contents, $patterns)) {
                $offenders[] = $relativePath;
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * Tracked text files, keyed by repository-relative path.
     *
     * Binary files are skipped: a byte sequence that happens to look like a path in a compiled asset is noise,
     * and the rule is about what a reader can read.
     *
     * @return array<string, string>
     */
    private static function readableTrackedFiles(): array
    {
        $root = self::repositoryRoot();
        $output = [];
        $status = 0;

        exec(\sprintf('git -C %s ls-files 2>/dev/null', escapeshellarg($root)), $output, $status);

        if ($status !== 0 || $output === []) {
            self::fail('Could not list tracked files with git.');
        }

        $files = [];

        foreach ($output as $relativePath) {
            if ($relativePath === '') {
                continue;
            }

            $contents = @file_get_contents($root . '/' . $relativePath);

            if (!\is_string($contents) || !mb_check_encoding($contents, 'UTF-8')) {
                continue;
            }

            $files[$relativePath] = $contents;
        }

        return $files;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
