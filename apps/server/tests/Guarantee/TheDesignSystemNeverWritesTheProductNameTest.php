<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: the design system carries no copy of the product name.
 *
 * Its sibling guarantee holds the application's source to the same rule; the design system needs its own,
 * because it is a separate publication handed to whoever draws the next screen. A name in a token
 * description, a swatch caption or a generated artefact is copied out of it by hand, and a rename then
 * reaches the application and stops at the design. The scan reads every file the system publishes, whatever
 * its extension, since markdown and a Compose theme are as much the deliverable as the code. Technical
 * identifiers derived from the stable slug are exempt: they are not what a reader sees.
 *
 * @see docs/adr/0004-the-product-name-lives-in-one-place.md
 */
#[Group('guarantee')]
final class TheDesignSystemNeverWritesTheProductNameTest extends TestCase
{
    private const string DESIGN_DIRECTORY = 'design';

    /**
     * The single file permitted to hold the literal name.
     */
    private const string CONFIGURATION_FILE = 'apps/server/config/identity.php';

    /**
     * Extensions whose bytes cannot be read as text, so a name in them would be a coincidence.
     *
     * @var list<string>
     */
    private const array BINARY_EXTENSIONS = ['ttf', 'otf', 'woff', 'woff2', 'png', 'jpg', 'jpeg', 'webp', 'ico', 'pdf'];

    public function testNoFileOfTheDesignSystemWritesTheProductName(): void
    {
        // Arrange
        $name = self::configuredName();

        // Act
        $offenders = self::designFilesWriting($name);

        // Assert
        self::assertSame(
            [],
            $offenders,
            \sprintf(
                "The design system writes the product name \"%s\". It has one home, %s, and a rename has to be\n"
                . "a change to that value alone. Describe the product generically here, or read the name from\n"
                . "the application identity where a screen renders it:\n  - %s",
                $name,
                self::CONFIGURATION_FILE,
                implode("\n  - ", $offenders),
            ),
        );
    }

    public function testTheScanReachesTheDesignSystem(): void
    {
        // The guarantee is worth exactly what the scan reaches. Comparing it against what the repository
        // publishes means a new kind of file in the design system is covered the day it appears.

        // Act
        $scanned = self::designFiles();

        // Assert
        self::assertNotEmpty($scanned, 'The scan read no design file at all; the guarantee above is inert.');
        self::assertContains(
            self::DESIGN_DIRECTORY . '/tokens/tokens.json',
            $scanned,
            'The token source is not in the scan, so the file most likely to describe the product is unread.',
        );
    }

    public function testTheScanReadsEveryKindOfFileTheDesignSystemPublishes(): void
    {
        // A name reaches a reader from a stylesheet as easily as from prose, so a scan restricted to source
        // extensions would leave the markdown a designer actually reads outside it.

        // Act
        $extensions = [];

        foreach (self::designFiles() as $relativePath) {
            $extensions[strtolower(pathinfo($relativePath, PATHINFO_EXTENSION))] = true;
        }

        // Assert
        foreach (['md', 'json', 'css', 'kt', 'swift', 'php'] as $expected) {
            self::assertArrayHasKey(
                $expected,
                $extensions,
                \sprintf('The design system publishes no .%s file, or the scan does not reach it.', $expected),
            );
        }
    }

    public function testTheGeneratedTypeNameIsNotMistakenForTheDisplayName(): void
    {
        // The complement. The generated theme is addressed by a type name built from the slug, and a check
        // that flagged it would be unusable: it would fail on an identifier nobody can rename anyway.

        // Arrange
        $name = self::configuredName();
        $identifier = str_replace(' ', '', $name) . 'Theme';

        // Act
        $flagged = self::textWrites('object ' . $identifier . ' {', $name);
        $caught = self::textWrites('A ' . $name . ' card', $name);

        // Assert
        self::assertFalse($flagged, 'A generated type name derived from the slug must not count as the name.');
        self::assertTrue($caught, 'The name written in prose must count.');
    }

    /**
     * Files of the design system that write the name, as `path:line`.
     *
     * @return list<string>
     */
    private static function designFilesWriting(string $name): array
    {
        $root = self::repositoryRoot();
        $offenders = [];

        foreach (self::designFiles() as $relativePath) {
            if (\in_array(strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)), self::BINARY_EXTENSIONS, true)) {
                continue;
            }

            $contents = @file_get_contents($root . '/' . $relativePath);

            if (!\is_string($contents) || !mb_check_encoding($contents, 'UTF-8')) {
                continue;
            }

            foreach (explode("\n", $contents) as $number => $line) {
                if (self::textWrites($line, $name)) {
                    $offenders[] = \sprintf('%s:%d', $relativePath, $number + 1);
                }
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * Whether a fragment of text writes the display name.
     *
     * The name is matched with the spacing it is written with, so an identifier that runs the words together
     * is not a mention: those are built from the stable slug and are nobody's to rename.
     */
    private static function textWrites(string $text, string $name): bool
    {
        return stripos($text, $name) !== false;
    }

    /**
     * Every file the design system publishes, as repository-relative paths.
     *
     * @return list<string>
     */
    private static function designFiles(): array
    {
        $command = \sprintf(
            'git -C %s ls-files --cached --others --exclude-standard -- %s 2>/dev/null',
            escapeshellarg(self::repositoryRoot()),
            escapeshellarg(self::DESIGN_DIRECTORY),
        );

        $output = [];
        $status = 0;

        exec($command, $output, $status);

        if ($status !== 0) {
            self::fail(
                'Could not list the design system with git. This guarantee scans what the repository '
                . 'publishes, so it needs a working tree with a git history.',
            );
        }

        $files = array_values(array_filter($output, static fn(string $line): bool => $line !== ''));
        sort($files);

        return $files;
    }

    /**
     * @return non-empty-string
     */
    private static function configuredName(): string
    {
        $configuration = require self::repositoryRoot() . '/' . self::CONFIGURATION_FILE;

        if (!\is_array($configuration)) {
            self::fail(self::CONFIGURATION_FILE . ' must return an array.');
        }

        $name = $configuration['name'] ?? null;

        if (!\is_string($name) || $name === '') {
            self::fail(self::CONFIGURATION_FILE . ' must define a non-empty "name".');
        }

        return $name;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
