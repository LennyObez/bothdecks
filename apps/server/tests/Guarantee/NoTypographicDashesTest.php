<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: no tracked file carries an em dash or an en dash.
 *
 * Long dashes read as machine-written prose. That perception is not universal and it may not last, but this
 * repository is read by people deciding whether its author writes carefully, and the cost of avoiding them is
 * a comma, a colon or a second sentence. Ordinary punctuation is never the weaker choice.
 *
 * The forbidden characters are built from their code points rather than written out, so this file does not
 * contain what it forbids. Writing them here would force the check either to fail on itself or to exempt
 * itself, and a guarantee with an exemption for its own file is not a guarantee.
 */
#[Group('guarantee')]
final class NoTypographicDashesTest extends TestCase
{
    /**
     * Files whose content is generated or reproduced, so their punctuation is not ours to choose. A path
     * ending in a slash names a directory.
     *
     * @var array<string, string>
     */
    private const array GENERATED = [
        'apps/server/composer.lock' => 'written by the dependency manager from upstream package descriptions',
        'apps/server/tests/Fixtures/taxonomy/snapshot/' => 'a slice of the reference classification, reproduced verbatim and verified by digest',
    ];

    public function testNoTrackedFileCarriesALongDash(): void
    {
        // Arrange
        $forbidden = self::forbiddenCharacters();

        // Act
        $offenders = self::trackedFilesCarrying($forbidden);

        // Assert
        self::assertSame(
            [],
            $offenders,
            "These tracked files carry a long dash. Replace it with a comma, a colon, or a second sentence:\n  - "
            . implode("\n  - ", $offenders),
        );
    }

    public function testTheCheckActuallyDetectsTheCharactersItForbids(): void
    {
        // A check built from code points would silently pass if a code point were wrong, so each is exercised
        // against a string that must be caught.

        // Arrange
        $forbidden = self::forbiddenCharacters();
        $undetected = [];

        // Act
        foreach ($forbidden as $name => $character) {
            if (!str_contains('before ' . $character . ' after', $character)) {
                $undetected[] = $name;
            }
        }

        // Assert
        self::assertSame([], $undetected, 'These characters were not detectable: ' . implode(', ', $undetected));
        self::assertCount(2, $forbidden, 'Both the em dash and the en dash must be covered.');
    }

    public function testAnOrdinaryHyphenIsNotFlagged(): void
    {
        // The complement: a check that also caught the hyphen would be unusable, since compound words need it.

        // Arrange
        $innocent = 'a well-known server-rendered page';

        // Act
        $flagged = self::carriesForbidden($innocent, self::forbiddenCharacters());

        // Assert
        self::assertFalse($flagged, 'An ordinary hyphen must not be flagged.');
    }

    public function testTheScanReadsRealTrackedFiles(): void
    {
        // Without this, a change in how tracked files are listed would let the check pass by reading nothing.

        // Act
        $files = self::readableTrackedFiles();

        // Assert
        self::assertNotEmpty($files, 'No tracked file was read; the check above is inert.');
    }

    /**
     * The characters this guarantee forbids, keyed by name and built from their code points.
     *
     * @return array<string, string>
     */
    private static function forbiddenCharacters(): array
    {
        return [
            'em dash' => mb_chr(0x2014, 'UTF-8'),
            'en dash' => mb_chr(0x2013, 'UTF-8'),
        ];
    }

    /**
     * @param array<string, string> $forbidden
     */
    private static function carriesForbidden(string $haystack, array $forbidden): bool
    {
        foreach ($forbidden as $character) {
            if (str_contains($haystack, $character)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $forbidden
     *
     * @return list<string>
     */
    private static function trackedFilesCarrying(array $forbidden): array
    {
        $offenders = [];

        foreach (self::readableTrackedFiles() as $relativePath => $contents) {
            if (self::isGenerated($relativePath)) {
                continue;
            }

            if (self::carriesForbidden($contents, $forbidden)) {
                $offenders[] = $relativePath;
            }
        }

        sort($offenders);

        return $offenders;
    }

    private static function isGenerated(string $relativePath): bool
    {
        foreach (array_keys(self::GENERATED) as $generated) {
            if ($relativePath === $generated || (str_ends_with($generated, '/') && str_starts_with($relativePath, $generated))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tracked text files, keyed by repository-relative path.
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
