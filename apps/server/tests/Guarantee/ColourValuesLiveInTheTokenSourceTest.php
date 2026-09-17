<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: a colour value is written once, in the design token source, and nowhere else.
 *
 * A colour in two files has two futures: the one in the source is audited for contrast and changed by editing
 * a line, the copy is audited by nobody. The product ships a website and two native applications from one
 * palette, so a second copy is not a duplicate value, it is a second palette. The scan runs over what git
 * would publish rather than a list of directories, so it cannot be outgrown, and it covers every notation the
 * three platforms use, since a rule enforced in one is evaded by writing the colour in another.
 */
#[Group('guarantee')]
final class ColourValuesLiveInTheTokenSourceTest extends TestCase
{
    /**
     * The one directory permitted to hold colour values: the token source and what is generated from it.
     */
    private const string TOKEN_DIRECTORY = 'design/tokens/';

    /**
     * Paths excluded from the scan, each with the reason it is not a colour the product paints.
     *
     * @var array<string, string>
     */
    private const array EXCLUDED_PREFIXES = [
        'apps/server/tests/' => 'a test of the colour rule has to be able to write a colour to check it is caught',
        '.github/labels.yml' => 'issue-label colours are repository metadata; they paint nothing in the product',
    ];

    /**
     * Extensions whose contents cannot carry a colour a renderer would read.
     *
     * @var list<string>
     */
    private const array BINARY_EXTENSIONS = ['ttf', 'otf', 'woff', 'woff2', 'png', 'jpg', 'jpeg', 'webp', 'ico', 'pdf'];

    public function testNoPublishedFileWritesAColourOutsideTheTokenSource(): void
    {
        // Act
        $offenders = self::coloursOutsideTheTokenSource();

        // Assert
        self::assertSame(
            [],
            $offenders,
            "These files write a colour value. Add it to design/tokens/tokens.json, regenerate, and read the\n"
            . "generated name instead, so that one edit changes the colour on the web, on Android and on iOS:\n  - "
            . implode("\n  - ", $offenders),
        );
    }

    public function testTheScanReachesTheTokenSourceItself(): void
    {
        // The exemption has to be live. If design/tokens/ fell outside the scan, the guarantee would pass
        // while colours were free to live anywhere the scan no longer reached.

        // Act
        $scanned = self::publishedFiles();

        // Assert
        self::assertNotEmpty($scanned, 'The scan read no files at all; the guarantee above is inert.');
        self::assertContains(
            self::TOKEN_DIRECTORY . 'tokens.json',
            $scanned,
            'The token source is not among the published files, so the exemption protects nothing.',
        );
    }

    public function testTheTokenSourceItselfCarriesColours(): void
    {
        // The complement of the exemption: if the source stopped holding colours, the rule above would be
        // satisfied by a palette that had moved somewhere unscanned.

        // Arrange
        $source = file_get_contents(self::repositoryRoot() . '/' . self::TOKEN_DIRECTORY . 'tokens.json');

        // Act
        $found = \is_string($source) ? self::coloursIn($source) : [];

        // Assert
        self::assertNotEmpty($found, 'The token source holds no colour value, so it is not the source of any.');
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function colourNotationProvider(): array
    {
        return [
            'CSS three-digit hexadecimal' => ['#e91', ['#e91']],
            'CSS six-digit hexadecimal' => ['color: #883871;', ['#883871']],
            'CSS eight-digit hexadecimal' => ['#883871cc', ['#883871cc']],
            'CSS functional red-green-blue' => ['rgba(16, 12, 9, 0.06)', ['rgba(16, 12, 9, 0.06)']],
            'CSS functional hue-saturation-lightness' => ['hsl(340 40% 30%)', ['hsl(340 40% 30%)']],
            'CSS wide-gamut lightness-chroma-hue' => ['oklch(0.47 0.13 340)', ['oklch(0.47 0.13 340)']],
            'Compose and SwiftUI packed integer' => ['Color(0xFF883871)', ['0xFF883871']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('colourNotationProvider')]
    public function testEveryNotationTheThreePlatformsUseIsDetected(string $source, array $expected): void
    {
        // A rule that only catches one spelling is a rule the next file evades by choosing another. Each
        // notation is exercised rather than assumed, because a wrong pattern would fail open and silently.

        // Act
        $found = self::coloursIn($source);

        // Assert
        self::assertSame($expected, $found);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function innocentTextProvider(): array
    {
        return [
            'a git revision in a link' => ['https://eur-lex.europa.eu/eli/reg/2016/679/oj#d1e3462-1-1'],
            'a fragment identifier' => ['See the section at #summary for the reasoning.'],
            'a unicode range' => ['unicode-range: U+0460-052F, U+1C80-1C88;'],
            'a hexadecimal that is not a colour length' => ['The digest begins 0x1f2 and ends there.'],
            'a description of the syntax rather than a colour' => ['Use `#rrggbb` or `rgba(r, g, b, a)`.'],
            'a format string that builds a colour' => ['sprintf("rgba(%d, %d, %d, %s)", $r, $g, $b, $a)'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('innocentTextProvider')]
    public function testOrdinaryTextIsNotMistakenForAColour(string $source): void
    {
        // The complement. A check that also flagged a document fragment or a revision would be turned off
        // within a week, and a guarantee nobody can live with protects nothing.

        // Act
        $found = self::coloursIn($source);

        // Assert
        self::assertSame([], $found, 'This text is not a colour and must not be flagged.');
    }

    /**
     * Published files that write a colour, as `path:line` with the offending value.
     *
     * @return list<string>
     */
    private static function coloursOutsideTheTokenSource(): array
    {
        $root = self::repositoryRoot();
        $offenders = [];

        foreach (self::publishedFiles() as $relativePath) {
            if (str_starts_with($relativePath, self::TOKEN_DIRECTORY) || self::isExcluded($relativePath)) {
                continue;
            }

            if (\in_array(strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)), self::BINARY_EXTENSIONS, true)) {
                continue;
            }

            $contents = @file_get_contents($root . '/' . $relativePath);

            if (!\is_string($contents) || !mb_check_encoding($contents, 'UTF-8')) {
                continue;
            }

            foreach (explode("\n", $contents) as $number => $line) {
                foreach (self::coloursIn($line) as $colour) {
                    $offenders[] = \sprintf('%s:%d writes %s', $relativePath, $number + 1, $colour);
                }
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * Every colour value in a fragment of text, in the order it appears.
     *
     * What is being looked for is a *value*, not a mention of the syntax. The hexadecimal pattern accepts
     * only the three lengths CSS defines as colours and refuses to match one that runs on into further
     * hexadecimal, so a revision or a fragment identifier is not a colour. The functional pattern requires
     * arguments made of numbers, so a docblock describing `rgba(r, g, b, a)` and a format string that builds
     * one are not colours either: they carry no value that can drift from the source.
     *
     * @return list<string>
     */
    private static function coloursIn(string $text): array
    {
        $found = [];
        $matches = [];

        if (preg_match_all('/#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![0-9a-fA-F])/', $text, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $match) {
                $found[$match[1]] = $match[0];
            }
        }

        $matches = [];

        if (preg_match_all('/\b0x[0-9a-fA-F]{8}\b/', $text, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $match) {
                $found[$match[1]] = $match[0];
            }
        }

        $matches = [];

        if (preg_match_all('/\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(([^()]*)\)/', $text, $matches, PREG_OFFSET_CAPTURE) !== false) {
            $arguments = $matches[1];

            foreach ($matches[0] as $index => $match) {
                $argument = $arguments[$index] ?? null;

                if ($argument !== null && self::isNumericArgumentList($argument[0])) {
                    $found[$match[1]] = $match[0];
                }
            }
        }

        ksort($found);

        return array_values($found);
    }

    /**
     * Whether the arguments of a functional colour are actual numbers rather than placeholders or prose.
     */
    private static function isNumericArgumentList(string $arguments): bool
    {
        if (preg_match('/[0-9]/', $arguments) !== 1) {
            return false;
        }

        return preg_match('/^(?:\s|[0-9.,%\/+-]|deg|turn|rad|grad|none)+$/', $arguments) === 1;
    }

    private static function isExcluded(string $relativePath): bool
    {
        foreach (array_keys(self::EXCLUDED_PREFIXES) as $prefix) {
            if ($relativePath === $prefix || str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Everything the repository publishes: what git tracks, plus what it would track on the next commit.
     *
     * A generated artefact is published the moment it is written, not the moment someone remembers to add
     * it, so a guarantee that read only the index would go quiet exactly while a new target was being built.
     *
     * @return list<string>
     */
    private static function publishedFiles(): array
    {
        $command = \sprintf(
            'git -C %s ls-files --cached --others --exclude-standard 2>/dev/null',
            escapeshellarg(self::repositoryRoot()),
        );

        $output = [];
        $status = 0;

        exec($command, $output, $status);

        if ($status !== 0 || $output === []) {
            self::fail(
                'Could not list published files with git. This guarantee scans what the repository publishes, '
                . 'so it needs a working tree with a git history.',
            );
        }

        $files = array_values(array_filter($output, static fn(string $line): bool => $line !== ''));
        sort($files);

        return $files;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
