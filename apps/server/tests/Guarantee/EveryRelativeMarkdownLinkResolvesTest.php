<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: every relative link in a tracked Markdown file points at a path that exists.
 *
 * A document that points at a page nobody wrote, or at the wrong depth, is the cheapest broken promise a
 * reader can hit. The pipeline runs the same check on every pull request; running it here as well means a
 * link is caught before the push, in the same suite as every other guarantee.
 */
#[Group('guarantee')]
final class EveryRelativeMarkdownLinkResolvesTest extends TestCase
{
    public function testEveryRelativeLinkInATrackedMarkdownFileResolves(): void
    {
        // Act
        $broken = self::brokenLinks();

        // Assert
        self::assertSame(
            [],
            $broken,
            "Markdown links pointing at paths that do not exist:\n  - " . implode("\n  - ", $broken),
        );
    }

    public function testTheScanReadsRealMarkdownFiles(): void
    {
        // Without this, a change in how tracked files are listed would let the check pass by reading nothing.

        // Act
        $files = self::trackedMarkdownFiles();

        // Assert
        self::assertNotEmpty($files, 'No tracked Markdown file was read; the check above is inert.');
    }

    public function testTheLinkPatternMatchesWhatItMustAndLeavesTheRestAlone(): void
    {
        // Arrange
        $text = 'See [a](docs/a.md), [b](../b.md#section), [c](https://example.test/c), [d](mailto:d@example.test), [e](#only-anchor).';

        // Act
        $targets = self::relativeTargets($text);

        // Assert
        self::assertSame(['docs/a.md', '../b.md'], $targets);
    }

    /**
     * @return list<string> Each entry is `file -> target`.
     */
    private static function brokenLinks(): array
    {
        $root = self::repositoryRoot();
        $broken = [];

        foreach (self::trackedMarkdownFiles() as $relativePath => $contents) {
            $directory = \dirname($relativePath);

            foreach (self::relativeTargets($contents) as $target) {
                if (!file_exists($root . '/' . $directory . '/' . $target)) {
                    $broken[] = $relativePath . ' -> ' . $target;
                }
            }
        }

        sort($broken);

        return $broken;
    }

    /**
     * The targets of the Markdown links in a text that name a path: no scheme, no bare anchor, and the
     * anchor stripped from a path that carries one.
     *
     * @return list<string>
     */
    private static function relativeTargets(string $contents): array
    {
        preg_match_all('/\]\(([^)#][^)]*)\)/', $contents, $matches);
        $targets = [];

        foreach ($matches[1] as $target) {
            if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) === 1) {
                continue;
            }

            $targets[] = explode('#', $target, 2)[0];
        }

        return $targets;
    }

    /**
     * Tracked Markdown files, keyed by repository-relative path.
     *
     * @return array<string, string>
     */
    private static function trackedMarkdownFiles(): array
    {
        $root = self::repositoryRoot();
        $output = [];
        $status = 0;

        exec(\sprintf('git -C %s ls-files -- %s 2>/dev/null', escapeshellarg($root), escapeshellarg('*.md')), $output, $status);

        if ($status !== 0 || $output === []) {
            self::fail('Could not list tracked Markdown files with git.');
        }

        $files = [];

        foreach ($output as $relativePath) {
            if ($relativePath === '') {
                continue;
            }

            $contents = @file_get_contents($root . '/' . $relativePath);

            if (\is_string($contents)) {
                $files[$relativePath] = $contents;
            }
        }

        return $files;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
