<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * Which token group must reach which target, declared once and enforced in both directions.
 *
 * Checked from both ends: a token whose path matches no group is refused, so a group added to the source
 * cannot reach nothing; and a group that produces no output in a target it names is refused, so an emitter
 * cannot drop one. A target a group genuinely cannot reach is an exclusion carrying its reason, which the
 * check command prints. That is written down and visible, which a loss is not.
 */
final class Manifest
{
    public const TARGETS = ['css', 'kotlin', 'swift'];

    /**
     * @var array<string, array{
     *     paths: list<string>,
     *     targets: list<string>,
     *     required: bool,
     *     type: ?string,
     *     exclusions: array<string, string>,
     *     note: ?string,
     *     extension: ?array{key: string, entries: string}
     * }>
     */
    private array $groups;

    public function __construct()
    {
        $everywhere = self::TARGETS;

        $this->groups = [
            'color' => self::group(['color'], $everywhere),
            'theme' => self::group(['theme'], $everywhere),
            'font-family' => self::group(['font.family'], $everywhere),
            'type' => self::group(['type'], $everywhere),

            // The smallest size each surface is allowed to set, which is a constraint rather than a style: a
            // dense recruiter console legitimately runs smaller than a consumer application, and a single
            // number for both is a rule that one of the two will quietly break. Matched ahead of `type`
            // because the longest declared prefix wins, and these are dimensions rather than type styles.
            'type-floor' => self::group(['type.floor'], $everywhere),
            'space' => self::group(['space'], $everywhere),
            'radius' => self::group(['radius'], $everywhere),
            'elevation' => self::group(['elevation'], $everywhere),

            // `borderWidth` is the name the audit asks for, because `border.focus` (a width) and
            // `theme.*.border.focus` (a colour) both flatten to `--border-focus` and the width silently wins.
            // Both spellings are accepted so the rename can land in the source without breaking the build,
            // and the generator refuses to emit the colliding CSS name either way.
            'border-width' => self::group(['borderWidth', 'border'], $everywhere),

            // Two lengths rather than a stroke style, because no composite the format defines is read the
            // same way by a browser, by Compose and by SwiftUI, and a dash each of them chooses for itself
            // is a dash that matches by luck.
            'dash' => self::group(['dash'], $everywhere),

            'motion-duration' => self::group(['motion.duration'], $everywhere),
            'motion-easing' => self::group(['motion.easing'], $everywhere),

            // The W3C format has no spring type, so the manifest states it. The value is still typed; the
            // declaration is just here instead of in the file.
            'motion-spring' => self::group(['motion.spring'], $everywhere, type: 'bothdecks.spring'),

            // The transfer function that turns a drag into a decision: where the card commits, where it
            // disarms, how far it rotates, what the card beneath it does. Four implementations draw the same
            // gesture, and a constant that lives in each of them agrees with the others by luck rather than by
            // construction, which is what this group exists to prevent.
            'motion-gesture' => self::group(['motion.gesture'], $everywhere),

            'icon' => self::group(['icon'], $everywhere),

            // The geometry of an interactive control, kept out of `space` because a height is not a gap: a
            // spacing step and a control height that happen to share a number would move together the day
            // one of them is changed, and nothing would report it.
            'control' => self::group(['control'], $everywhere),

            // Haptics are not tokens and the source no longer pretends they are. A haptic has no `$type` the
            // W3C format defines, its value is a pair of platform constants rather than a measurable
            // quantity, and half of it is policy: how often the threshold may fire, what happens when
            // system haptics are off. It is carried in a namespaced root extension, which is where the
            // format puts what it does not itself define, and read from there.
            'haptic' => self::group(
                [],
                ['kotlin', 'swift'],
                exclusions: ['css' => 'The web has no haptic surface: the values are iOS and Android platform constants, and emitting them into CSS would produce names nothing can call.'],
                extension: ['key' => 'bothdecks.haptic', 'entries' => 'events'],
            ),

            // Declared ahead of the decisions the audit leaves open. Absent is reported by `--check` and is
            // not an error; present is generated. What is never allowed is present-and-unmapped.
            // Which duration replaces which under reduced motion is a mapping between tokens rather than a
            // value, so like the haptics it is carried in a root extension. It is not required: a source that
            // declares none gets the collapse-to-`reduced` fallback, and the fallback is reported.
            'motion-reduced' => self::group(
                [],
                $everywhere,
                required: false,
                note: 'The substitution map used when the platform asks for reduced motion. Without it the generator collapses every duration to `motion.duration.reduced`, except where a `motion.duration.reduced<Name>` token exists.',
                extension: ['key' => 'bothdecks.reducedMotion', 'entries' => 'durations'],
            ),
            'safe-area' => self::group(
                ['safeArea', 'safe-area'],
                $everywhere,
                required: false,
                note: 'Safe-area insets, if a source ever declares them. It should not: the inset is a number the device reports at runtime and it changes between models, between orientations and between one navigation mode and the next. What belongs to the design system is the minimum a screen edge takes when the platform reports none, and the rule that the inset is added to it rather than replacing it. The group stays declared so that a `safeArea` token added later is matched and checked rather than silently reaching nothing.',
            ),
            // Neither of these is a token, and the source carries both in `$extensions."bothdecks.product"`
            // instead. The entries stay here so that a `product` or `taxonomy` token added later is refused
            // by name with the reason attached, rather than silently compiled into a theme.
            'product' => self::group(
                ['product'],
                [],
                required: false,
                exclusions: array_fill_keys(
                    $everywhere,
                    'The product name reaches the interface from the server\'s configuration, never from a generated theme file (ADR-0004). Generating it would create the very copies the decision record forbids.',
                ),
                note: 'Not a token. The name is carried in `$extensions."bothdecks.product"` for the design file to bind to, and reaches the interface from the server\'s configuration.',
            ),
            'taxonomy' => self::group(
                ['taxonomy'],
                [],
                required: false,
                exclusions: array_fill_keys(
                    $everywhere,
                    'The taxonomy version is data the server renders next to a code, not a style. It is deliberately not compiled into a theme.',
                ),
                note: 'Not a token. The version is carried in `$extensions."bothdecks.product"`, and is data the server renders beside a code rather than a style.',
            ),
        ];
    }

    /**
     * @param list<string>                    $paths
     * @param list<string>                    $targets
     * @param array<string, string>           $exclusions
     * @param ?array{key: string, entries: string} $extension the root `$extensions` key a group is carried in
     *                                                        when it is not expressible as tokens, and the
     *                                                        member of that block holding the entries
     */
    private static function group(
        array $paths,
        array $targets,
        bool $required = true,
        ?string $type = null,
        array $exclusions = [],
        ?string $note = null,
        ?array $extension = null,
    ): array {
        return compact('paths', 'targets', 'required', 'type', 'exclusions', 'note', 'extension');
    }

    /** @return array<string, array<string, mixed>> */
    public function groups(): array
    {
        return $this->groups;
    }

    public function spec(string $group): array
    {
        return $this->groups[$group] ?? throw new Failure(sprintf('No manifest group named `%s`.', $group));
    }

    /**
     * The group a token path belongs to, matching the longest declared prefix.
     *
     * @return array{0: string, 1: string}|null the logical group name and the source prefix it matched
     */
    public function groupFor(string $path): ?array
    {
        $best = null;
        $bestLength = -1;

        foreach ($this->groups as $name => $spec) {
            foreach ($spec['paths'] as $prefix) {
                if ($path !== $prefix && !str_starts_with($path, $prefix . '.')) {
                    continue;
                }

                if (strlen($prefix) > $bestLength) {
                    $best = [$name, $prefix];
                    $bestLength = strlen($prefix);
                }
            }
        }

        return $best;
    }

    public function assumedTypeFor(string $group): ?string
    {
        return $this->groups[$group]['type'] ?? null;
    }

    /** @return list<string> */
    public function targetsFor(string $group): array
    {
        return $this->groups[$group]['targets'];
    }
}
