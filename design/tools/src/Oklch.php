<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * The perceptual colour space the palette is authored in, rendered down to the sRGB fallback beside it.
 *
 * Every colour in the source carries both: the lightness, chroma and hue a designer reasons about, and the
 * hexadecimal triple every target actually paints. Only the second is emitted, which is what makes the first
 * worth checking: an unchecked component list is a comment, and a comment that drifts from the value beneath
 * it is worse than no comment, because the next person to move a step along the ramp will move the wrong one.
 *
 * The transform is Ottosson's, unmodified. Out-of-gamut components are clipped per channel, which is what a
 * browser does with `oklch()` outside sRGB, so a colour that cannot be shown is reported as the colour that
 * will be shown rather than as an error the source cannot act on.
 */
final class Oklch
{
    private function __construct(
        public readonly float $lightness,
        public readonly float $chroma,
        public readonly float $hue,
    ) {
    }

    /**
     * @param list<float>|list<int|float> $components lightness 0..1, chroma, hue in degrees
     */
    public static function fromComponents(array $components, string $tokenPath): self
    {
        if (count($components) !== 3) {
            throw new Failure(sprintf(
                'Token `%s` declares %d colour component%s where a lightness, a chroma and a hue are expected.',
                $tokenPath,
                count($components),
                count($components) === 1 ? '' : 's',
            ));
        }

        foreach ($components as $index => $component) {
            if (!is_int($component) && !is_float($component)) {
                throw new Failure(sprintf('Token `%s` declares a colour component at position %d that is not a number.', $tokenPath, $index));
            }
        }

        return new self((float) $components[0], (float) $components[1], (float) $components[2]);
    }

    /** Six hexadecimal digits, lowercase, the same spelling the source writes its fallbacks in. */
    public function hex(): string
    {
        [$red, $green, $blue] = $this->channels();

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    /**
     * The three 8-bit channels this colour renders to.
     *
     * @return array{int, int, int}
     */
    public function channels(): array
    {
        $radians = deg2rad($this->hue);
        $a = $this->chroma * cos($radians);
        $b = $this->chroma * sin($radians);

        $longCubeRoot = $this->lightness + 0.3963377774 * $a + 0.2158037573 * $b;
        $mediumCubeRoot = $this->lightness - 0.1055613458 * $a - 0.0638541728 * $b;
        $shortCubeRoot = $this->lightness - 0.0894841775 * $a - 1.2914855480 * $b;

        $long = $longCubeRoot ** 3;
        $medium = $mediumCubeRoot ** 3;
        $short = $shortCubeRoot ** 3;

        return [
            self::encode(4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short),
            self::encode(-1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short),
            self::encode(-0.0041960863 * $long - 0.7034186147 * $medium + 1.7076147010 * $short),
        ];
    }

    /** Linear light to an 8-bit sRGB channel, gamma and clipping included. */
    private static function encode(float $linear): int
    {
        $clipped = max(0.0, min(1.0, $linear));
        $encoded = $clipped <= 0.0031308
            ? 12.92 * $clipped
            : 1.055 * $clipped ** (1 / 2.4) - 0.055;

        return (int) round(max(0.0, min(1.0, $encoded)) * 255);
    }
}
