<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * One colour, parsed once, rendered per platform.
 *
 * The alpha channel is carried through every representation. A scrim is the only role in the system that
 * needs it, and it is precisely the role the screens invented because the source could not express it, so
 * dropping alpha here would reopen that gap.
 */
final class Colour
{
    private function __construct(
        public readonly int $red,
        public readonly int $green,
        public readonly int $blue,
        public readonly float $alpha,
    ) {
    }

    public static function parse(mixed $value, string $tokenPath): self
    {
        if (is_array($value)) {
            return self::fromColourObject($value, $tokenPath);
        }

        if (!is_string($value)) {
            throw new Failure(sprintf(
                'Token `%s` carries a colour of type `%s`. A colour is either the object the format defines or a `#rrggbb` string.',
                $tokenPath,
                get_debug_type($value),
            ));
        }

        $value = trim($value);

        if (preg_match('/^#([0-9a-fA-F]{3,8})$/', $value, $m) === 1) {
            $digits = $m[1];

            $expand = static fn (string $pair): int => (int) hexdec($pair);

            return match (strlen($digits)) {
                3 => new self($expand($digits[0] . $digits[0]), $expand($digits[1] . $digits[1]), $expand($digits[2] . $digits[2]), 1.0),
                4 => new self($expand($digits[0] . $digits[0]), $expand($digits[1] . $digits[1]), $expand($digits[2] . $digits[2]), $expand($digits[3] . $digits[3]) / 255),
                6 => new self($expand(substr($digits, 0, 2)), $expand(substr($digits, 2, 2)), $expand(substr($digits, 4, 2)), 1.0),
                8 => new self($expand(substr($digits, 0, 2)), $expand(substr($digits, 2, 2)), $expand(substr($digits, 4, 2)), $expand(substr($digits, 6, 2)) / 255),
                default => throw new Failure(sprintf('Token `%s` carries `%s`, which is not a 3, 4, 6 or 8 digit hexadecimal colour.', $tokenPath, $value)),
            };
        }

        if (preg_match('/^rgba?\(\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*(?:,\s*([0-9.]+)\s*)?\)$/', $value, $m) === 1) {
            return new self((int) $m[1], (int) $m[2], (int) $m[3], isset($m[4]) && $m[4] !== '' ? (float) $m[4] : 1.0);
        }

        throw new Failure(sprintf(
            'Token `%s` carries the colour `%s`, which the generator cannot parse. Use `#rrggbb`, `#rrggbbaa` or `rgba(r, g, b, a)`.',
            $tokenPath,
            $value,
        ));
    }

    /**
     * The colour object the Design Tokens Format Module defines: a colour space, its components, and an sRGB
     * hexadecimal fallback.
     *
     * The fallback is what is read. It is the only representation the format guarantees can be rendered
     * without a colour-space conversion, and every target here consumes sRGB. A colour authored in a wide
     * gamut without a fallback is refused rather than converted, because a generator that implements its own
     * conversion emits a value nobody measured, and the contrast guarantee is measured on what ships.
     *
     * @param array<mixed, mixed> $value
     */
    private static function fromColourObject(array $value, string $tokenPath): self
    {
        $hex = $value['hex'] ?? null;

        if (!is_string($hex)) {
            throw new Failure(sprintf(
                'Token `%s` carries a colour object with no `hex` member, so there is nothing the generator can render. Add the sRGB fallback the format defines.',
                $tokenPath,
            ));
        }

        $colour = self::parse($hex, $tokenPath);

        $alpha = $value['alpha'] ?? null;

        if ($alpha === null) {
            return $colour;
        }

        if (!is_int($alpha) && !is_float($alpha)) {
            throw new Failure(sprintf(
                'Token `%s` carries an alpha of type `%s`. Alpha is a number between 0 and 1.',
                $tokenPath,
                get_debug_type($alpha),
            ));
        }

        if ($alpha < 0.0 || $alpha > 1.0) {
            throw new Failure(sprintf('Token `%s` carries an alpha of %s, which is outside 0 to 1.', $tokenPath, Naming::number((float) $alpha, 4)));
        }

        return new self($colour->red, $colour->green, $colour->blue, (float) $alpha);
    }

    public function isOpaque(): bool
    {
        return abs($this->alpha - 1.0) < 1e-9;
    }

    /** Six hexadecimal digits, or an `rgba()` triple with its alpha when the colour is translucent. */
    public function css(): string
    {
        if ($this->isOpaque()) {
            return sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);
        }

        return sprintf('rgba(%d, %d, %d, %s)', $this->red, $this->green, $this->blue, Naming::number($this->alpha, 4));
    }

    /** The alpha-first ARGB literal both `androidx.compose.ui.graphics.Color` and the Swift helper take. */
    public function argb(): string
    {
        return sprintf('0x%02X%02X%02X%02X', (int) round($this->alpha * 255), $this->red, $this->green, $this->blue);
    }

    /** Relative luminance, WCAG 2.2 section "relative luminance". Alpha is ignored: it is not composited here. */
    public function relativeLuminance(): float
    {
        $channel = static function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($this->red) + 0.7152 * $channel($this->green) + 0.0722 * $channel($this->blue);
    }

    public function contrastAgainst(self $other): float
    {
        $a = $this->relativeLuminance();
        $b = $other->relativeLuminance();

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }
}
