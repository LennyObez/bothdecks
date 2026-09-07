<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: every workflow step runs an action pinned to a commit.
 *
 * A tag is a movable pointer. Whoever controls the action's repository can move `v5` after review, and every
 * pipeline that references it then runs different code with the token the workflow was granted. For this
 * repository, those tokens include one that can write labels and one that will eventually deploy.
 *
 * A commit hash cannot be moved. The version stays visible in a trailing comment, which is also the form
 * automated dependency updates recognise, so pinning costs nothing in maintenance.
 */
#[Group('guarantee')]
final class WorkflowActionsArePinnedTest extends TestCase
{
    public function testEveryActionReferenceNamesACommit(): void
    {
        // Arrange
        $references = self::actionReferences();
        $unpinned = [];

        // Act
        foreach ($references as $where => $reference) {
            if (preg_match('/@[0-9a-f]{40}$/', $reference) !== 1) {
                $unpinned[] = $where . ' -> ' . $reference;
            }
        }

        // Assert
        self::assertSame(
            [],
            $unpinned,
            "These workflow steps reference an action by a tag, which whoever owns that repository can move\n"
            . "after review. Pin the commit and keep the version in a trailing comment:\n  - "
            . implode("\n  - ", $unpinned),
        );
    }

    public function testEveryPinnedActionKeepsItsVersionVisible(): void
    {
        // A bare hash is unreadable and stops a reader (and an update bot) from knowing what it stands for.

        // Arrange
        $lines = self::actionLines();
        $undocumented = [];

        // Act
        foreach ($lines as $where => $line) {
            if (!str_contains($line, '#')) {
                $undocumented[] = $where . ' -> ' . trim($line);
            }
        }

        // Assert
        self::assertSame(
            [],
            $undocumented,
            "These pinned actions carry no version comment:\n  - " . implode("\n  - ", $undocumented),
        );
    }

    public function testWorkflowsWereActuallyRead(): void
    {
        // Without this, a change to the workflow directory would make both checks pass by finding nothing.

        // Act
        $references = self::actionReferences();

        // Assert
        self::assertNotEmpty($references, 'No action reference was found; the checks above are inert.');
    }

    /**
     * Every `uses:` value, keyed by where it appears.
     *
     * @return array<string, string>
     */
    private static function actionReferences(): array
    {
        $references = [];

        foreach (self::actionLines() as $where => $line) {
            if (preg_match('/uses:\s*(\S+)/', $line, $matches) === 1) {
                $references[$where] = $matches[1];
            }
        }

        return $references;
    }

    /**
     * Every workflow line declaring an action, keyed by `file:line`.
     *
     * @return array<string, string>
     */
    private static function actionLines(): array
    {
        $lines = [];

        foreach (glob(self::repositoryRoot() . '/.github/workflows/*.yml') ?: [] as $path) {
            $contents = file_get_contents($path);

            if (!\is_string($contents)) {
                continue;
            }

            foreach (explode("\n", $contents) as $index => $line) {
                if (str_contains($line, 'uses:')) {
                    $lines[basename($path) . ':' . ($index + 1)] = $line;
                }
            }
        }

        return $lines;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
