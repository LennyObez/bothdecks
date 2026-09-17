<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * Which duration is substituted for which when the platform asks for reduced motion.
 *
 * The map is stated in the source, at `$extensions."bothdecks.reducedMotion"`, because the answer is not
 * derivable: an `instant` transition is already shorter than the reduced one, and a hold is time given to a
 * reader rather than movement, and no rule about names tells those apart from the durations that collapse.
 * Where no map is stated, the fallback collapses everything to `motion.duration.reduced` unless a
 * `motion.duration.reduced<Name>` token exists. The fallback invents no number, but it guesses at intent,
 * which is why a stated map wins and why the checks below hold one to being complete in both directions.
 */
final class ReducedMotion
{
    /**
     * @return array<string, string> duration name => the duration name substituted for it
     */
    public static function map(TokenSource $source): array
    {
        $names = self::durations($source);

        if ($names === []) {
            return [];
        }

        if (!isset($names['reduced'])) {
            throw new Failure(
                'The source declares durations but no `motion.duration.reduced`.',
                ['Reduced motion has to substitute something, and the generator will not choose a number the source does not contain.'],
            );
        }

        $declared = $source->extensionEntries('motion-reduced');

        return $declared === [] ? self::inferred($names) : self::stated($source, $names, $declared);
    }

    /**
     * @return array<string, Token> local duration name => token, in source order
     */
    private static function durations(TokenSource $source): array
    {
        $names = [];

        foreach ($source->group('motion-duration') as $token) {
            $names[Naming::camel($token->localSegments())] = $token;
        }

        return $names;
    }

    /**
     * The map the source states, held to being complete in both directions and to its own stated rule.
     *
     * Completeness is what stops the map being a place to hide: a duration missing from it would reduce to
     * nothing at all, and deleting one line would be a silent exemption with no marker. So a duration absent
     * from the map is a failure, and so is a map entry naming a duration that does not exist.
     *
     * @param array<string, Token> $names
     * @param array<string, mixed> $declared
     *
     * @return array<string, string>
     */
    private static function stated(TokenSource $source, array $names, array $declared): array
    {
        $problems = [];

        foreach (array_keys($names) as $name) {
            if (!isset($declared[$name])) {
                $problems[] = sprintf('`%s` has no entry, so reduced motion does not say what replaces it', $name);
            }
        }

        foreach ($declared as $name => $substitute) {
            if (!isset($names[(string) $name])) {
                $problems[] = sprintf('`%s` is mapped but is not a duration', (string) $name);

                continue;
            }

            if (!is_string($substitute) || !isset($names[$substitute])) {
                $problems[] = sprintf('`%s` is replaced by `%s`, which is not a duration', (string) $name, is_scalar($substitute) ? (string) $substitute : get_debug_type($substitute));

                continue;
            }

            $full = Value::duration($names[(string) $name]->resolved, $names[(string) $name]->path);
            $reduced = Value::duration($names[$substitute]->resolved, $names[$substitute]->path);

            // The source's own rule, enforced rather than quoted: a reduced path that takes longer than the
            // full one is slower motion for a reader who asked for less of it.
            if ($reduced > $full) {
                $problems[] = sprintf(
                    '`%s` is %sms and is replaced by `%s` at %sms, which is longer',
                    (string) $name,
                    Naming::number($full),
                    $substitute,
                    Naming::number($reduced),
                );
            }
        }

        if ($problems !== []) {
            throw new Failure('The reduced-motion map does not agree with the durations.', $problems);
        }

        $map = [];

        // Built in the order the durations are declared, so the emitted block does not depend on the order
        // the map's keys happen to appear in.
        foreach (array_keys($names) as $name) {
            $map[$name] = (string) $declared[$name];
        }

        return $map;
    }

    /**
     * @param array<string, Token> $names
     *
     * @return array<string, string>
     */
    private static function inferred(array $names): array
    {
        $map = [];

        foreach (array_keys($names) as $name) {
            // `reduced` and `reducedCelebrate` are the reduced values themselves; substituting them would
            // overwrite the very numbers the rest of the map points at.
            if ($name === 'reduced' || str_starts_with($name, 'reduced')) {
                $map[$name] = $name;

                continue;
            }

            $own = 'reduced' . ucfirst($name);
            $map[$name] = isset($names[$own]) ? $own : 'reduced';
        }

        return $map;
    }
}
