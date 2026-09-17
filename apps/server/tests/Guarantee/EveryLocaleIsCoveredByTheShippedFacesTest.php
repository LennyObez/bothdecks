<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use BothDecks\Tests\Guarantee\Support\OpenTypeFace;
use BothDecks\Tests\Guarantee\Support\ShippedFaces;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: in every language the product ships, every character it will draw is in the shipped typefaces.
 *
 * The product self-hosts its faces and owns the consequence: a character a face does not carry is drawn by a
 * substitute the design never chose, or as an empty box. The corpus is derived from three sources rather than
 * written down twice: the letters the language is written with, the symbols the platform's own formatter
 * produces for it, and its catalogue once the catalogues exist. A language whose catalogue is not written yet
 * is still checked against the first two.
 */
#[Group('guarantee')]
final class EveryLocaleIsCoveredByTheShippedFacesTest extends TestCase
{
    /**
     * Where the interface catalogues live once they exist, relative to the repository root.
     */
    private const string CATALOGUE_DIRECTORY = 'apps/server/resources/lang';

    /**
     * The configuration file that lists the locales the product serves.
     */
    private const string LOCALE_CONFIGURATION = 'apps/server/config/i18n.php';

    /**
     * The letters each official language of the European Union is written with, beyond the unaccented Latin
     * alphabet every locale shares.
     *
     * The list is the point of the guarantee, so it is spelled out rather than derived from a library: it is
     * what a reader of this file can check against their own language, and what a reviewer can argue with.
     *
     * @var array<string, string>
     */
    private const array ALPHABETS = [
        'bg' => 'АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЬЮЯабвгдежзийклмнопрстуфхцчшщъьюя',
        'cs' => 'ÁČĎÉĚÍŇÓŘŠŤÚŮÝŽáčďéěíňóřšťúůýž',
        'da' => 'ÆØÅæøå',
        'de' => 'ÄÖÜäöüß',
        'el' => 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩΆΈΉΊΌΎΏΪΫαβγδεζηθικλμνξοπρστυφχψωςάέήίόύώϊϋΐΰ',
        'en' => '',
        'es' => 'ÁÉÍÓÚÑÜáéíóúñü¿¡',
        'et' => 'ÄÖÜÕŠŽäöüõšž',
        'fi' => 'ÄÖÅäöå',
        'fr' => 'ÀÂÆÇÉÈÊËÎÏÔŒÙÛÜŸàâæçéèêëîïôœùûüÿ',
        'ga' => 'ÁÉÍÓÚáéíóú',
        'hr' => 'ČĆĐŠŽčćđšž',
        'hu' => 'ÁÉÍÓÖŐÚÜŰáéíóöőúüű',
        'it' => 'ÀÈÉÌÒÙàèéìíîòóù',
        'lt' => 'ĄČĘĖĮŠŲŪŽąčęėįšųūž',
        'lv' => 'ĀČĒĢĪĶĻŅŠŪŽāčēģīķļņšūž',
        'mt' => 'ĊĠĦŻċġħżàèìòù',
        'nl' => 'ÉËÏÖéëïöü',
        'pl' => 'ĄĆĘŁŃÓŚŹŻąćęłńóśźż',
        'pt' => 'ÃÁÀÂÇÉÊÍÓÕÔÚÜãáàâçéêíóõôúü',
        'ro' => 'ĂÂÎȘȚăâîșț',
        'sk' => 'ÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽáäčďéíĺľňóôŕšťúýž',
        'sl' => 'ČŠŽčšž',
        'sv' => 'ÅÄÖåäö',
    ];

    public function testEveryLocaleIsDrawnEntirelyByTheShippedFaces(): void
    {
        // Arrange
        $faces = self::readableFaces();
        $gaps = [];

        // Act
        foreach (self::locales() as $locale) {
            $required = self::requiredCodepoints($locale);

            foreach ($faces as $family => $codepoints) {
                $missing = self::missingFrom($required, $codepoints);

                if ($missing !== []) {
                    $gaps[] = \sprintf('%s in %s: %s', $locale, $family, implode(', ', $missing));
                }
            }
        }

        // Assert
        self::assertSame(
            [],
            $gaps,
            "These characters will be drawn by a face the design did not choose, or not drawn at all. Ship a\n"
            . "face that carries them, or subset one that does:\n  - "
            . implode("\n  - ", $gaps),
        );
    }

    public function testEveryLocaleTheProductShipsHasAnAlphabet(): void
    {
        // The corpus is only as complete as this list. A locale added to the configuration without its
        // alphabet would be checked against nothing and would pass in silence.

        // Act
        $unknown = array_values(array_diff(self::locales(), array_keys(self::ALPHABETS)));

        // Assert
        self::assertSame(
            [],
            $unknown,
            'These locales are served and this guarantee knows no alphabet for them: ' . implode(', ', $unknown),
        );
    }

    public function testTheAlphabetsCoverTheTwentyFourOfficialLanguages(): void
    {
        // The product's scope is the official languages of the Union. Stating the count here means a language
        // cannot quietly disappear from the corpus while the suite stays green.

        // Assert
        self::assertCount(
            24,
            self::ALPHABETS,
            'The product ships in the 24 official languages of the European Union; the corpus lists a '
            . 'different number.',
        );
    }

    public function testTheConfiguredLocalesAndTheCorpusAgreeOnceTheConfigurationExists(): void
    {
        // The list of locales belongs to the application, not to a test. While the configuration is not
        // there the corpus stands in for it; the moment it lands, the two are held to each other.

        // Arrange
        $path = self::repositoryRoot() . '/' . self::LOCALE_CONFIGURATION;

        if (!is_file($path)) {
            self::assertNotEmpty(
                self::ALPHABETS,
                'Neither the configuration nor the corpus lists a locale, so nothing is checked.',
            );

            return;
        }

        // Act
        $configured = self::configuredLocales($path);

        // Assert
        self::assertSame(
            [],
            array_values(array_diff($configured, array_keys(self::ALPHABETS))),
            'The application serves locales this guarantee has no alphabet for.',
        );
        self::assertSame(
            [],
            array_values(array_diff(array_keys(self::ALPHABETS), $configured)),
            'This guarantee checks locales the application does not serve.',
        );
    }

    public function testEveryServedLocaleHasACatalogueOnceTheCataloguesExist(): void
    {
        // A catalogue directory holding some languages and not others is the shape a translation effort takes
        // halfway through, and the shape it keeps if nothing notices.

        // Arrange
        $directory = self::repositoryRoot() . '/' . self::CATALOGUE_DIRECTORY;

        if (!is_dir($directory)) {
            self::assertNotEmpty(self::locales(), 'No locale is served, so nothing is checked.');

            return;
        }

        // Act
        $without = [];

        foreach (self::locales() as $locale) {
            if (self::catalogueFilesFor($locale) === []) {
                $without[] = $locale;
            }
        }

        // Assert
        self::assertSame([], $without, 'These served locales have no catalogue: ' . implode(', ', $without));
    }

    public function testTheFormattingSymbolsAreTakenFromThePlatformRatherThanAssumed(): void
    {
        // The separator in "1 234,50 €" is not a space in French and not the same character in every
        // language. Reading it from the platform's formatter is what keeps this corpus honest: the guarantee
        // then holds the typeface to what the product will actually print, not to what its author remembered.

        // Act
        $french = self::formattingCodepoints('fr');
        $german = self::formattingCodepoints('de');

        // Assert
        self::assertNotSame(
            $french,
            $german,
            'Two languages that group and punctuate numbers differently produced the same symbols; the '
            . 'formatter is not being read.',
        );
        self::assertNotEmpty($french, 'No formatting symbol was derived for French.');
    }

    public function testAFaceThatIsMissingACharacterIsDetected(): void
    {
        // The detection is exercised against a character no text face carries, so a mistake in how the
        // character map is read cannot make every face look complete.

        // Arrange
        $faces = self::readableFaces();

        // Act
        $undetected = [];

        foreach ($faces as $family => $codepoints) {
            // U+E000 is the first private-use code point: unassigned by Unicode, and not something a text
            // face has any reason to draw.
            if (self::missingFrom([0xE000], $codepoints) === []) {
                $undetected[] = $family;
            }
        }

        // Assert
        self::assertNotEmpty($faces, 'No face was read, so this guarantee measured nothing.');
        self::assertSame([], $undetected, 'A character no face carries was reported as present: ' . implode(', ', $undetected));
    }

    /**
     * The code points a locale needs: its alphabet, the plain Latin alphabet and punctuation every locale
     * shares, the symbols its number formatter produces, and everything in its catalogue.
     *
     * @return list<int>
     */
    private static function requiredCodepoints(string $locale): array
    {
        $required = [];

        for ($codepoint = 0x20; $codepoint <= 0x7E; ++$codepoint) {
            $required[$codepoint] = true;
        }

        foreach (self::codepointsOf(self::ALPHABETS[$locale] ?? '') as $codepoint) {
            $required[$codepoint] = true;
        }

        foreach (self::formattingCodepoints($locale) as $codepoint) {
            $required[$codepoint] = true;
        }

        foreach (self::catalogueFilesFor($locale) as $path) {
            $contents = @file_get_contents($path);

            if (\is_string($contents) && mb_check_encoding($contents, 'UTF-8')) {
                foreach (self::codepointsOf($contents) as $codepoint) {
                    $required[$codepoint] = true;
                }
            }
        }

        // A line break and a tab are structure, not glyphs, and no face draws them.
        unset($required[0x0A], $required[0x0D], $required[0x09]);

        $list = array_keys($required);
        sort($list);

        return $list;
    }

    /**
     * The characters the platform's own formatter produces for a locale: the grouping and decimal
     * separators, the minus sign, the digits and the currency symbol.
     *
     * @return list<int>
     */
    private static function formattingCodepoints(string $locale): array
    {
        $decimal = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $currency = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $percent = new \NumberFormatter($locale, \NumberFormatter::PERCENT);

        $printed = implode('', [
            (string) $decimal->format(-1234567.89),
            (string) $currency->formatCurrency(1234.5, 'EUR'),
            (string) $percent->format(0.42),
        ]);

        $printed = strtr($printed, self::declaredSubstitutions($locale));

        $codepoints = self::codepointsOf($printed);
        sort($codepoints);

        return array_values(array_unique($codepoints));
    }

    /**
     * The substitutions the design source declares for a locale, as a translation table.
     *
     * The platform decides how a number reads in a language, and this test exists to hold the shipped faces to
     * what the platform produces. A product may still depart from it, but only in the open: the departure is
     * written in the token source with its reason, it is applied here, and the test below refuses one that is
     * not necessary. A declaration that could be deleted without breaking anything is a declaration that has
     * become an excuse, so it fails.
     *
     * @return array<string, string>
     */
    private static function declaredSubstitutions(string $locale): array
    {
        $table = [];

        foreach (self::substitutionDeclarations() as $substitution) {
            if (($substitution['locale'] ?? null) !== $locale) {
                continue;
            }

            $table[self::characterOf($substitution['replace'] ?? '')] = self::characterOf($substitution['with'] ?? '');
        }

        return $table;
    }

    /** @return list<array<string, string>> */
    private static function substitutionDeclarations(): array
    {
        $source = json_decode((string) file_get_contents(self::repositoryRoot() . '/design/tokens/tokens.json'), true);

        self::assertIsArray($source, 'The design token source must be readable JSON.');

        $declarations = self::branch(self::branch(self::branch($source, '$extensions'), 'bothdecks.localeFormatting'), 'substitutions');

        $substitutions = [];

        foreach ($declarations as $declaration) {
            self::assertIsArray($declaration, 'Every declared substitution is an object.');

            $fields = [];

            foreach ($declaration as $key => $value) {
                if (\is_string($key) && \is_string($value)) {
                    $fields[$key] = $value;
                }
            }

            $substitutions[] = $fields;
        }

        return $substitutions;
    }

    /**
     * One level down a decoded JSON document, without asserting a shape the file is not obliged to have.
     *
     * An absent branch is an empty list, because the declaration is optional: a source that declares no
     * substitution is the normal case and must not fail here.
     *
     * @return array<mixed, mixed>
     */
    private static function branch(mixed $node, string $key): array
    {
        if (!\is_array($node) || !isset($node[$key]) || !\is_array($node[$key])) {
            return [];
        }

        return $node[$key];
    }

    /** `U+00A0` as the character it names. */
    private static function characterOf(string $notation): string
    {
        self::assertMatchesRegularExpression('/^U\+[0-9A-F]{4,6}$/', $notation, 'A declared substitution names its characters as `U+XXXX`.');

        return (string) mb_chr((int) hexdec(substr($notation, 2)), 'UTF-8');
    }

    /**
     * A declared departure from the platform is only allowed where the shipped faces force it.
     *
     * Without this, the declaration list is a way to make this test green by writing a line, which is the one
     * shape of exemption that always grows. With it, a declaration is a statement about the faces that the
     * faces themselves can contradict: ship a face carrying the character and the declaration fails until it
     * is removed.
     */
    public function testEveryDeclaredFormattingSubstitutionIsNecessary(): void
    {
        // Arrange
        $faces = self::readableFaces();
        $unnecessary = [];

        // Act
        foreach (self::substitutionDeclarations() as $substitution) {
            $replaced = self::characterOf($substitution['replace'] ?? '');
            $codepoint = mb_ord($replaced, 'UTF-8');

            self::assertIsInt($codepoint);

            $carriedBy = [];

            foreach ($faces as $family => $codepoints) {
                if (isset($codepoints[$codepoint])) {
                    $carriedBy[] = $family;
                }
            }

            if (\count($carriedBy) === \count($faces) && $faces !== []) {
                $unnecessary[] = \sprintf('%s substitutes %s, which every shipped face carries', $substitution['locale'] ?? '?', $substitution['replace'] ?? '?');
            }
        }

        // Assert
        self::assertSame(
            [],
            $unnecessary,
            "A locale substitution is declared for a character the shipped faces can already draw. Remove the\n"
            . "declaration and let the platform decide:\n  - "
            . implode("\n  - ", $unnecessary),
        );
    }

    /**
     * @return list<int>
     */
    private static function codepointsOf(string $text): array
    {
        $codepoints = [];

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $codepoint = mb_ord($character, 'UTF-8');

            if ($codepoint !== false) {
                $codepoints[] = $codepoint;
            }
        }

        return $codepoints;
    }

    /**
     * The code points of a locale's requirement that a face does not carry, named so the failure is readable.
     *
     * @param list<int> $required
     * @param array<int,true> $available
     *
     * @return list<string>
     */
    private static function missingFrom(array $required, array $available): array
    {
        $missing = [];

        foreach ($required as $codepoint) {
            if (!isset($available[$codepoint])) {
                $missing[] = \sprintf('U+%04X (%s)', $codepoint, mb_chr($codepoint, 'UTF-8'));
            }
        }

        return $missing;
    }

    /**
     * The character map of every face the design ships, keyed by family.
     *
     * @return array<string, array<int, true>>
     */
    private static function readableFaces(): array
    {
        $shipped = ShippedFaces::at(self::repositoryRoot());
        $faces = [];

        foreach ($shipped->families() as $family) {
            $master = $shipped->masterFor($family);

            if ($master === null) {
                self::fail(\sprintf(
                    'The design sets text in %s and the repository ships no master for it in %s, so this '
                    . 'guarantee cannot measure what the product will draw.',
                    $family,
                    ShippedFaces::DIRECTORY,
                ));
            }

            try {
                $faces[$family] = OpenTypeFace::fromFile($master)->codepoints();
            } catch (\RuntimeException $failure) {
                self::fail($failure->getMessage());
            }
        }

        return $faces;
    }

    /**
     * @return list<string>
     */
    private static function catalogueFilesFor(string $locale): array
    {
        $directory = self::repositoryRoot() . '/' . self::CATALOGUE_DIRECTORY . '/' . $locale;

        if (!is_dir($directory)) {
            return [];
        }

        $found = glob($directory . '/*');

        if ($found === false) {
            return [];
        }

        return array_values(array_filter($found, static fn(string $path): bool => is_file($path)));
    }

    /**
     * @return list<string>
     */
    private static function locales(): array
    {
        $path = self::repositoryRoot() . '/' . self::LOCALE_CONFIGURATION;

        if (is_file($path)) {
            return self::configuredLocales($path);
        }

        $locales = array_keys(self::ALPHABETS);
        sort($locales);

        return $locales;
    }

    /**
     * @return list<string>
     */
    private static function configuredLocales(string $path): array
    {
        $configuration = require $path;

        if (!\is_array($configuration)) {
            self::fail(self::LOCALE_CONFIGURATION . ' must return an array.');
        }

        $locales = $configuration['supported_locales'] ?? null;

        if (!\is_array($locales) || $locales === []) {
            self::fail(self::LOCALE_CONFIGURATION . ' must define a non-empty "supported_locales" list.');
        }

        $list = [];

        foreach ($locales as $locale) {
            if (\is_string($locale) && $locale !== '') {
                $list[] = $locale;
            }
        }

        sort($list);

        return $list;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
