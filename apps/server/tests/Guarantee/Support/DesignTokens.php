<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee\Support;

use PHPUnit\Framework\Assert;

/**
 * A reader for the design token source, written for the guarantees and deliberately independent of the
 * generator that emits the platform artefacts.
 *
 * A guarantee that resolved its values by calling the generator's own resolver would prove only that the
 * generator agrees with itself. This class parses the same JSON with its own code, so a guarantee that passes
 * is a second opinion rather than an echo, and a defect in either resolver shows up as a disagreement.
 *
 * The source is the design token file; the shape it follows is the W3C design-token draft, where a node
 * carrying a `$value` is a token and a `$value` written as `{some.path}` is a reference to another token.
 */
final class DesignTokens
{
    /**
     * How deep a chain of references may go before it is treated as a loop.
     */
    private const int MAXIMUM_REFERENCE_DEPTH = 16;

    /**
     * @param array<string, mixed> $document
     * @param array<string, array<string, mixed>> $nodes token path to the whole token node
     */
    private function __construct(
        private readonly array $document,
        private readonly array $nodes,
        private readonly string $sourcePath,
    ) {}

    /**
     * The token source as the repository publishes it.
     */
    public static function fromRepositoryRoot(string $repositoryRoot): self
    {
        $path = $repositoryRoot . '/design/tokens/tokens.json';

        if (!is_file($path)) {
            Assert::fail(
                'The design token source is missing at design/tokens/tokens.json. Every design guarantee '
                . 'reads it, so its absence is a failure rather than a reason to skip.',
            );
        }

        $raw = file_get_contents($path);

        if (!\is_string($raw)) {
            Assert::fail('design/tokens/tokens.json could not be read.');
        }

        $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!\is_array($document)) {
            Assert::fail('design/tokens/tokens.json must hold a JSON object.');
        }

        /** @var array<string, mixed> $document */
        $nodes = [];
        self::collect($document, '', $nodes);

        return new self($document, $nodes, $path);
    }

    public function sourcePath(): string
    {
        return $this->sourcePath;
    }

    /**
     * The top-level groups the source declares, in source order, ignoring metadata keys.
     *
     * @return list<string>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (array_keys($this->document) as $key) {
            if (str_starts_with($key, '$')) {
                continue;
            }

            $groups[] = $key;
        }

        return $groups;
    }

    /**
     * Every token path the source defines.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return array_keys($this->nodes);
    }

    /**
     * Every token path under a prefix, as a list of paths.
     *
     * @return list<string>
     */
    public function pathsUnder(string $prefix): array
    {
        $paths = [];

        foreach (array_keys($this->nodes) as $path) {
            if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
                $paths[] = $path;
            }
        }

        sort($paths);

        return $paths;
    }

    public function has(string $path): bool
    {
        return \array_key_exists($path, $this->nodes);
    }

    /**
     * A product-specific extension recorded on the document as a whole, or null when it carries none.
     *
     * Some facts about a design belong to no single token: which pairs the layout composes, which surface is
     * painted edge to edge. The format keeps them in the document's own extensions, and a guarantee that
     * needs one reads it here rather than guessing at it from a name.
     */
    public function documentExtension(string $key): mixed
    {
        $extensions = $this->document['$extensions'] ?? null;

        if (!\is_array($extensions)) {
            return null;
        }

        return $extensions[$key] ?? null;
    }

    /**
     * A product-specific extension recorded on a token, or null when the token carries none.
     */
    public function extension(string $path, string $key): mixed
    {
        $node = $this->nodes[$path] ?? null;

        if ($node === null) {
            return null;
        }

        $extensions = $node['$extensions'] ?? null;

        if (!\is_array($extensions)) {
            return null;
        }

        return $extensions[$key] ?? null;
    }

    /**
     * The value at a path with every `{reference}` followed to the primitive it names.
     */
    public function resolve(string $path): mixed
    {
        $seen = [];
        $current = $path;

        for ($depth = 0; $depth < self::MAXIMUM_REFERENCE_DEPTH; ++$depth) {
            if (isset($seen[$current])) {
                Assert::fail(\sprintf('The token source contains a reference loop reaching `%s`.', $current));
            }

            $seen[$current] = true;

            if (!\array_key_exists($current, $this->nodes)) {
                Assert::fail(\sprintf('The token source defines no token at `%s`.', $current));
            }

            $value = $this->nodes[$current]['$value'] ?? null;
            $reference = self::referenceIn($value);

            if ($reference === null) {
                return $value;
            }

            $current = $reference;
        }

        Assert::fail(\sprintf('Following references from `%s` did not reach a value.', $path));
    }

    /**
     * The colour at a path, as a lower-case `#rrggbb`.
     *
     * The format writes a colour either as a hexadecimal string or as an object naming a colour space, its
     * components and the hexadecimal fallback the three targets render. Both are read here, because a reader
     * that understood only one would report a token as "not a colour" the day the source moved to the other,
     * and a guarantee that fails on the shape of a value says nothing about the value.
     *
     * Alpha is refused rather than silently dropped: a contrast ratio computed against a translucent colour
     * is a ratio against a colour nobody sees, and a guarantee that reports one is worse than no guarantee.
     * Ask {@see self::isTranslucent()} first where a translucent role can legitimately appear.
     */
    public function colour(string $path): string
    {
        $value = $this->resolve($path);
        $colour = self::colourIn($value);

        if ($colour === null) {
            Assert::fail(\sprintf('The token at `%s` is not a colour.', $path));
        }

        if ($colour['alpha'] !== 255) {
            Assert::fail(\sprintf(
                'The colour at `%s` carries an alpha channel. Contrast is measured between opaque colours, '
                . 'so what is drawn through this one has to be composited before it can be measured.',
                $path,
            ));
        }

        return $colour['hex'];
    }

    /**
     * Whether the colour at a path is drawn through rather than drawn on.
     *
     * A translucent role has no one colour: what a reader sees is the composite of the role and everything
     * behind it, which the token does not name and this reader cannot invent. Such a role is therefore left
     * out of the derived contrast pairs by the guarantees, and identified here rather than guessed at from
     * its name.
     */
    public function isTranslucent(string $path): bool
    {
        $colour = self::colourIn($this->resolve($path));

        return $colour !== null && $colour['alpha'] !== 255;
    }

    /**
     * Every colour the source defines, as `#rrggbb` and an 8-bit alpha, mapped to the token paths that
     * define it.
     *
     * Shadow tokens are walked into, because a shadow carries its ink inside a composite value and that ink
     * reaches the artefacts as a colour like any other. A colour the source defines nowhere is a colour no
     * artefact may publish, and this is the set that sentence is measured against.
     *
     * @return array<string, list<string>> `#rrggbb@aa` to the paths defining it, sorted by key
     */
    public function definedColours(): array
    {
        $colours = [];

        foreach ($this->nodes as $path => $node) {
            foreach (self::coloursWithin($node['$value'] ?? null) as $colour) {
                $key = self::colourKey($colour['hex'], $colour['alpha']);
                $colours[$key][] = $path;
            }
        }

        ksort($colours);

        foreach ($colours as $key => $paths) {
            $unique = array_values(array_unique($paths));
            sort($unique);
            $colours[$key] = $unique;
        }

        return $colours;
    }

    /**
     * The canonical spelling a colour is compared under: the opaque hexadecimal plus the alpha channel at the
     * eight bits every target renders it with.
     */
    public static function colourKey(string $hex, int $alpha): string
    {
        return strtolower($hex) . '@' . \sprintf('%02x', $alpha);
    }

    /**
     * A token value read as a colour, or null when it is not one.
     *
     * @return array{hex: string, alpha: int}|null
     */
    public static function colourIn(mixed $value): ?array
    {
        if (\is_string($value)) {
            $hex = self::normaliseHex($value);

            return $hex === null ? null : $hex;
        }

        if (!\is_array($value) || !\is_string($value['hex'] ?? null)) {
            return null;
        }

        $hex = self::normaliseHex($value['hex']);

        if ($hex === null) {
            return null;
        }

        $alpha = $value['alpha'] ?? null;

        if (\is_int($alpha) || \is_float($alpha)) {
            $hex['alpha'] = (int) round(max(0.0, min(1.0, (float) $alpha)) * 255.0);
        }

        return $hex;
    }

    /**
     * A hexadecimal colour in any of the lengths CSS defines, as `#rrggbb` and an 8-bit alpha.
     *
     * @return array{hex: string, alpha: int}|null
     */
    public static function normaliseHex(string $value): ?array
    {
        $hex = strtolower(trim($value));

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])([0-9a-f])?$/', $hex, $short) === 1) {
            $expanded = '#' . $short[1] . $short[1] . $short[2] . $short[2] . $short[3] . $short[3];
            $alpha = ($short[4] ?? '') === '' ? 'ff' : $short[4] . $short[4];

            return ['hex' => $expanded, 'alpha' => (int) hexdec($alpha)];
        }

        if (preg_match('/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $hex, $long) === 1) {
            return ['hex' => '#' . $long[1], 'alpha' => (int) hexdec(($long[2] ?? '') === '' ? 'ff' : $long[2])];
        }

        return null;
    }

    /**
     * Every colour inside a token value, however deeply a composite value nests it.
     *
     * @return list<array{hex: string, alpha: int}>
     */
    private static function coloursWithin(mixed $value): array
    {
        $colour = self::colourIn($value);

        if ($colour !== null) {
            return [$colour];
        }

        if (!\is_array($value)) {
            return [];
        }

        $colours = [];

        foreach ($value as $child) {
            foreach (self::coloursWithin($child) as $found) {
                $colours[] = $found;
            }
        }

        return $colours;
    }

    /**
     * The WCAG 2.x contrast ratio between two opaque `#rrggbb` colours, rounded to two decimals the way the
     * success criteria are written.
     */
    public static function contrastRatio(string $foreground, string $background): float
    {
        $first = self::relativeLuminance($foreground);
        $second = self::relativeLuminance($background);

        $lighter = max($first, $second);
        $darker = min($first, $second);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * WCAG relative luminance, implemented from the definition rather than taken from a library, so that the
     * number a guarantee reports can be checked by hand against the specification.
     */
    private static function relativeLuminance(string $hex): float
    {
        $channels = [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];

        $weights = [0.2126, 0.7152, 0.0722];
        $luminance = 0.0;

        foreach ($channels as $index => $channel) {
            $srgb = (float) $channel / 255.0;
            $linear = $srgb <= 0.03928 ? $srgb / 12.92 : ((($srgb + 0.055) / 1.055) ** 2.4);
            $luminance += $weights[$index] * $linear;
        }

        return $luminance;
    }

    /**
     * The path a `{reference}` names, or null when the value is a literal.
     */
    private static function referenceIn(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        if (preg_match('/^\{([^{}]+)\}$/', trim($value), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Walk the document and record every node carrying a `$value`.
     *
     * @param array<string, mixed> $node
     * @param array<string, array<string, mixed>> $into
     */
    private static function collect(array $node, string $prefix, array &$into): void
    {
        if (\array_key_exists('$value', $node)) {
            $into[$prefix] = $node;

            return;
        }

        foreach ($node as $key => $child) {
            // A JSON key that reads as a number arrives here as an integer, and a ramp step, a spacing step
            // and an elevation level are all spelled with digits. Every path segment is therefore a string
            // before anything looks at it.
            $segment = (string) $key;

            if (str_starts_with($segment, '$') || !\is_array($child)) {
                continue;
            }

            /** @var array<string, mixed> $child */
            self::collect($child, $prefix === '' ? $segment : $prefix . '.' . $segment, $into);
        }
    }
}
