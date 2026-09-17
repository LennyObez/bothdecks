<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use BothDecks\Tests\Guarantee\Support\DesignTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: a colour written in a generated artefact is a colour the token source defines.
 *
 * Every other check reads the source. The contrast of a pair is measured there, and coverage counts the
 * source's tokens against the artefacts' names; both hold while an artefact carries a colour that is in no
 * token at all. A generated file is what a browser and a native screen actually read, so a value arriving
 * there from anywhere else is a colour nobody can change from the source and the next regeneration reverts
 * without trace. Reading the committed bytes answers a question the generator cannot answer for itself, which
 * is why this is not a diff against a regenerated copy. Colour only: it survives to all three targets
 * unchanged, while a size or a duration is transformed per platform and checking those would be the generator
 * written twice.
 *
 * @see ColourValuesLiveInTheTokenSourceTest
 */
#[Group('guarantee')]
final class EveryColourInAGeneratedArtefactComesFromTheTokenSourceTest extends TestCase
{
    private const string ARTEFACT_DIRECTORY = 'design/tokens/generated';

    /**
     * The generated artefacts, which are the design as the three platforms receive it.
     *
     * @var list<string>
     */
    private const array ARTEFACTS = [
        'colors.css',
        'foundations.css',
        'BothDecksTheme.kt',
        'BothDecksTheme.swift',
    ];

    public function testNoGeneratedArtefactWritesAColourTheSourceDoesNotDefine(): void
    {
        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $defined = $tokens->definedColours();
        $foreign = [];

        // Act
        foreach (self::ARTEFACTS as $artefact) {
            foreach (self::colourOccurrencesIn(self::artefactSource($artefact)) as $occurrence) {
                if (isset($defined[$occurrence['key']])) {
                    continue;
                }

                $foreign[] = \sprintf(
                    '%s/%s:%d writes %s, which resolves to %s and is in no token',
                    self::ARTEFACT_DIRECTORY,
                    $artefact,
                    $occurrence['line'],
                    $occurrence['literal'],
                    $occurrence['key'],
                );
            }
        }

        sort($foreign);

        // Assert
        self::assertSame(
            [],
            $foreign,
            "These generated files publish a colour the token source does not define, so the product paints a\n"
            . "colour no one can change from the source and no contrast audit has measured. Put the value in\n"
            . "design/tokens/tokens.json and regenerate, or undo the edit to the generated file:\n  - "
            . implode("\n  - ", $foreign),
        );
    }

    public function testTheArtefactsWereReadAndDoCarryColours(): void
    {
        // The guarantee above passes trivially over an empty file, an unreadable one, or a reader that has
        // stopped recognising the notation a target uses. Each artefact is therefore required to have been
        // read and to have yielded colours before its silence counts as good news.

        // Act & Assert
        foreach (self::ARTEFACTS as $artefact) {
            $occurrences = self::colourOccurrencesIn(self::artefactSource($artefact));

            self::assertNotEmpty($occurrences, \sprintf(
                'No colour was found in %s, so the provenance check passed over it without reading one. '
                . 'Either the artefact stopped carrying colours, or the notation it uses is one this '
                . 'guarantee no longer recognises.',
                $artefact,
            ));
        }
    }

    public function testTheSourceDefinesTheColoursThisGuaranteeChecksAgainst(): void
    {
        // The other half of the same worry. If the source were read as holding no colour, every colour in
        // every artefact would be reported at once, which is loud; but if it were read as holding a colour
        // for every possible value, nothing would ever be reported, which is silent. The size and the shape
        // of the set are asserted so that neither can happen unnoticed.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());

        // Act
        $defined = $tokens->definedColours();

        // Assert
        self::assertNotEmpty($defined, 'The token source was read as defining no colour at all.');

        foreach ($defined as $key => $paths) {
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}@[0-9a-f]{2}$/', $key);
            self::assertNotEmpty($paths, \sprintf('The colour %s is defined by no token path.', $key));
        }
    }

    public function testAColourReachesTheThreeTargetsUnderThreeSpellingsOfOneValue(): void
    {
        // The comparison is only meaningful because one colour has one canonical spelling. This checks that
        // claim on the artefacts themselves: a primitive the source states once is found in the stylesheet,
        // in the Kotlin theme and in the Swift theme, and all three resolve to the same key.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $defined = $tokens->definedColours();
        $shared = null;

        // Act
        foreach (array_keys($defined) as $key) {
            $found = 0;

            foreach (self::ARTEFACTS as $artefact) {
                foreach (self::colourOccurrencesIn(self::artefactSource($artefact)) as $occurrence) {
                    if ($occurrence['key'] === $key) {
                        ++$found;

                        break;
                    }
                }
            }

            if ($found >= 3) {
                $shared = $key;

                break;
            }
        }

        // Assert
        self::assertNotNull(
            $shared,
            'No colour of the source was found in three of the four artefacts, so either the targets no '
            . 'longer share a palette or the three notations are not all being read.',
        );
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function notationProvider(): array
    {
        // Every notation the four artefacts use, with the canonical key each must resolve to. A reader that
        // silently stopped recognising one of them would report a whole target as clean, so each is
        // exercised rather than assumed.
        return [
            'a stylesheet hexadecimal' => ['  --color-brand-600: #883871;', ['#883871@ff']],
            'a stylesheet short hexadecimal' => ['color: #e91;', ['#ee9911@ff']],
            'a stylesheet hexadecimal with alpha' => ['#883871cc', ['#883871@cc']],
            'a translucent shadow ink' => ['0px 1px 2px rgba(16, 12, 9, 0.06)', ['#100c09@0f']],
            'a fully transparent shadow ink' => ['rgba(16, 12, 9, 0)', ['#100c09@00']],
            'a veil' => ['  --surface-scrim: rgba(16, 12, 9, 0.45);', ['#100c09@73']],
            'an opaque functional colour' => ['rgb(136, 56, 113)', ['#883871@ff']],
            'a Compose packed integer' => ['    val brand600 = Color(0xFF883871)', ['#883871@ff']],
            'a SwiftUI packed integer with alpha' => ['Color(bdARGB: 0x0F100C09)', ['#100c09@0f']],
            'two colours on one line' => [
                'listOf(BdShadowLayer(0.dp, Color(0x0F100C09)), BdShadowLayer(0.dp, Color(0x0A100C09)))',
                ['#100c09@0f', '#100c09@0a'],
            ],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('notationProvider')]
    public function testEveryNotationTheArtefactsUseResolvesToOneCanonicalColour(string $line, array $expected): void
    {
        // Act
        $keys = array_column(self::colourOccurrencesIn($line), 'key');

        // Assert
        self::assertSame($expected, $keys);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function innocentTextProvider(): array
    {
        return [
            'a unicode range in a font face' => ['  unicode-range: U+0460-052F, U+1C80-1C88, U+20B4;'],
            'a fragment in a documentation link' => ['/* See https://www.w3.org/TR/WCAG22/#contrast-minimum */'],
            'a bit mask rather than a colour' => ['red: Double((argb >> 16) & 0xFF) / 255,'],
            'a duration written as a number' => ['  --duration-undo-window: 8000ms;'],
            'a cubic curve' => ['  --easing-standard: cubic-bezier(0.2, 0, 0, 1);'],
            'a prose mention of the notation' => ['/// An ARGB literal spelled 0xAARRGGBB, alpha included.'],
        ];
    }

    #[DataProvider('innocentTextProvider')]
    public function testTextThatIsNotAColourIsNotReportedAsOne(string $line): void
    {
        // The complement. A provenance check that flagged a unicode range or a bit mask would be read as
        // noise within a day, and a guarantee read as noise protects nothing.

        // Act
        $occurrences = self::colourOccurrencesIn($line);

        // Assert
        self::assertSame([], $occurrences, 'This text carries no colour value and must not be reported as one.');
    }

    /**
     * Every colour written in a fragment of text, with the line it is on and the value it resolves to.
     *
     * @return list<array{line: int, literal: string, key: string}>
     */
    private static function colourOccurrencesIn(string $text): array
    {
        $occurrences = [];

        foreach (explode("\n", $text) as $index => $line) {
            foreach (self::coloursOnOneLine($line) as $offset => $colour) {
                $occurrences[] = [
                    'line' => $index + 1,
                    'literal' => $colour['literal'],
                    'key' => $colour['key'],
                    'offset' => $offset,
                ];
            }
        }

        return array_map(
            static fn(array $occurrence): array => [
                'line' => $occurrence['line'],
                'literal' => $occurrence['literal'],
                'key' => $occurrence['key'],
            ],
            $occurrences,
        );
    }

    /**
     * The colours on one line, keyed by where each starts so that several on one line keep their order.
     *
     * @return array<int, array{literal: string, key: string}>
     */
    private static function coloursOnOneLine(string $line): array
    {
        $found = [];

        foreach (self::hexadecimalOn($line) as $offset => $colour) {
            $found[$offset] = $colour;
        }

        foreach (self::packedIntegersOn($line) as $offset => $colour) {
            $found[$offset] = $colour;
        }

        foreach (self::functionalOn($line) as $offset => $colour) {
            $found[$offset] = $colour;
        }

        ksort($found);

        return $found;
    }

    /**
     * @return array<int, array{literal: string, key: string}>
     */
    private static function hexadecimalOn(string $line): array
    {
        $matches = [];
        $found = [];

        // Only the lengths CSS calls a colour, and never one that runs on into further hexadecimal, so that
        // a document fragment or a digest is not read as a value.
        preg_match_all(
            '/#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![0-9a-fA-F])/',
            $line,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matches[0] as $match) {
            $colour = DesignTokens::normaliseHex($match[0]);

            if ($colour !== null) {
                $found[$match[1]] = [
                    'literal' => $match[0],
                    'key' => DesignTokens::colourKey($colour['hex'], $colour['alpha']),
                ];
            }
        }

        return $found;
    }

    /**
     * The packed literal Compose and SwiftUI take, which is alpha first.
     *
     * It is read only where it is being used as a colour, because the same eight digits are a bit mask in
     * the two lines of arithmetic that unpack one, and a guarantee that reported those would be reporting
     * the mechanism that makes the colours work.
     *
     * @return array<int, array{literal: string, key: string}>
     */
    private static function packedIntegersOn(string $line): array
    {
        $matches = [];
        $found = [];

        preg_match_all('/\bColor\((?:bdARGB:\s*)?(0x[0-9a-fA-F]{8})\b\)/', $line, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $match) {
            $digits = substr($match[0], 2);
            $found[$match[1]] = [
                'literal' => $match[0],
                'key' => DesignTokens::colourKey('#' . strtolower(substr($digits, 2)), (int) hexdec(substr($digits, 0, 2))),
            ];
        }

        return $found;
    }

    /**
     * @return array<int, array{literal: string, key: string}>
     */
    private static function functionalOn(string $line): array
    {
        $matches = [];
        $found = [];

        preg_match_all('/\brgba?\(([^()]*)\)/', $line, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => $match) {
            $arguments = self::numericArguments($matches[1][$index][0] ?? '');

            if (\count($arguments) < 3 || \count($arguments) > 4) {
                continue;
            }

            $channels = '';

            foreach (\array_slice($arguments, 0, 3) as $channel) {
                $channels .= \sprintf('%02x', (int) round(max(0.0, min(255.0, $channel))));
            }

            $alpha = \count($arguments) === 4 ? $arguments[3] : 1.0;
            $found[$match[1]] = [
                'literal' => $match[0],
                'key' => DesignTokens::colourKey('#' . $channels, (int) round(max(0.0, min(1.0, $alpha)) * 255.0)),
            ];
        }

        return $found;
    }

    /**
     * The arguments of a functional colour, when every one of them is a number.
     *
     * A docblock describing `rgba(r, g, b, a)` and a format string that builds one carry no value that can
     * drift from the source, so neither is a colour.
     *
     * @return list<float>
     */
    private static function numericArguments(string $arguments): array
    {
        $parts = preg_split('/[\s,\/]+/', trim($arguments)) ?: [];
        $numbers = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^-?(?:[0-9]+\.?[0-9]*|\.[0-9]+)%?$/', $part) !== 1) {
                return [];
            }

            $numbers[] = str_ends_with($part, '%')
                ? (float) substr($part, 0, -1) * 255.0 / 100.0
                : (float) $part;
        }

        return $numbers;
    }

    private static function artefactSource(string $artefact): string
    {
        $path = self::repositoryRoot() . '/' . self::ARTEFACT_DIRECTORY . '/' . $artefact;
        $contents = @file_get_contents($path);

        if (!\is_string($contents)) {
            self::fail(\sprintf(
                'The generated artefact %s/%s is missing, so the design as shipped cannot be checked against '
                . 'the design as written. Run `php design/tools/generate-tokens.php`.',
                self::ARTEFACT_DIRECTORY,
                $artefact,
            ));
        }

        return $contents;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
