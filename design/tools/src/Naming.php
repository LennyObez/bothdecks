<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * The one place a token path becomes a name in an emitted file.
 *
 * Naming lives here rather than in each emitter so that a path cannot acquire two spellings on two
 * platforms. A role that is `on-fill` on the web is `onFill` in Kotlin and Swift because these functions
 * say so, not because three authors happened to agree.
 */
final class Naming
{
    /** Split a path segment into its words, accepting both `on-fill` and `onFill` as input. */
    public static function words(string $segment): array
    {
        $spaced = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $segment);
        $spaced = str_replace(['-', '_', '.'], ' ', (string) $spaced);

        return array_values(array_filter(explode(' ', $spaced), static fn (string $w): bool => $w !== ''));
    }

    /** `['theme', 'light', 'text', 'onBrand']` becomes `theme-light-text-on-brand`. */
    public static function kebab(array $segments): string
    {
        $words = [];

        foreach ($segments as $segment) {
            foreach (self::words((string) $segment) as $word) {
                $words[] = strtolower($word);
            }
        }

        return implode('-', $words);
    }

    /** `['text', 'onBrand']` becomes `textOnBrand`; `['brand', '600']` becomes `brand600`. */
    public static function camel(array $segments): string
    {
        $out = '';

        foreach ($segments as $segment) {
            foreach (self::words((string) $segment) as $word) {
                $out .= $out === '' ? strtolower($word) : ucfirst(strtolower($word));
            }
        }

        return $out;
    }

    /** `['space', '4']` becomes `Space4`, for a type or a constant that must start with a letter. */
    public static function pascal(array $segments): string
    {
        return ucfirst(self::camel($segments));
    }

    /**
     * A Kotlin or Swift identifier cannot start with a digit, and `space.4` is a legitimate token path.
     * The group's initial letter is used as the prefix, which is what the hand-written themes already did
     * (`BdSpace.s4`), so the rename does not churn call sites that already exist.
     */
    public static function identifier(string $camel, string $group): string
    {
        if ($camel === '') {
            throw new Failure('A token path produced an empty identifier.');
        }

        return ctype_digit($camel[0]) ? strtolower($group[0]) . $camel : $camel;
    }

    /**
     * Format a number the same way on every machine.
     *
     * `%F` is deliberate: `%f` follows the C locale's decimal separator, which would make the generated
     * files differ between a contributor in Brussels and the pipeline.
     */
    public static function number(float $value, int $decimals = 6): string
    {
        $formatted = sprintf('%.' . $decimals . 'F', $value);

        // Only the fractional part is trimmed. Trimming the whole string turns 20 into 2 and 100 into 1 the
        // moment `$decimals` is zero and there is no point to stop at, which is a wrong number printed
        // confidently in the middle of a message about a wrong number.
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
