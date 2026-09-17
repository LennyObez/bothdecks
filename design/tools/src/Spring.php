<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * One spring, folded out of the leaves the source authors it from.
 *
 * The format has no composite type for a spring, so the source writes each of its six numbers as its own
 * typed token and a reader sees the unit of every member. This class is the other half: the emitters ask for
 * springs rather than leaves, so the authoring shape and the emitted shape differ without either guessing.
 * Members are named exhaustively, because a misspelled one would otherwise be dropped in silence and the
 * animation would still look plausible.
 */
final class Spring
{
    /** Every member a spring may carry. Anything else under a spring's name is refused. */
    private const MEMBERS = ['mass', 'stiffness', 'dampingCoefficient', 'dampingRatio', 'response', 'reducedDuration'];

    /**
     * @param list<string> $segments the spring's name within its group, e.g. `['snapBack']`
     */
    private function __construct(
        public readonly string $path,
        public readonly array $segments,
        public readonly float $mass,
        public readonly float $stiffness,
        public readonly float $damping,
        public readonly float $dampingRatio,
        public readonly float $responseSeconds,
        public readonly float $reducedDurationMs,
    ) {
    }

    /**
     * Every spring in the source, in the order the source declares them.
     *
     * @return list<self>
     */
    public static function all(TokenSource $source): array
    {
        $members = [];
        $segments = [];
        $paths = [];

        foreach ($source->group('motion-spring') as $token) {
            $local = $token->localSegments();

            if ($local === []) {
                throw new Failure(sprintf('Token `%s` is the spring group itself and carries a value.', $token->path));
            }

            $name = (string) $local[0];
            $segments[$name] ??= [$name];
            $paths[$name] ??= $token->groupPath . '.' . $name;

            // A spring written as one composite value, which the format permits and the source does not use.
            // Accepted so that a source mid-migration between the two spellings builds either way.
            if (count($local) === 1) {
                if (!is_array($token->resolved)) {
                    throw new Failure(sprintf('Spring `%s` is a single value that is not an object of members.', $token->path));
                }

                foreach ($token->resolved as $member => $value) {
                    self::assign($members, $name, (string) $member, $value, $token->path);
                }

                continue;
            }

            if (count($local) > 2) {
                throw new Failure(
                    sprintf('Token `%s` is nested below a spring member.', $token->path),
                    ['A spring is one level of named members under `motion.spring.<name>`; nothing under this generator reads a deeper one.'],
                );
            }

            self::assign($members, $name, (string) $local[1], $token->resolved, $token->path);
        }

        $springs = [];

        foreach ($members as $name => $raw) {
            $springs[] = self::fold($source, $paths[$name], $segments[$name], $raw);
        }

        return $springs;
    }

    /** The custom-property stem, the Kotlin `val` and the Swift `static let` all start from this. */
    public function kebab(): string
    {
        return Naming::kebab($this->segments);
    }

    public function identifier(): string
    {
        return Naming::identifier(Naming::camel($this->segments), 'motion-spring');
    }

    /**
     * @param array<string, array<string, mixed>> $members
     */
    private static function assign(array &$members, string $name, string $member, mixed $value, string $path): void
    {
        if (!in_array($member, self::MEMBERS, true)) {
            throw new Failure(
                sprintf('Spring `%s` carries a member named `%s`, which is not part of a spring.', $name, $member),
                [
                    'The members are: ' . implode(', ', self::MEMBERS) . '.',
                    sprintf('Declared at `%s`. Correct the spelling, or the member reaches no target and nothing says so.', $path),
                ],
            );
        }

        if (isset($members[$name][$member])) {
            throw new Failure(sprintf('Spring `%s` declares `%s` twice.', $name, $member));
        }

        $members[$name][$member] = $value;
    }

    /**
     * @param list<string>        $segments
     * @param array<string, mixed> $raw
     */
    private static function fold(TokenSource $source, string $path, array $segments, array $raw): self
    {
        // Durations are normalised to milliseconds before the physics is checked, so that a `response` written
        // as `{"value": 0.314, "unit": "s"}` and one written as `314ms` are the same number by the time they
        // are compared against the mass and stiffness they claim to summarise.
        if (isset($raw['response'])) {
            $raw['response'] = Value::duration($raw['response'], $path . '.response');
        }

        $physics = Value::spring($raw, $path);

        return new self(
            $path,
            $segments,
            $physics['mass'],
            $physics['stiffness'],
            $physics['damping'],
            $physics['dampingRatio'],
            $physics['responseSeconds'],
            self::reducedDuration($source, $path, $raw),
        );
    }

    /**
     * How long the fade that replaces this spring lasts when the platform asks for reduced motion.
     *
     * A spring cannot be slowed down into an accessible animation, because it is the overshoot that is the
     * problem rather than the speed, so what reduced motion substitutes is a plain fade whose length has to come from
     * somewhere. The spring's own `reducedDuration` wins; failing that the general
     * `motion.duration.reduced` is used, which is the same fallback the duration substitution map applies.
     * Nothing here invents a number: with neither available the generation is refused.
     *
     * @param array<string, mixed> $raw
     */
    private static function reducedDuration(TokenSource $source, string $path, array $raw): float
    {
        if (isset($raw['reducedDuration'])) {
            return Value::duration($raw['reducedDuration'], $path . '.reducedDuration');
        }

        $fallback = 'motion.duration.reduced';

        if (!$source->has($fallback)) {
            throw new Failure(
                sprintf('Spring `%s` declares no `reducedDuration`, and the source has no `%s` to fall back on.', $path, $fallback),
                ['A spring that reduced motion cannot replace leaves a visitor who asked for less movement watching it anyway.'],
            );
        }

        return Value::duration($source->token($fallback)->resolved, $fallback);
    }
}
