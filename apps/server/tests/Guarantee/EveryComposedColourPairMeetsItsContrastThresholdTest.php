<?php

declare(strict_types=1);

namespace BothDecks\Tests\Guarantee;

use BothDecks\Tests\Guarantee\Support\DesignTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guarantee: every pair of colours the design puts one on top of the other reaches the contrast the success
 * criteria require, in both themes.
 *
 * The pairs are derived from the role names rather than listed, because a hand-written table grows a row for
 * a new role and never for a role composed somewhere new. Thresholds: 4.5:1 for text (SC 1.4.3), 3:1 for a
 * focus ring, a functional boundary and a meaningful fill (SC 1.4.11), and merely distinct for a decorative
 * hairline, which the criterion exempts but which still has to be drawn at all. `text.disabled` is exempt.
 * A translucent or full-bleed role is named and set aside rather than measured as an opaque mark, and the
 * full-bleed list is bounded here by name so a file cannot grant itself the exemption.
 *
 * @see https://www.w3.org/TR/WCAG22/#contrast-minimum
 * @see https://www.w3.org/TR/WCAG22/#non-text-contrast
 */
#[Group('guarantee')]
final class EveryComposedColourPairMeetsItsContrastThresholdTest extends TestCase
{
    private const float TEXT_THRESHOLD = 4.5;

    private const float NON_TEXT_THRESHOLD = 3.0;

    /**
     * The role that marks an inactive control, exempt from the text threshold by the criterion itself.
     */
    private const string EXEMPT_TEXT_ROLE = 'text.disabled';

    /**
     * How the source says that a fill is painted as a ground rather than as a mark.
     *
     * A fill is normally a shape drawn on a page, so it is held to the non-text threshold against the
     * surfaces. A fill painted edge to edge replaces the surface instead, and the pair that matters is then
     * the text on it, which `onFill` covers. Only the design knows which a fill is, so the source says it,
     * either on the token or in the document's list. A fill the source says nothing about is a mark, so an
     * unrecognised spelling costs a failure rather than an exemption.
     */
    private const string DRAWN_AS_EXTENSION = 'bothdecks.drawnAs';

    private const string DRAWN_AS_GROUND = 'surface';

    private const string COMPOSITION_EXTENSION = 'bothdecks.composition';

    private const string FOCUS_RING_EXTENSION = 'bothdecks.focusRing';

    private const string FULL_BLEED_KEY = 'fullBleedSurfaces';

    /**
     * The fills that may be painted as a ground, each with the screen that paints one.
     *
     * The declaration lives in the source, next to the colour, which is where a reviewer of a palette change
     * will see it. The *permission* lives here, because a declaration that grants itself its own exemption
     * is a switch: a fill that fails 3:1 today is one line away from passing, with nothing in any run saying
     * the palette got weaker rather than better. Held against this list, the same line fails instead, and
     * the only way to add a claimant is to change a guarantee on purpose and say which screen justifies it.
     *
     * A name here is a permission, never an assertion that the fill has claimed it: a fill that stops
     * claiming the exemption simply goes back to being measured as a mark, which is the stricter reading.
     *
     * @var array<string, string>
     */
    private const array FILLS_THAT_MAY_BE_PAINTED_AS_A_GROUND = [
        'match.fill' => 'the Match result fills the screen edge to edge, so it is the ground and not a mark on one',
    ];

    /**
     * The surfaces the design stacks on one another.
     *
     * `inverse` is deliberately absent: it is not a step in the stack, it is a reversal, and it is checked
     * against the one text role the design pairs it with instead.
     *
     * @var list<string>
     */
    private const array STACKED_SURFACES = [
        'surface.canvas',
        'surface.card',
        'surface.sunken',
        'surface.raised',
    ];

    /**
     * The surface that has nothing but its colour to say where it is.
     *
     * Two surfaces the design separates with a shadow may share a colour: `card` and `raised` are the same
     * paper lifted to different heights, and the elevation is what says which is which. A recess has no such
     * counterpart. Nothing in the token set casts an inward shadow, so a sunken surface is drawn by its
     * colour or it is not drawn: a loading skeleton the colour of the page it loads into is not subtle, it
     * is absent, and it is absent exactly while the reader is waiting to see something.
     */
    private const string SURFACE_WITHOUT_ELEVATION = 'surface.sunken';

    public function testEveryTextPairReachesTheContrastMinimum(): void
    {
        // Act
        $failures = self::failuresAmong(self::textPairs(), self::TEXT_THRESHOLD);

        // Assert
        self::assertSame([], $failures, self::report(
            'Text below 4.5:1 on the ground the design draws it on (WCAG 2.2 SC 1.4.3)',
            $failures,
        ));
    }

    public function testEveryFocusRingReachesTheNonTextMinimumOnEverySurfaceItCanBeDrawnOn(): void
    {
        // The focus ring is specified once and drawn everywhere, so it is the one role whose contrast has to
        // hold against every ground the theme can paint, not merely against the page. A ring measured only
        // on the canvas is a ring that has been measured on the surface it is least likely to fail.

        // Act
        $failures = self::failuresAmong(self::focusRingPairs(), self::NON_TEXT_THRESHOLD);

        // Assert
        self::assertSame([], $failures, self::report(
            'The focus indicator is below 3:1 against a surface the design paints (WCAG 2.2 SC 1.4.11). '
            . 'Give the ring a counterpart for branded and inverted grounds, the way onFill already works, '
            . 'and specify which one a control uses from the surface behind it',
            $failures,
        ));
    }

    public function testEveryFunctionalBoundaryAndMeaningfulFillReachesTheNonTextMinimum(): void
    {
        // Act
        $failures = self::failuresAmong(self::nonTextPairs(), self::NON_TEXT_THRESHOLD);

        // Assert
        self::assertSame([], $failures, self::report(
            'A functional boundary or a fill that carries meaning is below 3:1 (WCAG 2.2 SC 1.4.11)',
            $failures,
        ));
    }

    public function testNoDecorativeHairlineIsTheColourOfWhatItDivides(): void
    {
        // The decorative exemption covers a hairline that is faint. It does not cover one that is absent.

        // Act
        $identical = self::identicalPairs(self::hairlinePairs());

        // Assert
        self::assertSame([], $identical, self::report(
            'A divider is the same colour as the surface it divides, so it draws nothing. Move it one step '
            . 'along its ramp',
            $identical,
        ));
    }

    public function testTheSurfaceWithNoElevationIsTheColourOfNoOtherSurface(): void
    {
        // Act
        $identical = self::identicalPairs(self::recessPairs());

        // Assert
        self::assertSame([], $identical, self::report(
            \sprintf(
                'The design draws `%s` with its colour alone, and it resolves to the colour of another '
                . 'surface, so anything drawn in it is invisible. Move it one step along its ramp',
                self::SURFACE_WITHOUT_ELEVATION,
            ),
            $identical,
        ));
    }

    public function testTheExemptRoleIsMeasuredAndReportedRatherThanIgnored(): void
    {
        // An exemption that is never measured becomes a place to hide. The disabled role is not held to the
        // text threshold, but it is resolved and measured on every ground, so a change that made it vanish
        // altogether against a surface would still show up here.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $grounds = 0;
        $faintest = null;

        // Act
        foreach (self::themes() as $theme) {
            foreach (self::groundsIn($tokens, $theme, includeInverse: false, includeFills: false) as $ground) {
                $ratio = DesignTokens::contrastRatio(
                    $tokens->colour($theme . '.' . self::EXEMPT_TEXT_ROLE),
                    $tokens->colour($theme . '.' . $ground),
                );

                ++$grounds;
                $faintest = $faintest === null ? $ratio : min($faintest, $ratio);
            }
        }

        // Assert
        self::assertGreaterThan(0, $grounds, 'The exempt role was measured on no ground at all.');
        self::assertNotNull($faintest);
        self::assertGreaterThan(
            1.0,
            $faintest,
            'The disabled text role is exempt from 4.5:1, but it still has to be visible: it resolves to the '
            . 'colour of a surface it is drawn on.',
        );
    }

    public function testAFillDeclaredAsAGroundStillHasItsTextMeasured(): void
    {
        // The one exemption a fill may claim moves it out of the non-text check. It must not move it out of
        // every check: a fill painted as a ground is a fill words are written on, so the pair that matters is
        // the text on it, and a role claiming the exemption without naming that text would be exempt from
        // both.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $unmeasured = [];

        // Act
        foreach (self::themes() as $theme) {
            $roles = self::rolesIn($tokens, $theme);

            foreach ($roles as $role) {
                if (!str_ends_with($role, '.fill') || !self::isDrawnAsGround($tokens, $theme, $role)) {
                    continue;
                }

                $family = substr($role, 0, (int) strrpos($role, '.'));
                $carriesText = \in_array($family . '.onFill', $roles, true)
                    || ($family === 'brand' && \in_array('text.onBrand', $roles, true));

                if (!$carriesText) {
                    $unmeasured[] = $theme . '.' . $role;
                }
            }
        }

        // Assert
        self::assertSame(
            [],
            $unmeasured,
            'These fills are declared to be painted as a ground and name no text drawn on them, so nothing '
            . 'about them is measured: ' . implode(', ', $unmeasured),
        );
    }

    public function testNoFillClaimsToBePaintedAsAGroundWithoutPermission(): void
    {
        // The one exemption from SC 1.4.11 this system grants is bounded here rather than in the file it
        // exempts. A fill that starts claiming it without appearing above is reported by name and by theme,
        // so the palette cannot be made to pass by describing it differently.
        //
        // Only fills are asked, because a fill is the only thing the declaration moves out of a check: a
        // surface is already a ground, and saying so about one changes nothing that is measured here.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $unpermitted = [];

        // Act
        foreach (self::themes() as $theme) {
            foreach (self::rolesIn($tokens, $theme) as $role) {
                if (!str_starts_with(self::leafOf($role), 'fill')) {
                    continue;
                }

                if (!self::claimsToBeAGround($tokens, $theme, $role)) {
                    continue;
                }

                if (!isset(self::FILLS_THAT_MAY_BE_PAINTED_AS_A_GROUND[$role])) {
                    $unpermitted[] = $theme . '.' . $role;
                }
            }
        }

        sort($unpermitted);

        // Assert
        self::assertSame(
            [],
            $unpermitted,
            \sprintf(
                "These roles declare themselves painted as a ground, which takes them out of the 3:1\n"
                . "non-text check, and this guarantee permits it for none of them. Either the role is a mark\n"
                . "and the declaration goes, or it is a whole screen and it is named here with the screen\n"
                . "that paints it. Permitted today: %s.\n  - %s",
                implode(', ', array_keys(self::FILLS_THAT_MAY_BE_PAINTED_AS_A_GROUND)),
                implode("\n  - ", $unpermitted),
            ),
        );
    }

    public function testEveryFillPermittedToBeAGroundIsStillAFillInTheSource(): void
    {
        // The complement. A permission naming a role the source no longer has is a permission nobody can
        // read, and it is the state a list drifts into just before somebody widens it to make a run pass.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $stale = [];

        // Act
        foreach (array_keys(self::FILLS_THAT_MAY_BE_PAINTED_AS_A_GROUND) as $role) {
            if (!str_ends_with($role, '.fill')) {
                $stale[] = $role . ' is not a fill';

                continue;
            }

            foreach (self::themes() as $theme) {
                if (!$tokens->has($theme . '.' . $role)) {
                    $stale[] = $theme . '.' . $role . ' is not defined';
                }
            }
        }

        // Assert
        self::assertSame(
            [],
            $stale,
            'These fills are permitted to be painted as a ground and the source does not define them as '
            . 'fills: ' . implode(', ', $stale),
        );
    }

    public function testATranslucentRoleIsNamedAndLeftOutRatherThanMeasuredAsAnOpaqueColour(): void
    {
        // A colour drawn through is not a colour: what a reader sees is the composite of the role and
        // whatever is behind it, which the token does not name. Such a role is therefore not measured, and
        // this states which roles that is, so the set cannot grow unnoticed into a way of not being measured.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $translucent = [];

        // Act
        foreach (self::themes() as $theme) {
            foreach (self::rolesIn($tokens, $theme) as $role) {
                if ($tokens->isTranslucent($theme . '.' . $role)) {
                    $translucent[] = $theme . '.' . $role;
                }
            }
        }

        sort($translucent);

        // Assert
        foreach ($translucent as $path) {
            self::assertStringEndsWith(
                '.scrim',
                $path,
                \sprintf(
                    'The role `%s` carries an alpha channel, so no contrast pair can be measured against it '
                    . 'and it is silently outside every check above. A veil is the one role that legitimately '
                    . 'has one; anything else that needs alpha needs a primitive with the alpha baked in, so '
                    . 'that what is drawn on it is a colour the guarantees can read.',
                    $path,
                ),
            );
        }

        foreach (self::themes() as $theme) {
            $scrim = $theme . '.surface.scrim';

            if ($tokens->has($scrim)) {
                self::assertContains($scrim, $translucent, \sprintf(
                    'The veil `%s` is opaque, so it hides the surface behind it instead of darkening it.',
                    $scrim,
                ));
            }
        }
    }

    public function testTheNamedSurfacesAndRolesThisGuaranteeReliesOnStillExist(): void
    {
        // Three names are written down here rather than derived: the surfaces the design stacks, the recess
        // among them, and the role the criteria exempt. A rename in the source would otherwise turn each of
        // the checks that use them into a check of nothing.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $missing = [];

        // Act
        foreach (self::themes() as $theme) {
            foreach ([...self::STACKED_SURFACES, self::EXEMPT_TEXT_ROLE] as $role) {
                if (!$tokens->has($theme . '.' . $role)) {
                    $missing[] = $theme . '.' . $role;
                }
            }
        }

        // Assert
        self::assertSame([], $missing, 'These roles are named by this guarantee and absent from the source: ' . implode(', ', $missing));
        self::assertContains(self::SURFACE_WITHOUT_ELEVATION, self::STACKED_SURFACES);
    }

    public function testThePairsAreDerivedFromTheRoleNamesAndCoverEveryGround(): void
    {
        // A derived pair set is worth what it derives. This states the size of what was measured, so a change
        // that quietly stopped composing a family or a ground fails here rather than passing everywhere else.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());

        // Act
        $pairs = self::allPairs();
        $grounds = [];

        foreach ($pairs as $pair) {
            $grounds[$pair['theme'] . '.' . $pair['background']] = true;
        }

        // Assert
        self::assertNotEmpty($pairs, 'No pair was derived, so every assertion above is vacuous.');

        foreach (self::themes() as $theme) {
            foreach (self::groundsIn($tokens, $theme, includeInverse: true, includeFills: true) as $ground) {
                self::assertArrayHasKey(
                    $theme . '.' . $ground,
                    $grounds,
                    \sprintf('The theme paints `%s.%s`, and no derived pair is measured against it.', $theme, $ground),
                );
            }
        }
    }

    public function testEveryDeclaredFocusRingNamesAGroundAndARingThatExist(): void
    {
        // The source may say which of the rings a given ground takes, and this follows it. A declaration
        // naming a ground the theme no longer paints, or a ring the theme no longer defines, is then a line
        // that decides nothing while reading as though it did, and the ground it was written for quietly
        // falls back to a ring nobody chose.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $dangling = [];

        // Act
        foreach (self::themes() as $theme) {
            $grounds = self::groundsIn($tokens, $theme, includeInverse: true, includeFills: true);

            foreach (self::declaredFocusRings($tokens, $theme) as $ground => $ring) {
                $where = $theme . ': ' . $ground . ' -> ' . (\is_string($ring) ? $ring : \gettype($ring));

                if (!\is_string($ring) || !$tokens->has($theme . '.' . $ring)) {
                    $dangling[] = $where . ' (no such role)';

                    continue;
                }

                if (!\in_array((string) $ground, $grounds, true)) {
                    $dangling[] = $where . ' (nothing is painted on that ground)';
                }
            }
        }

        sort($dangling);

        // Assert
        self::assertSame(
            [],
            $dangling,
            "These focus-ring declarations name a ground or a role the theme does not have, so they decide\n"
            . "nothing and the ground they were written for takes whatever the derivation picks:\n  - "
            . implode("\n  - ", $dangling),
        );
    }

    public function testEveryRoleTheDesignDrawsOnSomethingIsMeasuredAgainstSomething(): void
    {
        // The claim this guarantee makes is that the pairs grow with the palette because they are derived
        // from the role names. That claim is only true while every name is one the derivation recognises. A
        // role added under a name none of the rules matches reaches no pair at all, and reaches it silently:
        // every assertion above still passes, on a set that quietly stopped containing it.
        //
        // So the complement of the ground coverage is asserted here. A role is either a ground, or it is
        // drawn on one and appears as a foreground somewhere.

        // Arrange
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $measured = [];

        foreach (self::allPairs() as $pair) {
            $measured[$pair['theme'] . '.' . $pair['foreground']] = true;
        }

        // Act
        $unmeasured = [];

        foreach (self::themes() as $theme) {
            $grounds = self::groundsIn($tokens, $theme, includeInverse: true, includeFills: true);

            foreach (self::opaqueRolesIn($tokens, $theme) as $role) {
                if ($role === self::EXEMPT_TEXT_ROLE || \in_array($role, $grounds, true)) {
                    continue;
                }

                if (!isset($measured[$theme . '.' . $role])) {
                    $unmeasured[] = $theme . '.' . $role;
                }
            }
        }

        sort($unmeasured);

        // Assert
        self::assertSame(
            [],
            $unmeasured,
            "These roles are painted on something and no derived pair measures them, so every threshold\n"
            . "above is silent about them. Either the role is a ground, or the rule that reads its name has\n"
            . "to recognise it:\n  - " . implode("\n  - ", $unmeasured),
        );
    }

    public function testTheDerivationReportsTheSizeAndShapeOfWhatItMeasured(): void
    {
        // A count is the one part of a contrast audit nobody can check by reading it, so this test produces
        // the count instead of repeating one. Each family of pairs is named, sized and required to be
        // non-empty, and the families are required to be disjoint, so the total is the sum of the parts and
        // a family that stopped deriving anything is a failure rather than a smaller number nobody notices.

        // Arrange
        $families = self::pairFamilies();
        $census = [];
        $seen = [];
        $total = 0;
        $empty = [];
        $duplicated = [];

        // Act
        foreach ($families as $name => $pairs) {
            $census[] = \sprintf('%s: %d', $name, \count($pairs));
            $total += \count($pairs);

            if ($pairs === []) {
                $empty[] = $name;
            }

            foreach ($pairs as $pair) {
                $key = $pair['theme'] . '|' . $pair['foreground'] . '|' . $pair['background'];

                if (isset($seen[$key])) {
                    $duplicated[] = \sprintf('%s is derived by both %s and %s', $key, $seen[$key], $name);
                }

                $seen[$key] = $name;
            }
        }

        $report = \sprintf('%s; total %d pairs over %d themes', implode(', ', $census), $total, \count(self::themes()));

        // Assert
        self::assertSame([], $empty, 'These families of pairs derive nothing, so what they measure is not '
            . 'measured at all: ' . implode(', ', $empty) . '. Measured: ' . $report);
        self::assertSame([], $duplicated, "These pairs are derived twice, so the census double-counts them:\n  - "
            . implode("\n  - ", $duplicated));
        self::assertCount($total, $seen, 'The families overlap, so the total is not the sum of the parts. ' . $report);
        self::assertGreaterThan(
            \count(self::themes()) * \count(self::STACKED_SURFACES),
            $total,
            'The derivation produced fewer pairs than there are surfaces to draw on, which is not an audit. '
            . $report,
        );
    }

    /**
     * @return array<string, array{string, string, float}>
     */
    public static function publishedRatioProvider(): array
    {
        // Three ratios taken from the audit of the source this system was built from, each recomputed here.
        // They exist so that a defect in this file's arithmetic fails on a number somebody has checked by
        // hand, rather than silently changing every ratio the guarantees above compare.
        return [
            'black on white' => ['#000000', '#ffffff', 21.0],
            'a colour on itself' => ['#883871', '#883871', 1.0],
            'the brand fill on the light canvas' => ['#883871', '#fcf9f7', 7.03],
            'the focus ring on the match dialog' => ['#883871', '#6e2a5b', 1.33],
            'the inverted text on the inverted surface' => ['#fcf9f7', '#100c09', 18.57],
        ];
    }

    #[DataProvider('publishedRatioProvider')]
    public function testTheContrastArithmeticReproducesRatiosCheckedByHand(
        string $foreground,
        string $background,
        float $expected,
    ): void {
        // Act
        $measured = DesignTokens::contrastRatio($foreground, $background);

        // Assert
        self::assertSame($expected, $measured);
    }

    /**
     * Text on the grounds the design draws it on.
     *
     * @return list<array{theme: string, foreground: string, background: string}>
     */
    private static function textPairs(): array
    {
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $pairs = [];

        foreach (self::themes() as $theme) {
            $roles = self::opaqueRolesIn($tokens, $theme);

            foreach ($roles as $role) {
                if ($role === self::EXEMPT_TEXT_ROLE) {
                    continue;
                }

                foreach (self::groundsForText($tokens, $theme, $role, $roles) as $ground) {
                    $pairs[] = ['theme' => $theme, 'foreground' => $role, 'background' => $ground];
                }
            }
        }

        return $pairs;
    }

    /**
     * The focus ring against every ground the theme can paint behind a control.
     *
     * One colour cannot be visible on a pale page and on the brand fill at once, so a palette may name a
     * counterpart for the grounds the ordinary ring cannot survive, exactly as `onFill` already names the
     * text that goes on a fill. Where the theme declares one, it is the role measured on those grounds; where
     * it does not, the ordinary ring is measured everywhere, because that is then what a control will draw.
     *
     * Either way every ground is covered. What the palette may choose is which ring answers for it, not
     * whether one is asked.
     *
     * @return list<array{theme: string, foreground: string, background: string}>
     */
    private static function focusRingPairs(): array
    {
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $pairs = [];

        foreach (self::themes() as $theme) {
            if (!$tokens->has($theme . '.border.focus')) {
                continue;
            }

            foreach (self::groundsIn($tokens, $theme, includeInverse: true, includeFills: true) as $ground) {
                $pairs[] = [
                    'theme' => $theme,
                    'foreground' => self::focusRingFor($tokens, $theme, $ground),
                    'background' => $ground,
                ];
            }
        }

        return $pairs;
    }

    /**
     * Whether a fill is painted as a ground rather than as a mark on one.
     *
     * The source has to say so and this guarantee has to permit it. Either alone is a claim; both together
     * are a decision two people made in two files.
     */
    private static function isDrawnAsGround(DesignTokens $tokens, string $theme, string $role): bool
    {
        return self::claimsToBeAGround($tokens, $theme, $role)
            && isset(self::FILLS_THAT_MAY_BE_PAINTED_AS_A_GROUND[$role]);
    }

    /**
     * Whether the source declares a role to be painted as a ground, permitted or not.
     */
    private static function claimsToBeAGround(DesignTokens $tokens, string $theme, string $role): bool
    {
        if ($tokens->extension($theme . '.' . $role, self::DRAWN_AS_EXTENSION) === self::DRAWN_AS_GROUND) {
            return true;
        }

        return \in_array($role, self::rolesTheSourceCallsFullBleed($tokens), true);
    }

    /**
     * The roles the document lists as painted edge to edge.
     *
     * @return list<string>
     */
    private static function rolesTheSourceCallsFullBleed(DesignTokens $tokens): array
    {
        $composition = $tokens->documentExtension(self::COMPOSITION_EXTENSION);
        $declared = \is_array($composition) ? ($composition[self::FULL_BLEED_KEY] ?? null) : null;

        if (!\is_array($declared)) {
            return [];
        }

        $roles = [];

        foreach ($declared as $role) {
            if (\is_string($role)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * The focus role a control draws when it sits on a given ground.
     *
     * Where the source declares which ring a surface takes, that declaration is followed. It selects between
     * rings, never between measuring and not measuring: whichever ring it names is then held to 3:1 against
     * that same ground, so the map can make the measurement *right* and cannot make it go away. A ground the
     * map is silent about is not skipped either; the ring is derived from the ground's own name instead.
     */
    private static function focusRingFor(DesignTokens $tokens, string $theme, string $ground): string
    {
        $declared = self::declaredFocusRingFor($tokens, $theme, $ground);

        if ($declared !== null && $tokens->has($theme . '.' . $declared)) {
            return $declared;
        }

        // A fill declared to be painted as a ground is a surface in everything but name, and a control on it
        // is a control on a page: it draws the ordinary ring. The counterpart exists for the other case, a
        // control sitting on a saturated mark, and for the inverted surface.
        $isReversedGround = $ground === 'surface.inverse'
            || (str_contains($ground, '.fill') && !self::isDrawnAsGround($tokens, $theme, $ground));

        if (!$isReversedGround) {
            return 'border.focus';
        }

        foreach ([$ground === 'surface.inverse' ? 'border.focusOnInverse' : '', 'border.focusOnFill'] as $counterpart) {
            if ($counterpart !== '' && $tokens->has($theme . '.' . $counterpart)) {
                return $counterpart;
            }
        }

        return 'border.focus';
    }

    /**
     * The ring the source declares for a ground in one theme, or null when it declares none.
     */
    private static function declaredFocusRingFor(DesignTokens $tokens, string $theme, string $ground): ?string
    {
        $map = self::declaredFocusRings($tokens, $theme);
        $declared = $map[$ground] ?? null;

        return \is_string($declared) ? $declared : null;
    }

    /**
     * The whole ring declaration for one theme, as ground to role.
     *
     * The keys come from a JSON object and are read back as whatever PHP made of them, so callers turn each
     * one into a string before comparing it with a role name.
     *
     * @return array<array-key, mixed>
     */
    private static function declaredFocusRings(DesignTokens $tokens, string $theme): array
    {
        $rings = $tokens->documentExtension(self::FOCUS_RING_EXTENSION);
        $mode = substr($theme, (int) strrpos($theme, '.') + 1);
        $forMode = \is_array($rings) ? ($rings[$mode] ?? null) : null;

        return \is_array($forMode) ? $forMode : [];
    }

    /**
     * Functional boundaries, icons and meaningful marks against the grounds they are drawn on.
     *
     * Four kinds of role fall under the non-text criterion, each recognised by its name rather than listed: a
     * meaningful boundary, an icon, a fill that is a mark on a surface rather than the surface itself, and an
     * accent. An accent is measured on its own family's fill only, since holding it to 3:1 on grounds that
     * family never paints would make the table longer rather than stricter.
     *
     * @return list<array{theme: string, foreground: string, background: string}>
     */
    private static function nonTextPairs(): array
    {
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $pairs = [];

        foreach (self::themes() as $theme) {
            $roles = self::opaqueRolesIn($tokens, $theme);

            foreach ($roles as $role) {
                $leaf = self::leafOf($role);

                if ($leaf === 'accent') {
                    foreach (self::fillsOf($roles, self::familyOf($role)) as $ground) {
                        $pairs[] = ['theme' => $theme, 'foreground' => $role, 'background' => $ground];
                    }

                    continue;
                }

                $isFunctionalBoundary = $role === 'border.strong' || ($leaf === 'border' && $role !== 'border');
                $isIcon = str_starts_with($role, 'icon.');
                $isMark = str_starts_with($leaf, 'fill') && !self::isDrawnAsGround($tokens, $theme, $role);

                if (!$isFunctionalBoundary && !$isIcon && !$isMark) {
                    continue;
                }

                foreach (self::groundsIn($tokens, $theme, includeInverse: false, includeFills: false) as $ground) {
                    $pairs[] = ['theme' => $theme, 'foreground' => $role, 'background' => $ground];
                }
            }
        }

        return $pairs;
    }

    /**
     * The last segment of a role name, which is what says what the role is for.
     */
    private static function leafOf(string $role): string
    {
        $separator = strrpos($role, '.');

        return $separator === false ? $role : substr($role, $separator + 1);
    }

    /**
     * The family a role belongs to, which is everything before its last segment.
     */
    private static function familyOf(string $role): string
    {
        $separator = strrpos($role, '.');

        return $separator === false ? '' : substr($role, 0, $separator);
    }

    /**
     * The decorative hairline against everything it divides.
     *
     * @return list<array{theme: string, foreground: string, background: string}>
     */
    private static function hairlinePairs(): array
    {
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $pairs = [];

        foreach (self::themes() as $theme) {
            if (!$tokens->has($theme . '.border.subtle')) {
                continue;
            }

            foreach (self::groundsIn($tokens, $theme, includeInverse: false, includeFills: false) as $ground) {
                $pairs[] = ['theme' => $theme, 'foreground' => 'border.subtle', 'background' => $ground];
            }
        }

        return $pairs;
    }

    /**
     * The recess against every other surface it can appear on.
     *
     * @return list<array{theme: string, foreground: string, background: string}>
     */
    private static function recessPairs(): array
    {
        $pairs = [];

        foreach (self::themes() as $theme) {
            foreach (self::STACKED_SURFACES as $surface) {
                if ($surface === self::SURFACE_WITHOUT_ELEVATION) {
                    continue;
                }

                $pairs[] = [
                    'theme' => $theme,
                    'foreground' => self::SURFACE_WITHOUT_ELEVATION,
                    'background' => $surface,
                ];
            }
        }

        return $pairs;
    }

    /**
     * The grounds a text role is drawn on, decided by what its name says it is for.
     *
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private static function groundsForText(DesignTokens $tokens, string $theme, string $role, array $roles): array
    {
        $leaf = self::leafOf($role);
        $family = self::familyOf($role);

        // `on…` names the ground, and it names it for every role that carries it, not only for the two the
        // palette happened to have when this was written. `text.onInverse` and `brand.onInverseAction` are
        // both words drawn on the inverted surface, and a role that answers to neither name would go
        // unmeasured on the one surface it exists for.
        if (str_starts_with($leaf, 'onInverse')) {
            return ['surface.inverse'];
        }

        if (str_starts_with($leaf, 'onBrand')) {
            return self::fillsOf($roles, 'brand');
        }

        if (str_starts_with($leaf, 'onFill')) {
            return self::fillsOf($roles, $family);
        }

        $isPlainText = str_starts_with($role, 'text.') || str_ends_with($role, '.text');

        if (!$isPlainText) {
            return [];
        }

        return self::groundsIn($tokens, $theme, includeInverse: false, includeFills: false);
    }

    /**
     * The fills of one family, so that an `onFill` role is measured against every fill of its own family and
     * not only against the resting one. A pressed state is a fill a user sees.
     *
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private static function fillsOf(array $roles, string $family): array
    {
        $fills = [];

        foreach ($roles as $role) {
            if (str_starts_with($role, $family . '.fill')) {
                $fills[] = $role;
            }
        }

        return $fills;
    }

    /**
     * Every ground the theme can paint behind something: the surfaces, the tints, and optionally the fills.
     *
     * A translucent role is not a ground. It is drawn over one, and what a reader sees underneath it is a
     * composite the source does not name; measuring the veil's own colour would report a ratio against
     * something nobody is looking at. Which roles those are is asserted separately, by name.
     *
     * @return list<string>
     */
    private static function groundsIn(
        DesignTokens $tokens,
        string $theme,
        bool $includeInverse,
        bool $includeFills,
    ): array {
        $grounds = [];

        foreach (self::opaqueRolesIn($tokens, $theme) as $role) {
            $isSurface = str_starts_with($role, 'surface.');
            $isInverse = $role === 'surface.inverse';
            $isTint = str_contains($role, '.tint');
            $isFill = str_contains($role, '.fill');

            if ($isInverse) {
                if ($includeInverse) {
                    $grounds[] = $role;
                }

                continue;
            }

            if ($isSurface || $isTint || ($isFill && $includeFills)) {
                $grounds[] = $role;
            }
        }

        return $grounds;
    }

    /**
     * Every colour role a theme defines, as a path relative to the theme.
     *
     * @return list<string>
     */
    private static function rolesIn(DesignTokens $tokens, string $theme): array
    {
        $roles = [];

        foreach ($tokens->pathsUnder($theme) as $path) {
            $roles[] = substr($path, \strlen($theme) + 1);
        }

        return $roles;
    }

    /**
     * The roles that have a colour of their own, which is every role a contrast ratio can be computed for.
     *
     * @return list<string>
     */
    private static function opaqueRolesIn(DesignTokens $tokens, string $theme): array
    {
        $roles = [];

        foreach (self::rolesIn($tokens, $theme) as $role) {
            if (!$tokens->isTranslucent($theme . '.' . $role)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    private static function themes(): array
    {
        return ['theme.light', 'theme.dark'];
    }

    /**
     * Every family of pairs this guarantee derives, named by what it measures.
     *
     * The five assertions above each take one of these. Naming them once, here, is what lets the census say
     * how large each one is instead of a report saying how large somebody remembers it being.
     *
     * @return array<string, list<array{theme: string, foreground: string, background: string}>>
     */
    private static function pairFamilies(): array
    {
        return [
            'text on the grounds it is drawn on' => self::textPairs(),
            'the focus indicator on every ground' => self::focusRingPairs(),
            'boundaries, icons and marks' => self::nonTextPairs(),
            'the decorative hairline on what it divides' => self::hairlinePairs(),
            'the recess against the rest of the stack' => self::recessPairs(),
        ];
    }

    /**
     * @return list<array{theme: string, foreground: string, background: string}>
     */
    private static function allPairs(): array
    {
        $pairs = [];

        foreach (self::pairFamilies() as $family) {
            foreach ($family as $pair) {
                $pairs[] = $pair;
            }
        }

        return $pairs;
    }

    /**
     * @param list<array{theme: string, foreground: string, background: string}> $pairs
     *
     * @return list<string>
     */
    private static function failuresAmong(array $pairs, float $threshold): array
    {
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $failures = [];

        foreach ($pairs as $pair) {
            $foreground = $tokens->colour($pair['theme'] . '.' . $pair['foreground']);
            $background = $tokens->colour($pair['theme'] . '.' . $pair['background']);
            $ratio = DesignTokens::contrastRatio($foreground, $background);

            if ($ratio < $threshold) {
                $failures[] = \sprintf(
                    '%s: %s (%s) on %s (%s) is %.2f:1, below %.1f:1',
                    $pair['theme'],
                    $pair['foreground'],
                    $foreground,
                    $pair['background'],
                    $background,
                    $ratio,
                    $threshold,
                );
            }
        }

        sort($failures);

        return $failures;
    }

    /**
     * @param list<array{theme: string, foreground: string, background: string}> $pairs
     *
     * @return list<string>
     */
    private static function identicalPairs(array $pairs): array
    {
        $tokens = DesignTokens::fromRepositoryRoot(self::repositoryRoot());
        $identical = [];

        foreach ($pairs as $pair) {
            $foreground = $tokens->colour($pair['theme'] . '.' . $pair['foreground']);
            $background = $tokens->colour($pair['theme'] . '.' . $pair['background']);

            if ($foreground === $background) {
                $identical[] = \sprintf(
                    '%s: %s and %s are both %s',
                    $pair['theme'],
                    $pair['foreground'],
                    $pair['background'],
                    $foreground,
                );
            }
        }

        sort($identical);

        return $identical;
    }

    /**
     * @param list<string> $failures
     */
    private static function report(string $headline, array $failures): string
    {
        return $headline . ":\n  - " . implode("\n  - ", $failures);
    }

    private static function repositoryRoot(): string
    {
        return \dirname(__DIR__, 4);
    }
}
