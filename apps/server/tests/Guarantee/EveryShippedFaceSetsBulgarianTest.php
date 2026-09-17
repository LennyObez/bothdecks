<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use BothDecks\Tests\Guarantee\Support\OpenTypeFace;
use BothDecks\Tests\Guarantee\Support\ShippedFaces;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: every typeface the product ships sets Bulgarian in Bulgarian letterforms.
 *
 * Several Bulgarian lower-case letters have shapes that differ from the Russian ones for the same code
 * points, and to a Bulgarian reader the Russian forms read as a foreign hand. The code points do not say
 * which shapes are drawn: what decides is a `cyrl` script with a `BGR` language system listing `locl`. A face
 * without it has no alternative shapes to swap in, and no stylesheet can produce them. That makes this a
 * property of the binary, and it is checked on every shipped face, because a display face used for one word
 * in a heading is the word a reader looks at first.
 *
 * @see https://learn.microsoft.com/typography/opentype/spec/gsub
 */
#[Group('guarantee')]
final class EveryShippedFaceSetsBulgarianTest extends TestCase
{
    private const string CYRILLIC_SCRIPT = 'cyrl';

    private const string BULGARIAN_LANGUAGE_SYSTEM = 'BGR';

    /**
     * The feature that substitutes the local letterforms. Without it the language system is an empty promise.
     */
    private const string LOCALISED_FORMS_FEATURE = 'locl';

    /**
     * Fonts written by this test, removed when it ends.
     *
     * @var list<string>
     */
    private array $fixtureFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtureFiles as $path) {
            @unlink($path);
        }

        $this->fixtureFiles = [];
    }

    public function testEveryFamilyTheDesignNamesShipsAMasterInTheRepository(): void
    {
        // A family name in a stylesheet is a request, not a typeface. If the binary is not in the repository
        // the product renders in whatever the platform substitutes, which is a face nobody audited for any of
        // the properties below.

        // Arrange
        $faces = ShippedFaces::at(self::repositoryRoot());
        $missing = [];

        // Act
        foreach ($faces->families() as $role => $family) {
            if ($faces->masterFor($family) === null) {
                $missing[] = \sprintf('%s is set in %s, which ships no master in %s', $role, $family, ShippedFaces::DIRECTORY);
            }
        }

        // Assert
        self::assertSame(
            [],
            $missing,
            "These typefaces are named by the design and shipped by nobody. Add the SFNT master, which is\n"
            . "also the form Android and iOS load, beside the web subsets:\n  - "
            . implode("\n  - ", $missing),
        );
    }

    public function testEveryWebSubsetTheSourceDeclaresIsPresent(): void
    {
        // The stylesheet is generated from these descriptors, so a descriptor pointing at a file that is not
        // there produces a stylesheet that silently serves nothing for the range it covers.

        // Arrange
        $faces = ShippedFaces::at(self::repositoryRoot());
        $missing = [];

        // Act
        foreach (array_keys($faces->families()) as $role) {
            foreach ($faces->subsetFilesOf($role) as $file) {
                if (!is_file($faces->directory() . '/' . $file)) {
                    $missing[] = ShippedFaces::DIRECTORY . '/' . $file . ' (declared by font.family.' . $role . ')';
                }
            }
        }

        // Assert
        self::assertSame(
            [],
            $missing,
            "The generated stylesheet serves these files and the repository does not carry them:\n  - "
            . implode("\n  - ", $missing),
        );
    }

    public function testEveryShippedFaceDeclaresTheBulgarianLanguageSystemWithLocalisedForms(): void
    {
        // Arrange
        $faces = ShippedFaces::at(self::repositoryRoot());
        $failures = [];

        // Act
        foreach ($faces->families() as $role => $family) {
            $master = $faces->masterFor($family);

            if ($master === null) {
                continue;
            }

            $failure = self::bulgarianShortfallIn($master, $family, $role);

            if ($failure !== null) {
                $failures[] = $failure;
            }
        }

        // Assert
        self::assertSame(
            [],
            $failures,
            "These faces cannot draw Bulgarian. No stylesheet, feature setting or stylistic set can produce\n"
            . "the letterforms: the alternatives are not in the binary. Ship a face that declares them:\n  - "
            . implode("\n  - ", $failures),
        );
    }

    public function testTheCheckIsRunAgainstAtLeastOneRealFace(): void
    {
        // Without this the guarantee above would report success on a repository that ships no typeface at
        // all, which is the state it exists to refuse.

        // Arrange
        $faces = ShippedFaces::at(self::repositoryRoot());

        // Assert
        self::assertNotEmpty($faces->families(), 'The design names no typeface, so nothing was checked.');
        self::assertNotEmpty(
            $faces->masters(),
            \sprintf('No typeface master is shipped in %s, so this guarantee measured nothing.', ShippedFaces::DIRECTORY),
        );
    }

    public function testAFaceDeclaringTheLanguageSystemIsAccepted(): void
    {
        // The reader is exercised in both directions against fonts built here, so a mistake in how the layout
        // table is walked cannot make every face look acceptable, or every face look broken.

        // Arrange
        $path = $this->fixtureFace(withBulgarian: true);

        // Act
        $shortfall = self::bulgarianShortfallIn($path, 'Fixture', 'fixture');

        // Assert
        self::assertNull($shortfall, 'A face that declares cyrl/BGR with locl must be accepted.');
    }

    public function testAFaceWithoutTheLanguageSystemIsRefused(): void
    {
        // Arrange
        $path = $this->fixtureFace(withBulgarian: false);

        // Act
        $shortfall = self::bulgarianShortfallIn($path, 'Fixture', 'fixture');

        // Assert
        self::assertIsString($shortfall, 'A face whose cyrl script has no BGR language system must be refused.');
        self::assertStringContainsString(self::BULGARIAN_LANGUAGE_SYSTEM, $shortfall);
    }

    /**
     * What a face is missing for Bulgarian, or null when it is missing nothing.
     */
    private static function bulgarianShortfallIn(string $path, string $family, string $role): ?string
    {
        try {
            $face = OpenTypeFace::fromFile($path);
            $systems = $face->languageSystems('GSUB');
        } catch (\RuntimeException $failure) {
            return \sprintf('%s (%s): %s', $family, $role, $failure->getMessage());
        }

        if (!isset($systems[self::CYRILLIC_SCRIPT])) {
            return \sprintf(
                '%s (%s) declares no `%s` script, so it draws no Cyrillic shapes of its own',
                $family,
                $role,
                self::CYRILLIC_SCRIPT,
            );
        }

        if (!\in_array(self::BULGARIAN_LANGUAGE_SYSTEM, $systems[self::CYRILLIC_SCRIPT], true)) {
            return \sprintf(
                '%s (%s) declares `%s` with the language systems [%s] and no `%s`, so Bulgarian is drawn in '
                . 'Russian letterforms',
                $family,
                $role,
                self::CYRILLIC_SCRIPT,
                implode(', ', $systems[self::CYRILLIC_SCRIPT]),
                self::BULGARIAN_LANGUAGE_SYSTEM,
            );
        }

        $features = $face->featureTags('GSUB', self::CYRILLIC_SCRIPT, self::BULGARIAN_LANGUAGE_SYSTEM);

        if (!\in_array(self::LOCALISED_FORMS_FEATURE, $features, true)) {
            return \sprintf(
                '%s (%s) declares `%s/%s` but lists no `%s` under it, so the language system substitutes '
                . 'nothing',
                $family,
                $role,
                self::CYRILLIC_SCRIPT,
                self::BULGARIAN_LANGUAGE_SYSTEM,
                self::LOCALISED_FORMS_FEATURE,
            );
        }

        return null;
    }

    /**
     * Build the smallest font that answers the question this guarantee asks: an SFNT carrying one `GSUB`
     * whose `cyrl` script does, or does not, offer a Bulgarian language system with localised forms.
     */
    private function fixtureFace(bool $withBulgarian): string
    {
        $languageSystems = $withBulgarian ? [self::BULGARIAN_LANGUAGE_SYSTEM] : [];
        $gsub = self::buildGsub($languageSystems);

        $offset = 12 + 16;
        $sfnt = pack('Nnnnn', 0x00010000, 1, 16, 0, 0)
            . 'GSUB' . pack('NNN', 0, $offset, \strlen($gsub))
            . $gsub;

        $path = sys_get_temp_dir() . '/bd-fixture-face-' . bin2hex(random_bytes(8)) . '.ttf';
        file_put_contents($path, $sfnt);
        $this->fixtureFiles[] = $path;

        return $path;
    }

    /**
     * A minimal `GSUB`: one script, one default language system, optionally one more, and one feature.
     *
     * @param list<string> $languageSystems
     */
    private static function buildGsub(array $languageSystems): string
    {
        $count = \count($languageSystems);

        // LangSys records follow the script header, then the default language system, then the others.
        $scriptHeaderLength = 4 + 6 * $count;
        $defaultLangSys = $scriptHeaderLength;
        $langSys = pack('nnnn', 0, 0xFFFF, 1, 0);

        $script = pack('nn', $defaultLangSys, $count);
        $offsets = $defaultLangSys + \strlen($langSys);

        foreach ($languageSystems as $index => $tag) {
            $script .= str_pad($tag, 4) . pack('n', $offsets + $index * \strlen($langSys));
        }

        $script .= str_repeat($langSys, $count + 1);

        $scriptList = pack('n', 1) . self::CYRILLIC_SCRIPT . pack('n', 2 + 6);
        $scriptList .= $script;

        $featureList = pack('n', 1) . self::LOCALISED_FORMS_FEATURE . pack('n', 2 + 6) . pack('nn', 0, 0);

        $header = 10;

        return pack('nnnnn', 1, 0, $header, $header + \strlen($scriptList), $header + \strlen($scriptList) + \strlen($featureList))
            . $scriptList
            . $featureList
            . pack('n', 0);
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
