<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * Reading a token's value into something a target can be generated from.
 *
 * Two spellings are accepted for the measured types, the string form (`"16px"`, `"240ms"`) and the object
 * form the current W3C draft requires (`{"value": 16, "unit": "px"}`), because the source is mid-migration
 * between them and a generator that accepted only one would make the migration a flag day. Anything that is
 * neither is refused by name.
 */
final class Value
{
    /**
     * The furthest a stated value is read as being written to. Past this the difference between a literal and
     * a binary float is smaller than the difference the check is looking for.
     */
    private const MAXIMUM_STATED_DECIMALS = 6;

    /** @return array{value: float, unit: string} */
    public static function dimension(mixed $raw, string $path): array
    {
        if (is_array($raw) && isset($raw['value'], $raw['unit'])) {
            return ['value' => (float) $raw['value'], 'unit' => (string) $raw['unit']];
        }

        if (is_int($raw) || is_float($raw)) {
            return ['value' => (float) $raw, 'unit' => 'px'];
        }

        if (is_string($raw) && preg_match('/^(-?[0-9]*\.?[0-9]+)(px|rem|em|%)$/', trim($raw), $m) === 1) {
            return ['value' => (float) $m[1], 'unit' => $m[2]];
        }

        // A bare zero is unitless in CSS and appears that way in shadow shorthands and in `letterSpacing: 0`.
        if (is_string($raw) && preg_match('/^-?[0-9]*\.?[0-9]+$/', trim($raw)) === 1) {
            return ['value' => (float) trim($raw), 'unit' => 'px'];
        }

        throw new Failure(sprintf(
            'Token `%s` carries `%s`, which is not a dimension. Use `"16px"`, `"1rem"` or `{"value": 16, "unit": "px"}`.',
            $path,
            is_scalar($raw) ? (string) $raw : json_encode($raw),
        ));
    }

    /** A dimension rendered for CSS, unit included. */
    public static function dimensionCss(mixed $raw, string $path): string
    {
        $d = self::dimension($raw, $path);

        return Naming::number($d['value']) . $d['unit'];
    }

    /**
     * A dimension in density-independent units.
     *
     * `px` in this source means a CSS pixel at a scale factor of one, which is what `dp` on Android and a
     * point in SwiftUI both mean. `rem` is resolved against the 16px root the web assumes. `em` is refused
     * here: it is relative to a font size the caller has, so it is handled where that font size is known.
     */
    public static function scalar(mixed $raw, string $path): float
    {
        $d = self::dimension($raw, $path);

        return match ($d['unit']) {
            'px' => $d['value'],
            'rem' => $d['value'] * 16.0,
            default => throw new Failure(sprintf(
                'Token `%s` is `%s`, and `%s` has no fixed size on Android or iOS.',
                $path,
                Naming::number($d['value']) . $d['unit'],
                $d['unit'],
            )),
        };
    }

    /** Milliseconds. */
    public static function duration(mixed $raw, string $path): float
    {
        if (is_array($raw) && isset($raw['value'], $raw['unit'])) {
            $value = (float) $raw['value'];

            return match ((string) $raw['unit']) {
                'ms' => $value,
                's' => $value * 1000,
                default => throw new Failure(sprintf('Token `%s` uses the duration unit `%s`; only `ms` and `s` exist.', $path, $raw['unit'])),
            };
        }

        if (is_string($raw) && preg_match('/^([0-9]*\.?[0-9]+)(ms|s)$/', trim($raw), $m) === 1) {
            return $m[2] === 's' ? (float) $m[1] * 1000 : (float) $m[1];
        }

        throw new Failure(sprintf(
            'Token `%s` carries `%s`, which is not a duration. Use `"240ms"` or `{"value": 240, "unit": "ms"}`.',
            $path,
            is_scalar($raw) ? (string) $raw : json_encode($raw),
        ));
    }

    /** @return list<float> the four control-point coordinates */
    public static function cubicBezier(mixed $raw, string $path): array
    {
        if (!is_array($raw) || count($raw) !== 4) {
            throw new Failure(sprintf('Token `%s` must be four numbers to be a cubic bezier.', $path));
        }

        return array_map(static fn (mixed $n): float => (float) $n, array_values($raw));
    }

    /**
     * @return array{mass: float, stiffness: float, damping: float, dampingRatio: float, responseSeconds: float}
     *
     * The ratio is computed, never copied. The Compose API takes a damping *ratio* and the source carries a
     * damping *coefficient*; a value pasted across that boundary is out by a factor of forty, and the
     * arithmetic that separates them belongs in the generator rather than in a comment a developer reads.
     */
    public static function spring(mixed $raw, string $path): array
    {
        foreach (['mass', 'stiffness', 'dampingCoefficient'] as $member) {
            if (!is_array($raw) || !isset($raw[$member]) || !is_numeric($raw[$member])) {
                throw new Failure(
                    sprintf('Token `%s` must carry numeric `mass`, `stiffness` and `dampingCoefficient`.', $path),
                    ['The coefficient is spelled out because the platform APIs take a damping *ratio*, and a name that could mean either is how the two get confused.'],
                );
            }
        }

        $mass = (float) $raw['mass'];
        $stiffness = (float) $raw['stiffness'];
        $damping = (float) $raw['dampingCoefficient'];

        if ($mass <= 0.0 || $stiffness <= 0.0) {
            throw new Failure(sprintf('Token `%s` has a non-positive mass or stiffness.', $path));
        }

        $ratio = $damping / (2 * sqrt($stiffness * $mass));
        $response = 2 * M_PI * sqrt($mass / $stiffness);

        // The source carries the derived values as well, so that a reader of the token file sees what the
        // platform will be given. Carrying them means they can disagree with the physics they came from, which
        // is the failure this check exists to make impossible: an edited ratio that no longer matches its
        // coefficients would otherwise ship as an authored value nobody recomputed.
        self::agrees($raw, 'dampingRatio', $ratio, $path);
        self::agrees($raw, 'response', $response * 1000, $path, ' ms');

        return [
            'mass' => $mass,
            'stiffness' => $stiffness,
            'damping' => $damping,
            'dampingRatio' => $ratio,
            'responseSeconds' => $response,
        ];
    }

    /**
     * Refuse a stated value that its own inputs do not produce.
     *
     * The tolerance is the stated value's own last place, not a constant. A damping ratio written `0.98` is a
     * claim about two decimals and is satisfied by anything that rounds to it; the same value written
     * `0.98149` is a claim about five, and is held to five. A constant tolerance answers a different question
     * (it either rejects a correctly rounded two-decimal literal or lets a five-decimal one drift), and the
     * quantity being guarded against here is a damping *coefficient* pasted where a *ratio* belongs, which is
     * out by more than an order of magnitude and fails at any precision.
     *
     * @param array<mixed, mixed> $raw
     */
    private static function agrees(array $raw, string $member, float $derived, string $path, string $unit = ''): void
    {
        if (!isset($raw[$member])) {
            return;
        }

        $stated = $raw[$member];

        if (is_array($stated) && isset($stated['value']) && is_numeric($stated['value'])) {
            $stated = $stated['value'];
        }

        if (!is_numeric($stated)) {
            throw new Failure(sprintf('Token `%s` states a `%s` that is not a number.', $path, $member));
        }

        $stated = (float) $stated;
        $decimals = self::statedDecimals($stated);
        $tolerance = 0.5 * 10 ** -$decimals;

        if (abs($stated - $derived) > $tolerance) {
            throw new Failure(
                sprintf(
                    'Token `%s` states `%s` = %s%s, but its mass, stiffness and damping coefficient produce %s%s.',
                    $path,
                    $member,
                    Naming::number($stated, $decimals),
                    $unit,
                    Naming::number($derived, $decimals + 4),
                    $unit,
                ),
                [
                    sprintf('Stated to %d decimal%s, so it has to agree to %d.', $decimals, $decimals === 1 ? '' : 's', $decimals),
                    'Correct whichever of the two is wrong. A stated value and the physics it claims to summarise cannot both be authoritative.',
                ],
            );
        }
    }

    /**
     * How many decimals a number was written to.
     *
     * The count comes from the value itself rather than from the JSON text, because the text is gone by the
     * time the generator sees a number and reconstructing it through a printf format would put the answer at
     * the mercy of a runtime setting. The smallest rounding that leaves the value untouched is the precision
     * it was written at.
     */
    private static function statedDecimals(float $value): int
    {
        $epsilon = 1e-9 * max(1.0, abs($value));

        for ($decimals = 0; $decimals < self::MAXIMUM_STATED_DECIMALS; ++$decimals) {
            if (abs(round($value, $decimals) - $value) <= $epsilon) {
                return $decimals;
            }
        }

        return self::MAXIMUM_STATED_DECIMALS;
    }

    /**
     * @return list<array{dx: float, dy: float, blur: float, spread: float, colour: Colour}>
     *
     * An empty list means "no shadow" and is a legitimate answer: `elevation.0` is flat by design.
     */
    public static function shadow(mixed $raw, string $path): array
    {
        if ($raw === 'none' || $raw === null || $raw === []) {
            return [];
        }

        if (is_array($raw)) {
            $layers = isset($raw['color']) || isset($raw['offsetX']) ? [$raw] : $raw;
            $out = [];

            foreach ($layers as $layer) {
                if (!is_array($layer)) {
                    throw new Failure(sprintf('Token `%s` has a shadow layer that is not an object.', $path));
                }

                if (!isset($layer['color'])) {
                    // Not defaulted to black. The previous shells each invented their own dark shadow in an
                    // ink the palette does not contain; a shadow whose colour nobody chose is the start of
                    // that, so it is refused instead.
                    throw new Failure(sprintf('Token `%s` has a shadow layer with no colour.', $path));
                }

                $out[] = [
                    'dx' => self::scalar($layer['offsetX'] ?? '0px', $path),
                    'dy' => self::scalar($layer['offsetY'] ?? '0px', $path),
                    'blur' => self::scalar($layer['blur'] ?? '0px', $path),
                    'spread' => self::scalar($layer['spread'] ?? '0px', $path),
                    'colour' => Colour::parse($layer['color'], $path),
                ];
            }

            return $out;
        }

        if (!is_string($raw)) {
            throw new Failure(sprintf('Token `%s` carries a shadow the generator cannot read.', $path));
        }

        $out = [];

        foreach (self::splitTopLevel($raw) as $layer) {
            $out[] = self::cssShadowLayer($layer, $path);
        }

        return $out;
    }

    /** The layer as written, so CSS can keep the author's own shorthand rather than a reconstruction of it. */
    public static function shadowCss(mixed $raw, string $path): string
    {
        $layers = self::shadow($raw, $path);

        if ($layers === []) {
            return 'none';
        }

        $parts = [];

        foreach ($layers as $layer) {
            $parts[] = sprintf(
                '%spx %spx %spx%s %s',
                Naming::number($layer['dx']),
                Naming::number($layer['dy']),
                Naming::number($layer['blur']),
                abs($layer['spread']) < 1e-9 ? '' : ' ' . Naming::number($layer['spread']) . 'px',
                $layer['colour']->css(),
            );
        }

        return implode(', ', $parts);
    }

    /** @return list<string> */
    private static function splitTopLevel(string $value): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        foreach (str_split($value) as $char) {
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')') {
                --$depth;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /** @return array{dx: float, dy: float, blur: float, spread: float, colour: Colour} */
    private static function cssShadowLayer(string $layer, string $path): array
    {
        // Pull the colour out first: it is the only part that can contain spaces of its own.
        $colour = null;

        if (preg_match('/(rgba?\([^)]*\)|#[0-9a-fA-F]{3,8})/', $layer, $m) === 1) {
            $colour = Colour::parse($m[1], $path);
            $layer = str_replace($m[1], '', $layer);
        }

        if ($colour === null) {
            throw new Failure(sprintf('Token `%s` has a shadow layer with no colour: `%s`.', $path, $layer));
        }

        $lengths = array_values(array_filter(preg_split('/\s+/', trim($layer)) ?: [], static fn (string $s): bool => $s !== ''));

        if (count($lengths) < 2 || count($lengths) > 4) {
            throw new Failure(sprintf(
                'Token `%s` has a shadow layer with %d lengths; a shadow takes an x and y offset, then an optional blur and spread.',
                $path,
                count($lengths),
            ));
        }

        return [
            'dx' => self::scalar($lengths[0], $path),
            'dy' => self::scalar($lengths[1], $path),
            'blur' => isset($lengths[2]) ? self::scalar($lengths[2], $path) : 0.0,
            'spread' => isset($lengths[3]) ? self::scalar($lengths[3], $path) : 0.0,
            'colour' => $colour,
        ];
    }

    /**
     * A typography composite, with every member present.
     *
     * Every member but the feature settings is required. `lineHeight` is a unitless multiplier so that it
     * survives a reader enlarging the text; read as a length it would be `1.5px`, so it is refused as one and
     * the pixel value derived. Both are returned: CSS wants the multiplier, the native targets the resolved
     * size. `fontFeatureSettings` is not part of the format's composite, so it is read from the style's own
     * extensions, where the tabular and lining figure request lives.
     *
     * @return array{family: mixed, familyWritten: mixed, size: float, lineHeight: float, lineHeightRatio: float, weight: int, letterSpacingEm: float, features: ?string}
     */
    public static function typography(Token $token): array
    {
        $written = $token->written;
        $resolved = $token->resolved;

        if (!is_array($written) || !is_array($resolved)) {
            throw new Failure(sprintf('Token `%s` is typed `typography` but is not an object.', $token->path));
        }

        foreach (['fontFamily', 'fontSize', 'lineHeight', 'fontWeight', 'letterSpacing'] as $member) {
            if (!array_key_exists($member, $resolved)) {
                throw new Failure(sprintf('Type style `%s` is missing `%s`.', $token->path, $member));
            }
        }

        $size = self::scalar($resolved['fontSize'], $token->path);
        $ratio = self::lineHeightRatio($resolved['lineHeight'], $token->path);
        $tracking = self::letterSpacingEm($resolved['letterSpacing'], $size, $token->path);

        self::agreesWithStated($token, 'bothdecks.lineHeightPx', $size * $ratio, ' px', 'line height');
        self::agreesWithStated($token, 'bothdecks.letterSpacingProportional', $tracking, 'em', 'letter spacing');

        $features = $token->extension('bothdecks.fontFeatureSettings') ?? $resolved['fontFeatureSettings'] ?? null;

        return [
            'family' => $resolved['fontFamily'],
            'familyWritten' => $written['fontFamily'],
            'size' => $size,
            'lineHeight' => $size * $ratio,
            'lineHeightRatio' => $ratio,
            'weight' => (int) $resolved['fontWeight'],
            'letterSpacingEm' => $tracking,
            'features' => $features === null ? null : (string) $features,
        ];
    }

    /**
     * The line height as a multiple of the font size.
     *
     * A bare number is the multiplier. A length is refused rather than converted: `24` and `24px` would both
     * be read as a multiplier of 24 by the first rule and as a length by the second, and a type scale where
     * the two spellings mean things a factor of sixteen apart is not a scale anyone can read safely.
     */
    private static function lineHeightRatio(mixed $raw, string $path): float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        throw new Failure(
            sprintf('Type style `%s` states a line height of `%s`.', $path, is_scalar($raw) ? (string) $raw : json_encode($raw)),
            ['A line height is a unitless multiple of the font size, so that it scales when a reader enlarges the text.'],
        );
    }

    /**
     * Hold a value the source also states in prose-adjacent form to the value the composite produces.
     *
     * A style carries its line height twice, once as the multiplier the platforms are given and once as the
     * pixel value a designer reads, and the same for letter spacing. Two spellings of one quantity drift
     * apart the moment one of them is edited, so the derived one is authoritative and the stated one is
     * checked against it.
     */
    private static function agreesWithStated(Token $token, string $extension, float $derived, string $unit, string $quantity): void
    {
        $stated = $token->extension($extension);

        if ($stated === null) {
            return;
        }

        if (is_string($stated) && str_ends_with($stated, $unit)) {
            $stated = substr($stated, 0, -strlen($unit));
        }

        if (!is_numeric($stated)) {
            throw new Failure(sprintf('Type style `%s` states a `%s` that is not a number in `%s`.', $token->path, $quantity, $extension));
        }

        $stated = (float) $stated;
        $decimals = self::statedDecimals($stated);

        if (abs($stated - $derived) > 0.5 * 10 ** -$decimals) {
            throw new Failure(
                sprintf(
                    'Type style `%s` states a %s of %s%s in `%s`, but its members produce %s%s.',
                    $token->path,
                    $quantity,
                    Naming::number($stated, $decimals),
                    $unit,
                    $extension,
                    Naming::number($derived, $decimals + 4),
                    $unit,
                ),
                ['Correct whichever of the two is wrong. The composite is what reaches the platforms; the stated value is the one a reader checks it against.'],
            );
        }
    }

    /** Letter spacing normalised to em, whatever unit the source used. */
    private static function letterSpacingEm(mixed $raw, float $fontSize, string $path): float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        if ($raw === '0' || $raw === 0) {
            return 0.0;
        }

        $d = self::dimension($raw, $path);

        return match ($d['unit']) {
            'em' => $d['value'],
            'rem' => $d['value'] * 16.0 / $fontSize,
            'px' => $d['value'] / $fontSize,
            '%' => $d['value'] / 100,
            default => throw new Failure(sprintf('Type style `%s` has a letter spacing in `%s`.', $path, $d['unit'])),
        };
    }
}
