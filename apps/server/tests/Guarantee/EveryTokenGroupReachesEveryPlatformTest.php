<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use BothDecks\Tests\Guarantee\Support\DesignTokens;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: every group of design tokens reaches every platform that can express it, under the name the
 * platform is meant to read it by.
 *
 * A group that reaches one target and not the others does not announce itself: the artefact that received
 * nothing still compiles, and the platform falls back to its own default. The accounting is measured from the
 * artefacts on disk rather than from the generator, which would only prove the generator agrees with itself,
 * and it works by name in both directions, so a rename fails from each side and cannot be traded for an
 * addition. Names are compared with case and separators removed, so `--icon-hit-target` matches `hitTarget`
 * while `hitZone` does not. A target a group cannot reach is a declared exclusion carrying its reason.
 */
#[Group('guarantee')]
final class EveryTokenGroupReachesEveryPlatformTest extends TestCase
{
    private const string ARTEFACT_DIRECTORY = 'design/tokens/generated';

    /**
     * The three targets, each with the artefacts it is written to and how a declaration is spelled in it.
     *
     * @var array<string, array{files: list<string>, member: string}>
     */
    private const array TARGETS = [
        'web' => [
            'files' => ['colors.css', 'foundations.css'],
            'member' => '/^\s*(--[a-z0-9-]+)\s*:/m',
        ],
        'android' => [
            'files' => ['BothDecksTheme.kt'],
            'member' => '/^\s+(?:const\s+)?val\s+([A-Za-z0-9_]+)/m',
        ],
        'ios' => [
            'files' => ['BothDecksTheme.swift'],
            'member' => '/^\s+(?:static\s+)?let\s+([A-Za-z0-9_]+)/m',
        ],
    ];

    /**
     * Every group of tokens, where the source holds it, and the name each target publishes it under.
     *
     * A prefix on the web, a named container on the native targets. The prefix is part of the contract:
     * `borderWidth` publishes as `--border-width-`, because one prefix for two kinds of value is how a width
     * overwrites a focus ring. `depth` is how many leading path segments form one declaration, `properties`
     * the suffixes a target appends when a token becomes several, and an empty prefix means the group reaches
     * that target under no name, which only an exclusion may allow.
     *
     * @var array<string, array{
     *     paths: list<string>,
     *     excluded: list<string>,
     *     names: list<string>,
     *     web: array{prefix: string, depth: int, properties: list<string>},
     *     android: array{container: string, prefix: string, suffix: string, depth: int},
     *     ios: array{container: string, prefix: string, suffix: string, depth: int},
     * }>
     */
    private const array GROUPS = [
        'colour ramps' => [
            'paths' => ['color'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--color-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdColors', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdColors', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'theme roles' => [
            'paths' => ['theme.light', 'theme.dark'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'data class BdTheme', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'struct BdTheme', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'font families' => [
            'paths' => ['font.family'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--font-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdFontFamily', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdFontFamily', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'type styles' => [
            'paths' => ['type'],
            'excluded' => ['type.floor'],
            'names' => [],
            'web' => [
                'prefix' => '--type-',
                'depth' => 0,
                'properties' => ['family', 'size', 'line-height', 'weight', 'tracking', 'font', 'features'],
            ],
            'android' => ['container' => 'object BdType', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdType', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'type floors' => [
            'paths' => ['type.floor'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--type-floor-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdTypeFloor', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdTypeFloor', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'spacing' => [
            'paths' => ['space'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--space-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdSpace', 'prefix' => 's', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdSpace', 'prefix' => 's', 'suffix' => '', 'depth' => 0],
        ],
        'corner radii' => [
            'paths' => ['radius'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--radius-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdRadius', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdRadius', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'elevation' => [
            'paths' => ['elevation'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--elevation-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdElevation', 'prefix' => 'e', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdElevation', 'prefix' => 'e', 'suffix' => '', 'depth' => 0],
        ],
        'border widths' => [
            'paths' => ['borderWidth'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--border-width-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdBorderWidth', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdBorderWidth', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'motion durations' => [
            'paths' => ['motion.duration'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--duration-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdMotion', 'prefix' => '', 'suffix' => 'Ms', 'depth' => 0],
            'ios' => ['container' => 'enum BdMotion', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'easing curves' => [
            'paths' => ['motion.easing'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--easing-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdEasing', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdEasing', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'springs' => [
            'paths' => ['motion.spring'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--spring-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdSprings', 'prefix' => '', 'suffix' => '', 'depth' => 1],
            'ios' => ['container' => 'enum BdSprings', 'prefix' => '', 'suffix' => '', 'depth' => 1],
        ],
        'gesture constants' => [
            'paths' => ['motion.gesture'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--gesture-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdGesture', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdGesture', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'icon metrics' => [
            'paths' => ['icon'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--icon-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdIcon', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdIcon', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'dash pattern' => [
            'paths' => ['dash'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--dash-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdDash', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdDash', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'control geometry' => [
            'paths' => ['control'],
            'excluded' => [],
            'names' => [],
            'web' => ['prefix' => '--control-', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdControl', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdControl', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
        'haptics' => [
            'paths' => [],
            'excluded' => [],
            'names' => ['bothdecks.haptic', 'events'],
            'web' => ['prefix' => '', 'depth' => 0, 'properties' => []],
            'android' => ['container' => 'object BdHaptics', 'prefix' => '', 'suffix' => '', 'depth' => 0],
            'ios' => ['container' => 'enum BdHaptics', 'prefix' => '', 'suffix' => '', 'depth' => 0],
        ],
    ];

    /**
     * The only place a group is allowed to reach no target, with the reason it cannot.
     *
     * @var array<string, string>
     */
    private const array EXCLUSIONS = [
        'haptics/web' => 'the values are iOS and Android platform constants, and no web surface plays them',
    ];

    /**
     * Members a target declares inside a group's container that answer to no single token, with the reason.
     *
     * A container may hold one derived member alongside the tokens, and it is still worth checking the rest
     * of the container by name. Each one is named so that it stays a decision: nothing else may appear.
     *
     * @var array<string, string>
     */
    private const array DERIVED_MEMBERS = [
        'springs/android/reducedduration' => 'Compose reads the fade that replaces a spring from its own object',
        'springs/ios/reducedduration' => 'SwiftUI reads the fade that replaces a spring from its own object',
    ];

    /**
     * Tokens a target is not given because it can work them out from what it is given, with the arithmetic.
     *
     * This is a narrow thing and it is kept narrow: not "the target does not need it", which would excuse
     * anything, but "the target already holds every number this one is made of". The claim is proved rather
     * than asserted, by {@see self::testTheSpringCoefficientTheWebIsNotGivenIsTheOneItCanCompute()}, so an
     * entry here is a statement that fails when it stops being true.
     *
     * @var array<string, string>
     */
    private const array TOKENS_A_TARGET_COMPUTES = [
        'springs/web/dampingcoefficient' => 'the web receives the mass, the stiffness and the damping ratio '
            . 'of every spring, and the coefficient is exactly those three: c = ratio x 2 x root(stiffness x mass)',
    ];

    public function testEveryTokenReachesEveryTargetUnderItsOwnName(): void
    {
        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $shortfalls = [];

        // Act
        foreach (self::GROUPS as $group => $specification) {
            foreach (self::TARGETS as $target => $reader) {
                if (self::isExcluded($group, $target)) {
                    continue;
                }

                $declared = self::declaredNamesOf($specification, $target, $reader);

                foreach (self::sourceNamesOf($tokens, $specification, $target) as $name => $origin) {
                    if (self::isComputedByTheTarget($group, $target, self::normalise(self::leafOf($origin)))) {
                        continue;
                    }

                    if (!isset($declared[$name])) {
                        $shortfalls[] = \sprintf(
                            '%s: `%s` reaches %s under no name (looked for `%s`)',
                            $group,
                            $origin,
                            $target,
                            $name,
                        );
                    }
                }
            }
        }

        // Assert
        self::assertSame(
            [],
            $shortfalls,
            "These tokens do not reach a target that can express them, so the design is different there and\n"
            . "nothing says so. Emit them, or declare the target unreachable with the reason it is:\n  - "
            . implode("\n  - ", $shortfalls),
        );
    }

    public function testEveryDeclarationATargetMakesAnswersToATokenInTheSource(): void
    {
        // The other direction, and the one a count can never take. A declaration renamed on its way out
        // leaves the count where it was: the source still has its token, the artefact still has a line, and
        // every screen that reads the old name now reads nothing. Here the new name is a name no token in the
        // source accounts for, so it is reported as what it is.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $unaccounted = [];

        // Act
        foreach (self::unaccountedWebProperties($tokens) as $property) {
            $unaccounted[] = \sprintf('web declares `%s`, which answers to no token in the source', $property);
        }

        foreach (self::GROUPS as $group => $specification) {
            foreach (['android', 'ios'] as $target) {
                if (self::isExcluded($group, $target)) {
                    continue;
                }

                $expected = self::sourceNamesOf($tokens, $specification, $target);
                $native = self::nativeOf($specification, $target);

                foreach (self::containerMembers(self::TARGETS[$target], $native['container']) as $member) {
                    $name = self::normalise(self::stripAffixes($member, $native));

                    if (isset($expected[$name]) || self::isDerivedMember($group, $target, $name)) {
                        continue;
                    }

                    $unaccounted[] = \sprintf(
                        '%s declares `%s` in %s, which answers to no token of `%s`',
                        $target,
                        $member,
                        $native['container'],
                        $group,
                    );
                }
            }
        }

        sort($unaccounted);

        // Assert
        self::assertSame(
            [],
            $unaccounted,
            "These declarations exist in an artefact and answer to nothing in the source, so a screen that\n"
            . "reads them reads a value nobody can change from the source, and whatever they were renamed\n"
            . "from now resolves to nothing:\n  - " . implode("\n  - ", $unaccounted),
        );
    }

    public function testEveryTokenInTheSourceBelongsToAGroupThisGuaranteeKnows(): void
    {
        // Coverage is worth nothing if a group can be added to the source without being covered. A token
        // whose path matches no group is refused by name here, so a new group cannot quietly reach nothing.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $unclaimed = [];

        // Act
        foreach ($tokens->paths() as $path) {
            if (self::groupOf($path) === null) {
                $unclaimed[] = $path;
            }
        }

        sort($unclaimed);

        // Assert
        self::assertSame(
            [],
            $unclaimed,
            "These tokens belong to no group, so no target is checked for them. Add the group to this\n"
            . "guarantee together with the name each target publishes it under:\n  - "
            . implode("\n  - ", $unclaimed),
        );
    }

    public function testEveryGroupThisGuaranteeKnowsExistsInTheSource(): void
    {
        // The complement. A group that lost its tokens would otherwise be satisfied by zero declarations
        // against zero expectations, which is the shape a guarantee takes just before it stops guaranteeing.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $empty = [];

        // Act
        foreach (self::GROUPS as $group => $specification) {
            if (self::sourceNamesOf($tokens, $specification, 'web') === []) {
                $where = $specification['paths'] === []
                    ? implode('.', $specification['names'])
                    : implode(', ', $specification['paths']);
                $empty[] = $group . ' (' . $where . ')';
            }
        }

        // Assert
        self::assertSame([], $empty, 'These groups define nothing in the source: ' . implode('; ', $empty));
    }

    public function testEveryDeclaredExclusionNamesAGroupATargetAndAReason(): void
    {
        // An exclusion is the one way a group may reach nothing, so it has to stay a decision rather than
        // become a habit: it names a real group, a real target, and says why.

        // Act
        $invalid = [];

        foreach (self::EXCLUSIONS as $key => $reason) {
            $parts = explode('/', (string) $key, 2);

            if (!isset(self::GROUPS[$parts[0]], self::TARGETS[$parts[1] ?? '']) || trim($reason) === '') {
                $invalid[] = $key;
            }
        }

        foreach ([...self::DERIVED_MEMBERS, ...self::TOKENS_A_TARGET_COMPUTES] as $key => $reason) {
            $parts = explode('/', (string) $key, 3);

            if (!isset(self::GROUPS[$parts[0]], self::TARGETS[$parts[1] ?? ''])
                || ($parts[2] ?? '') === ''
                || trim($reason) === '') {
                $invalid[] = $key;
            }
        }

        // Assert
        self::assertSame([], $invalid, 'These exclusions name no real group, target or reason: ' . implode(', ', $invalid));
    }

    public function testTheSpringCoefficientTheWebIsNotGivenIsTheOneItCanCompute(): void
    {
        // The web is the one target that receives a spring as separate numbers, and it receives four of the
        // five the source states. This is the proof that the fifth is not missing: the damping coefficient is
        // the damping ratio expressed in the units of the mass and the stiffness, both of which the web has,
        // so the stylesheet can arrive at it and a script that animates a card is not short of anything.
        //
        // Written as a test rather than as a sentence in the list above, because a sentence stays true after
        // the source stops making it true.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $declared = self::declaredNamesOf(self::GROUPS['springs'], 'web', self::TARGETS['web']);
        $springs = [];

        foreach ($tokens->pathsUnder('motion.spring') as $path) {
            $springs[substr($path, \strlen('motion.spring.'), (int) strpos($path, '.', \strlen('motion.spring.') + 1) - \strlen('motion.spring.'))] = true;
        }

        // Act & Assert
        self::assertNotEmpty($springs, 'The source states no spring, so this proves nothing.');

        foreach (array_keys($springs) as $spring) {
            $given = [];

            foreach (['mass', 'stiffness', 'dampingRatio'] as $part) {
                $path = 'motion.spring.' . $spring . '.' . $part;

                self::assertTrue($tokens->has($path), \sprintf('The source states no `%s`.', $path));
                self::assertArrayHasKey(
                    self::normalise($spring . $part),
                    $declared,
                    \sprintf(
                        'The web is not given `%s`, so it cannot compute the damping coefficient it is also '
                        . 'not given, and that token is a loss rather than a derivation.',
                        $path,
                    ),
                );

                $value = $tokens->resolve($path);
                $given[$part] = \is_int($value) || \is_float($value) ? (float) $value : null;
            }

            $stated = $tokens->resolve('motion.spring.' . $spring . '.dampingCoefficient');

            self::assertIsNumeric($stated, \sprintf('The damping coefficient of `%s` is not a number.', $spring));
            self::assertNotNull($given['mass']);
            self::assertNotNull($given['stiffness']);
            self::assertNotNull($given['dampingRatio']);

            $computed = $given['dampingRatio'] * 2.0 * sqrt($given['stiffness'] * $given['mass']);

            self::assertEqualsWithDelta(
                (float) $stated,
                $computed,
                0.5,
                \sprintf(
                    'The damping coefficient of `%s` is not what its mass, stiffness and damping ratio come '
                    . 'to, so a target given only those three would animate a different spring from the one '
                    . 'the source describes.',
                    $spring,
                ),
            );
        }
    }

    public function testEveryGroupNamesAContainerThatExistsInEachNativeArtefact(): void
    {
        // Every name-by-name check above reads one container per target. A container header that no longer
        // matches the artefact reads as a group that emitted nothing, which is indistinguishable from a group
        // that was never emitted, so the header is asserted to be found rather than assumed.

        // Act
        $missing = [];

        foreach (self::GROUPS as $group => $specification) {
            foreach (['android', 'ios'] as $target) {
                if (self::isExcluded($group, $target)) {
                    continue;
                }

                $native = self::nativeOf($specification, $target);

                if (self::containerMembers(self::TARGETS[$target], $native['container']) === []) {
                    $missing[] = \sprintf('%s declares nothing in `%s` on %s', $group, $native['container'], $target);
                }
            }
        }

        // Assert
        self::assertSame([], $missing, "These containers were not found, or were found empty, so the group\n"
            . "they hold is checked against nothing:\n  - " . implode("\n  - ", $missing));
    }

    public function testTheNamesAreReadFromArtefactsThatWereActuallyRead(): void
    {
        // Every name above is a name read from disk. If an artefact were missing or empty, every group would
        // report a shortfall for a reason that had nothing to do with coverage, so the artefacts are asserted
        // to exist and to carry declarations first.

        // Act
        foreach (self::TARGETS as $target => $reader) {
            $names = self::declaredNames($reader);

            // Assert
            self::assertNotEmpty($names, \sprintf('The %s artefacts declare nothing at all.', $target));
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function renameProvider(): array
    {
        // The comparison is what decides whether a rename is visible, so it is exercised on the two spellings
        // the artefacts actually use and on the change the guarantee exists to catch.
        return [
            'the web and native spellings of one token agree' => ['--icon-hit-target', 'hitTarget'],
            'a path and its native member agree' => ['size.sm', 'sizeSm'],
            'a path and its web property agree' => ['hitTarget.ios', 'hit-target-ios'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('renameProvider')]
    public function testTwoSpellingsOfOneNameCompareEqualAndTwoNamesDoNot(string $first, string $second): void
    {
        // Arrange
        $stripped = str_starts_with($first, '--icon-') ? substr($first, 7) : $first;

        // Act & Assert
        self::assertSame(self::normalise($stripped), self::normalise($second));
        self::assertNotSame(self::normalise($stripped), self::normalise(str_replace(['target', 'Target', 'sm', 'Sm'], ['zone', 'Zone', 'xs', 'Xs'], $second)));
    }

    /**
     * The names one group expects to find in one target, mapped to the source path each comes from.
     *
     * @param array{
     *     paths: list<string>,
     *     excluded: list<string>,
     *     names: list<string>,
     *     web: array{prefix: string, depth: int, properties: list<string>},
     *     android: array{container: string, prefix: string, suffix: string, depth: int},
     *     ios: array{container: string, prefix: string, suffix: string, depth: int},
     * } $specification
     *
     * @return array<string, string>
     */
    private static function sourceNamesOf(DesignTokens $tokens, array $specification, string $target): array
    {
        $depth = $target === 'web' ? $specification['web']['depth'] : self::nativeOf($specification, $target)['depth'];
        $names = [];

        foreach (self::relativePaths($tokens, $specification) as $relative) {
            $segments = explode('.', $relative);
            $kept = $depth > 0 ? \array_slice($segments, 0, $depth) : $segments;
            $names[self::normalise(implode('.', $kept))] = $relative;
        }

        return $names;
    }

    /**
     * The paths a group is made of, relative to the group, or the names it takes from the document.
     *
     * Haptics are the one group the format cannot hold as tokens: the value of each is a platform constant
     * with no type the format defines, so the source carries them in its own extensions. They still reach two
     * targets and are still part of the design, so they are read from where they live rather than left out.
     *
     * @param array{paths: list<string>, excluded: list<string>, names: list<string>, ...} $specification
     *
     * @return list<string>
     */
    private static function relativePaths(DesignTokens $tokens, array $specification): array
    {
        if ($specification['paths'] === []) {
            return self::namesFromDocument($tokens, $specification['names']);
        }

        $relative = [];

        foreach ($specification['paths'] as $prefix) {
            foreach ($tokens->pathsUnder($prefix) as $path) {
                if (self::isExcludedPath($path, $specification['excluded'])) {
                    continue;
                }

                $relative[substr($path, \strlen($prefix) + 1)] = true;
            }
        }

        // A spacing step and an elevation level are named with digits, and a PHP array key that reads as a
        // number arrives back from array_keys() as an integer. Every name is a string before anything splits
        // it on a dot.
        $paths = array_map(strval(...), array_keys($relative));
        sort($paths);

        return $paths;
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private static function namesFromDocument(DesignTokens $tokens, array $names): array
    {
        $node = $names === [] ? null : $tokens->documentExtension($names[0]);

        foreach (\array_slice($names, 1) as $segment) {
            $node = \is_array($node) ? ($node[$segment] ?? null) : null;
        }

        if (!\is_array($node)) {
            return [];
        }

        $found = [];

        foreach (array_keys($node) as $key) {
            $name = (string) $key;

            if (!str_starts_with($name, '$')) {
                $found[] = $name;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * @param list<string> $excluded
     */
    private static function isExcludedPath(string $path, array $excluded): bool
    {
        foreach ($excluded as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The names one group declares in one target, as a set.
     *
     * @param array{
     *     paths: list<string>,
     *     excluded: list<string>,
     *     names: list<string>,
     *     web: array{prefix: string, depth: int, properties: list<string>},
     *     android: array{container: string, prefix: string, suffix: string, depth: int},
     *     ios: array{container: string, prefix: string, suffix: string, depth: int},
     * } $specification
     * @param array{files: list<string>, member: string} $reader
     *
     * @return array<string, true>
     */
    private static function declaredNamesOf(array $specification, string $target, array $reader): array
    {
        $names = [];

        if ($target === 'web') {
            foreach (self::declaredNames($reader) as $property) {
                $name = self::webNameOf($property, $specification['web']);

                if ($name !== null) {
                    $names[$name] = true;
                }
            }

            return $names;
        }

        $native = self::nativeOf($specification, $target);

        foreach (self::containerMembers($reader, $native['container']) as $member) {
            $names[self::normalise(self::stripAffixes($member, $native))] = true;
        }

        return $names;
    }

    /**
     * The half of a group's specification that describes one native target.
     *
     * @param array{
     *     paths: list<string>,
     *     excluded: list<string>,
     *     names: list<string>,
     *     web: array{prefix: string, depth: int, properties: list<string>},
     *     android: array{container: string, prefix: string, suffix: string, depth: int},
     *     ios: array{container: string, prefix: string, suffix: string, depth: int},
     * } $specification
     *
     * @return array{container: string, prefix: string, suffix: string, depth: int}
     */
    private static function nativeOf(array $specification, string $target): array
    {
        return $target === 'android' ? $specification['android'] : $specification['ios'];
    }

    /**
     * Whether a group is declared unable to reach a target at all.
     *
     * The three lists are read through methods rather than indexed where they are written, because a
     * constant holding one entry has one key as far as static analysis is concerned, and a lookup by a group
     * and a target worked out at run time is a lookup rather than a claim about which entries exist.
     */
    private static function isExcluded(string $group, string $target): bool
    {
        return isset(self::reasons(self::EXCLUSIONS)[$group . '/' . $target]);
    }

    private static function isDerivedMember(string $group, string $target, string $name): bool
    {
        return isset(self::reasons(self::DERIVED_MEMBERS)[$group . '/' . $target . '/' . $name]);
    }

    private static function isComputedByTheTarget(string $group, string $target, string $leaf): bool
    {
        return isset(self::reasons(self::TOKENS_A_TARGET_COMPUTES)[$group . '/' . $target . '/' . $leaf]);
    }

    /**
     * One of the three lists above, read as what it is: a map from a key nobody knows in advance to the
     * reason for it.
     *
     * @param array<string, string> $list
     *
     * @return array<string, string>
     */
    private static function reasons(array $list): array
    {
        return $list;
    }

    /**
     * The token name a web custom property carries, or null when the property belongs to another group.
     *
     * @param array{prefix: string, depth: int, properties: list<string>} $web
     */
    private static function webNameOf(string $property, array $web): ?string
    {
        if ($web['prefix'] === '' || !str_starts_with($property, $web['prefix'])) {
            return null;
        }

        $name = substr($property, \strlen($web['prefix']));

        foreach ($web['properties'] as $suffix) {
            if (str_ends_with($name, '-' . $suffix)) {
                $name = substr($name, 0, -\strlen($suffix) - 1);

                break;
            }
        }

        if ($web['depth'] > 0) {
            $name = implode('-', \array_slice(explode('-', $name), 0, $web['depth']));
        }

        return self::normalise($name);
    }

    /**
     * The web custom properties no group can account for.
     *
     * @return list<string>
     */
    private static function unaccountedWebProperties(DesignTokens $tokens): array
    {
        $expected = [];

        foreach (self::GROUPS as $group => $specification) {
            if (self::isExcluded($group, 'web')) {
                continue;
            }

            foreach (array_keys(self::sourceNamesOf($tokens, $specification, 'web')) as $name) {
                $expected[$specification['web']['prefix'] . '|' . $name] = true;
            }
        }

        $unaccounted = [];

        foreach (self::declaredNames(self::TARGETS['web']) as $property) {
            $accounted = false;

            foreach (self::GROUPS as $specification) {
                $name = self::webNameOf($property, $specification['web']);

                if ($name !== null && isset($expected[$specification['web']['prefix'] . '|' . $name])) {
                    $accounted = true;

                    break;
                }
            }

            if (!$accounted) {
                $unaccounted[] = $property;
            }
        }

        sort($unaccounted);

        return $unaccounted;
    }

    /**
     * A native member name with the prefix and suffix its target adds, removed.
     *
     * @param array{container: string, prefix: string, suffix: string, depth: int} $native
     */
    private static function stripAffixes(string $member, array $native): string
    {
        $name = $member;

        if ($native['prefix'] !== '' && str_starts_with($name, $native['prefix'])) {
            $name = substr($name, \strlen($native['prefix']));
        }

        if ($native['suffix'] !== '' && str_ends_with($name, $native['suffix'])) {
            $name = substr($name, 0, -\strlen($native['suffix']));
        }

        return $name;
    }

    /**
     * The last segment of a token path, which is what the token is rather than which one it is.
     */
    private static function leafOf(string $path): string
    {
        $separator = strrpos($path, '.');

        return $separator === false ? $path : substr($path, $separator + 1);
    }

    /**
     * A name with everything but its words removed, so that three languages spelling one token three ways
     * still compare as one token, and two different words never do.
     */
    private static function normalise(string $name): string
    {
        return strtolower(str_replace(['-', '_', '.'], '', $name));
    }

    /**
     * The declarations inside one named container of a native artefact.
     *
     * A container runs from its header line to the first line that closes it at the left margin, which is
     * how both generated files are laid out. A header that is not found returns nothing, which is exactly
     * the shortfall this guarantee is looking for.
     *
     * @param array{files: list<string>, member: string} $reader
     *
     * @return list<string>
     */
    private static function containerMembers(array $reader, string $header): array
    {
        if ($header === '') {
            return [];
        }

        $lines = explode("\n", self::artefactSource($reader));
        $inside = false;
        $members = [];

        foreach ($lines as $line) {
            if (!$inside) {
                $inside = str_starts_with($line, $header . ' ') || str_starts_with($line, $header . '(');

                continue;
            }

            if ($line !== '' && !str_starts_with($line, ' ') && !str_starts_with($line, "\t")) {
                break;
            }

            $matches = [];

            if (preg_match($reader['member'], $line . "\n", $matches) === 1 && isset($matches[1])) {
                $members[] = $matches[1];
            }
        }

        return $members;
    }

    /**
     * Every declaration name in a target's artefacts.
     *
     * @param array{files: list<string>, member: string} $reader
     *
     * @return list<string>
     */
    private static function declaredNames(array $reader): array
    {
        $matches = [];
        preg_match_all($reader['member'], self::artefactSource($reader), $matches);

        $names = [];

        foreach ($matches[1] ?? [] as $name) {
            $names[$name] = true;
        }

        return array_keys($names);
    }

    /**
     * @param array{files: list<string>, member: string} $reader
     */
    private static function artefactSource(array $reader): string
    {
        $source = '';

        foreach ($reader['files'] as $file) {
            $path = self::repositoryRoot() . '/' . self::ARTEFACT_DIRECTORY . '/' . $file;
            $contents = @file_get_contents($path);

            if (!\is_string($contents)) {
                self::fail(\sprintf(
                    'The generated artefact %s/%s is missing. Run `php design/tools/generate-tokens.php`.',
                    self::ARTEFACT_DIRECTORY,
                    $file,
                ));
            }

            $source .= $contents . "\n";
        }

        return $source;
    }

    private static function groupOf(string $path): ?string
    {
        foreach (self::GROUPS as $group => $specification) {
            foreach ($specification['paths'] as $prefix) {
                if (self::isExcludedPath($path, $specification['excluded'])) {
                    continue;
                }

                if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
                    return $group;
                }
            }
        }

        return null;
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
