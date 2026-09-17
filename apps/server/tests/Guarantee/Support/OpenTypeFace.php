<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee\Support;

/**
 * A dependency-free reader for the parts of a font binary that the typography guarantees measure: the
 * character map, and the script and language systems declared in the layout tables.
 *
 * Both properties are properties of the binary rather than of the name: a face can carry every Cyrillic code
 * point and still set Bulgarian in Russian letterforms, because the letterforms live behind a `BGR` language
 * system in `GSUB`. Two containers are read, a bare `SFNT` and a zlib-wrapped `WOFF`. A `WOFF2` is refused,
 * because reading Brotli needs an extension the manifest does not require, and a guarantee that cannot read
 * what ships passes without measuring.
 *
 * @see https://learn.microsoft.com/typography/opentype/spec/otff
 */
final class OpenTypeFace
{
    /**
     * @param array<string, string> $tables raw table contents, keyed by their four-character tag
     */
    private function __construct(
        private readonly array $tables,
        private readonly string $path,
    ) {}

    /**
     * @throws \RuntimeException when the file is not a font this reader can measure
     */
    public static function fromFile(string $path): self
    {
        $bytes = @file_get_contents($path);

        if (!\is_string($bytes) || $bytes === '') {
            throw new \RuntimeException(\sprintf('The font file `%s` could not be read.', $path));
        }

        $signature = substr($bytes, 0, 4);

        if ($signature === 'wOF2') {
            throw new \RuntimeException(\sprintf(
                'The font file `%s` is a WOFF2. Its tables are Brotli-compressed, which this guarantee cannot '
                . 'read without an extension the manifest does not require, and the mobile platforms cannot '
                . 'load one either. Ship the SFNT master (.ttf or .otf) beside the web subsets.',
                $path,
            ));
        }

        if ($signature === 'wOFF') {
            return new self(self::readWoffTables($bytes, $path), $path);
        }

        if ($signature === "\x00\x01\x00\x00" || $signature === 'OTTO' || $signature === 'true') {
            return new self(self::readSfntTables($bytes, $path), $path);
        }

        throw new \RuntimeException(\sprintf('The file `%s` is not an OpenType font.', $path));
    }

    public function path(): string
    {
        return $this->path;
    }

    public function hasTable(string $tag): bool
    {
        return isset($this->tables[$tag]);
    }

    /**
     * Every code point the character map assigns to a glyph.
     *
     * @return array<int, true> keyed by code point, so membership is a single lookup
     */
    public function codepoints(): array
    {
        $cmap = $this->tables['cmap'] ?? null;

        if ($cmap === null) {
            throw new \RuntimeException(\sprintf('The font `%s` has no character map.', $this->path));
        }

        $subtableCount = self::u16($cmap, 2, $this->path);
        $chosen = null;
        $chosenRank = -1;

        // Preference order: full-repertoire Unicode first, then the basic-plane subtables. A face whose only
        // subtable is a legacy one is still read, so a real gap is never hidden behind "unsupported".
        $ranks = ['3/10' => 4, '0/4' => 3, '0/6' => 3, '3/1' => 2, '0/3' => 1, '0/1' => 0, '0/0' => 0];

        for ($index = 0; $index < $subtableCount; ++$index) {
            $record = 4 + 8 * $index;
            $platform = self::u16($cmap, $record, $this->path);
            $encoding = self::u16($cmap, $record + 2, $this->path);
            $offset = self::u32($cmap, $record + 4, $this->path);
            $rank = $ranks[$platform . '/' . $encoding] ?? -1;

            if ($rank > $chosenRank) {
                $chosenRank = $rank;
                $chosen = $offset;
            }
        }

        if ($chosen === null) {
            throw new \RuntimeException(\sprintf('The font `%s` declares no usable character map.', $this->path));
        }

        return match (self::u16($cmap, $chosen, $this->path)) {
            4 => self::readCmapFormat4($cmap, $chosen, $this->path),
            12 => self::readCmapFormat12($cmap, $chosen, $this->path),
            6 => self::readCmapFormat6($cmap, $chosen, $this->path),
            default => throw new \RuntimeException(\sprintf(
                'The font `%s` uses a character-map format this guarantee does not read.',
                $this->path,
            )),
        };
    }

    /**
     * The script tags a layout table declares, each with the language systems under it.
     *
     * Tags are returned trimmed of the padding spaces the format requires, so `BGR ` reads as `BGR`. The
     * default language system is reported as `dflt`, which is the tag the specification reserves for it.
     *
     * @return array<string, list<string>>
     */
    public function languageSystems(string $tableTag): array
    {
        $table = $this->tables[$tableTag] ?? null;

        if ($table === null) {
            return [];
        }

        $scriptList = self::u16($table, 4, $this->path);
        $scriptCount = self::u16($table, $scriptList, $this->path);
        $systems = [];

        for ($index = 0; $index < $scriptCount; ++$index) {
            $record = $scriptList + 2 + 6 * $index;
            $tag = rtrim(substr($table, $record, 4));
            $script = $scriptList + self::u16($table, $record + 4, $this->path);

            $languages = [];

            if (self::u16($table, $script, $this->path) !== 0) {
                $languages[] = 'dflt';
            }

            $languageCount = self::u16($table, $script + 2, $this->path);

            for ($language = 0; $language < $languageCount; ++$language) {
                $languages[] = rtrim(substr($table, $script + 4 + 6 * $language, 4));
            }

            $systems[$tag] = $languages;
        }

        return $systems;
    }

    /**
     * The feature tags reachable from one script and language system of a layout table.
     *
     * @return list<string>
     */
    public function featureTags(string $tableTag, string $scriptTag, string $languageTag): array
    {
        $table = $this->tables[$tableTag] ?? null;

        if ($table === null) {
            return [];
        }

        $scriptList = self::u16($table, 4, $this->path);
        $featureList = self::u16($table, 6, $this->path);
        $scriptCount = self::u16($table, $scriptList, $this->path);
        $languageSystem = null;

        for ($index = 0; $index < $scriptCount; ++$index) {
            $record = $scriptList + 2 + 6 * $index;

            if (rtrim(substr($table, $record, 4)) !== $scriptTag) {
                continue;
            }

            $script = $scriptList + self::u16($table, $record + 4, $this->path);

            if ($languageTag === 'dflt') {
                $default = self::u16($table, $script, $this->path);
                $languageSystem = $default === 0 ? null : $script + $default;

                break;
            }

            $languageCount = self::u16($table, $script + 2, $this->path);

            for ($language = 0; $language < $languageCount; ++$language) {
                $languageRecord = $script + 4 + 6 * $language;

                if (rtrim(substr($table, $languageRecord, 4)) === $languageTag) {
                    $languageSystem = $script + self::u16($table, $languageRecord + 4, $this->path);

                    break;
                }
            }

            break;
        }

        if ($languageSystem === null) {
            return [];
        }

        $featureCount = self::u16($table, $languageSystem + 4, $this->path);
        $tags = [];

        for ($index = 0; $index < $featureCount; ++$index) {
            $featureIndex = self::u16($table, $languageSystem + 6 + 2 * $index, $this->path);
            $tags[] = rtrim(substr($table, $featureList + 2 + 6 * $featureIndex, 4));
        }

        $required = self::u16($table, $languageSystem + 2, $this->path);

        if ($required !== 0xFFFF) {
            $tags[] = rtrim(substr($table, $featureList + 2 + 6 * $required, 4));
        }

        return array_values(array_unique($tags));
    }

    /**
     * @return array<string, string>
     */
    private static function readSfntTables(string $bytes, string $path): array
    {
        $count = self::u16($bytes, 4, $path);
        $tables = [];

        for ($index = 0; $index < $count; ++$index) {
            $record = 12 + 16 * $index;
            $tag = substr($bytes, $record, 4);
            $offset = self::u32($bytes, $record + 8, $path);
            $length = self::u32($bytes, $record + 12, $path);
            $table = substr($bytes, $offset, $length);

            if (\strlen($table) !== $length) {
                throw new \RuntimeException(\sprintf('The table `%s` in `%s` is truncated.', $tag, $path));
            }

            $tables[$tag] = $table;
        }

        return $tables;
    }

    /**
     * @return array<string, string>
     */
    private static function readWoffTables(string $bytes, string $path): array
    {
        $count = self::u16($bytes, 12, $path);
        $tables = [];

        for ($index = 0; $index < $count; ++$index) {
            $record = 44 + 20 * $index;
            $tag = substr($bytes, $record, 4);
            $offset = self::u32($bytes, $record + 4, $path);
            $compressed = self::u32($bytes, $record + 8, $path);
            $original = self::u32($bytes, $record + 12, $path);
            $stored = substr($bytes, $offset, $compressed);

            if (\strlen($stored) !== $compressed) {
                throw new \RuntimeException(\sprintf('The table `%s` in `%s` is truncated.', $tag, $path));
            }

            if ($compressed < $original) {
                $inflated = @gzuncompress($stored);

                if (!\is_string($inflated)) {
                    throw new \RuntimeException(\sprintf('The table `%s` in `%s` could not be inflated.', $tag, $path));
                }

                $stored = $inflated;
            }

            $tables[$tag] = $stored;
        }

        return $tables;
    }

    /**
     * @return array<int, true>
     */
    private static function readCmapFormat4(string $cmap, int $start, string $path): array
    {
        $segments = intdiv(self::u16($cmap, $start + 6, $path), 2);
        $ends = $start + 14;
        $starts = $ends + 2 * $segments + 2;
        $deltas = $starts + 2 * $segments;
        $ranges = $deltas + 2 * $segments;
        $codepoints = [];

        for ($segment = 0; $segment < $segments; ++$segment) {
            $end = self::u16($cmap, $ends + 2 * $segment, $path);
            $first = self::u16($cmap, $starts + 2 * $segment, $path);
            $delta = self::u16($cmap, $deltas + 2 * $segment, $path);
            $rangeOffset = self::u16($cmap, $ranges + 2 * $segment, $path);

            if ($first > $end) {
                continue;
            }

            for ($codepoint = $first; $codepoint <= min($end, 0xFFFF); ++$codepoint) {
                if ($rangeOffset === 0) {
                    $glyph = ($codepoint + $delta) & 0xFFFF;
                } else {
                    $at = $ranges + 2 * $segment + $rangeOffset + 2 * ($codepoint - $first);

                    if ($at + 1 >= \strlen($cmap)) {
                        continue;
                    }

                    $glyph = self::u16($cmap, $at, $path);
                    $glyph = $glyph === 0 ? 0 : ($glyph + $delta) & 0xFFFF;
                }

                if ($glyph !== 0) {
                    $codepoints[$codepoint] = true;
                }
            }
        }

        return $codepoints;
    }

    /**
     * @return array<int, true>
     */
    private static function readCmapFormat6(string $cmap, int $start, string $path): array
    {
        $first = self::u16($cmap, $start + 6, $path);
        $count = self::u16($cmap, $start + 8, $path);
        $codepoints = [];

        for ($index = 0; $index < $count; ++$index) {
            if (self::u16($cmap, $start + 10 + 2 * $index, $path) !== 0) {
                $codepoints[$first + $index] = true;
            }
        }

        return $codepoints;
    }

    /**
     * @return array<int, true>
     */
    private static function readCmapFormat12(string $cmap, int $start, string $path): array
    {
        $groups = self::u32($cmap, $start + 12, $path);
        $codepoints = [];

        for ($group = 0; $group < $groups; ++$group) {
            $record = $start + 16 + 12 * $group;
            $first = self::u32($cmap, $record, $path);
            $last = self::u32($cmap, $record + 4, $path);

            for ($codepoint = $first; $codepoint <= $last; ++$codepoint) {
                $codepoints[$codepoint] = true;
            }
        }

        return $codepoints;
    }

    private static function u16(string $bytes, int $offset, string $path): int
    {
        if ($offset < 0 || $offset + 2 > \strlen($bytes)) {
            throw new \RuntimeException(\sprintf('Reading `%s` ran past the end of the file.', $path));
        }

        /** @var array{1: int} $unpacked */
        $unpacked = unpack('n', $bytes, $offset);

        return $unpacked[1];
    }

    private static function u32(string $bytes, int $offset, string $path): int
    {
        if ($offset < 0 || $offset + 4 > \strlen($bytes)) {
            throw new \RuntimeException(\sprintf('Reading `%s` ran past the end of the file.', $path));
        }

        /** @var array{1: int} $unpacked */
        $unpacked = unpack('N', $bytes, $offset);

        return $unpacked[1];
    }
}
