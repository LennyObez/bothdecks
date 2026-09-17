<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee\Support;

/**
 * The typefaces the design system ships, read from the token source and matched against the binaries in the
 * repository.
 *
 * Self-hosting makes a typeface a file rather than a name in a stylesheet, which is what makes both
 * typography guarantees measurable and why the two halves are checked against each other: a family with no
 * binary renders in a fallback nobody chose, and a binary nobody names is weight carried for nothing. The
 * master is the SFNT, because Compose and SwiftUI load one and cannot load a WOFF2, so a system shipping
 * only web subsets has shipped a typeface to one surface out of three.
 */
final class ShippedFaces
{
    /**
     * Where the binaries live, relative to the repository root.
     *
     * The generated stylesheet addresses them as `../fonts/…` from `design/tokens/generated/`, so this is the
     * directory the web already points at rather than a second one invented for the guarantees.
     */
    public const string DIRECTORY = 'design/tokens/fonts';

    /**
     * The token path holding the font stacks.
     */
    private const string FAMILY_GROUP = 'font.family';

    /**
     * The extension key under which a family records the subset files it serves to the web.
     */
    private const string FACE_EXTENSION = 'bothdecks.face';

    /**
     * @param array<string, string> $families role (display, text) to the first family in its stack
     * @param array<string, list<string>> $subsetFiles role to the web subset filenames it declares
     */
    private function __construct(
        private readonly array $families,
        private readonly array $subsetFiles,
        private readonly string $directory,
    ) {}

    public static function at(string $repositoryRoot): self
    {
        $tokens = DesignTokens::fromRepositoryRoot($repositoryRoot);
        $families = [];
        $subsetFiles = [];

        foreach ($tokens->pathsUnder(self::FAMILY_GROUP) as $path) {
            $role = substr($path, \strlen(self::FAMILY_GROUP) + 1);
            $stack = $tokens->resolve($path);

            if (!\is_array($stack) || !isset($stack[0]) || !\is_string($stack[0])) {
                continue;
            }

            $families[$role] = $stack[0];
            $subsetFiles[$role] = self::filesIn($tokens->extension($path, self::FACE_EXTENSION));
        }

        return new self($families, $subsetFiles, $repositoryRoot . '/' . self::DIRECTORY);
    }

    /**
     * The typeface each type role is set in, keyed by role.
     *
     * Only the first entry of each stack is a face the product ships; the rest are the fallbacks a browser
     * reaches for when it cannot, and they are nobody's to guarantee.
     *
     * @return array<string, string>
     */
    public function families(): array
    {
        return $this->families;
    }

    /**
     * The web subset filenames a role declares.
     *
     * @return list<string>
     */
    public function subsetFilesOf(string $role): array
    {
        return $this->subsetFiles[$role] ?? [];
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * The SFNT master shipped for a family, or null when none is present.
     *
     * The match is on the family name reduced to letters and digits, so `Noto Sans` is served by
     * `notosans.ttf` or `NotoSans[wght].ttf` without either spelling being privileged.
     */
    public function masterFor(string $family): ?string
    {
        $wanted = self::slug($family);

        foreach ($this->masters() as $path) {
            if (str_starts_with(self::slug(basename($path)), $wanted)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Every SFNT master in the fonts directory.
     *
     * @return list<string>
     */
    public function masters(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $entries = scandir($this->directory);

        if ($entries === false) {
            return [];
        }

        $masters = [];

        foreach ($entries as $entry) {
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

            if (\in_array($extension, ['ttf', 'otf'], true)) {
                $masters[] = $this->directory . '/' . $entry;
            }
        }

        sort($masters);

        return $masters;
    }

    /**
     * The family name reduced to the letters and digits that identify it, so that spelling, punctuation and
     * the axis suffix a variable binary carries do not decide whether a face is found.
     */
    private static function slug(string $name): string
    {
        $stripped = preg_replace('/\[.*$/', '', pathinfo($name, PATHINFO_FILENAME)) ?? $name;

        return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $stripped));
    }

    /**
     * @return list<string>
     */
    private static function filesIn(mixed $faces): array
    {
        if (!\is_array($faces)) {
            return [];
        }

        $files = [];

        foreach ($faces as $face) {
            if (!\is_array($face)) {
                continue;
            }

            $file = $face['file'] ?? null;

            if (\is_string($file) && $file !== '') {
                $files[] = $file;
            }
        }

        return $files;
    }
}
