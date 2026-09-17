<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * The Jetpack Compose theme.
 *
 * Spring constants are computed rather than transcribed. The source states a damping coefficient;
 * `androidx.compose.animation.core.spring` takes a damping ratio, and copying one into the other gives a
 * spring roughly forty times overdamped: an animation that looks broken but not broken enough to report.
 */
final class KotlinEmitter extends Emitter
{
    public function target(): string
    {
        return 'kotlin';
    }

    public function emit(TokenSource $source): array
    {
        $parts = [
            '//' . str_replace("\n", "\n//", rtrim($this->header(' '))) . "\n",
            'package com.bothdecks.design',
            $this->imports($source),
            '',
            $this->colours($source),
            $this->theme($source),
            $this->dimensions($source, 'space', 'BdSpace'),
            $this->dimensions($source, 'radius', 'BdRadius'),
            $this->dimensions($source, 'border-width', 'BdBorderWidth'),
            $this->dimensions($source, 'dash', 'BdDash'),
            $this->dimensions($source, 'icon', 'BdIcon'),
            $this->dimensions($source, 'control', 'BdControl'),
            $this->families($source),
            $this->type($source),
            $this->typeFloor($source),
            $this->gesture($source),
            $this->elevation($source),
            $this->easing($source),
            $this->springs($source),
            $this->motion($source),
            $this->haptics($source),
        ];

        if ($source->hasGroup('safe-area')) {
            $parts[] = $this->dimensions($source, 'safe-area', 'BdSafeArea');
        }

        $body = implode("\n", array_filter($parts, static fn (string $p): bool => $p !== ''));

        return ['BothDecksTheme.kt' => rtrim((string) preg_replace("/\n{3,}/", "\n\n", $body)) . "\n"];
    }

    private function imports(TokenSource $source): string
    {
        $imports = [
            'androidx.compose.animation.core.CubicBezierEasing',
            'androidx.compose.animation.core.Easing',
            'androidx.compose.ui.graphics.Color',
            'androidx.compose.ui.text.font.FontWeight',
            'androidx.compose.ui.unit.Dp',
            'androidx.compose.ui.unit.TextUnit',
            'androidx.compose.ui.unit.dp',
            'androidx.compose.ui.unit.em',
            'androidx.compose.ui.unit.sp',
        ];

        if ($source->extensionEntries('haptic') !== []) {
            $imports[] = 'android.os.VibrationEffect';
            $imports[] = 'android.view.HapticFeedbackConstants';
        }

        sort($imports);

        return "\n" . implode("\n", array_map(static fn (string $i): string => 'import ' . $i, $imports)) . "\n";
    }

    private function colours(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('color') as $token) {
            $lines[] = sprintf(
                '    val %s = Color(%s)',
                $this->identifier($token),
                Colour::parse($token->resolved, $token->path)->argb(),
            );
        }

        $this->record('color', count($lines));

        return "\n/** The palette. Every value below appears exactly once in the product. */\nobject BdColors {\n"
            . implode("\n", $lines) . "\n}\n";
    }

    private function theme(TokenSource $source): string
    {
        $modes = [];

        foreach ($source->group('theme') as $token) {
            $local = $token->localSegments();
            $modes[$local[0]][] = $token;
        }

        foreach (['light', 'dark'] as $required) {
            if (!isset($modes[$required])) {
                throw new Failure(sprintf('The theme declares no `%s` mode.', $required));
            }
        }

        $fields = [];
        $roles = [];

        foreach ($modes['light'] as $token) {
            $role = Naming::camel(array_slice($token->localSegments(), 1));
            $roles[] = $role;
            $fields[] = sprintf('    val %s: %s,', $role, $this->kotlinType($token));
        }

        $out = "\n/**\n * The roles, one field per role, so a screen names a role and never a palette entry.\n */\ndata class BdTheme(\n"
            . rtrim(implode("\n", $fields), ',') . "\n)\n";

        $declarations = count($fields);

        foreach (['light' => 'BdLightTheme', 'dark' => 'BdDarkTheme'] as $mode => $name) {
            $byRole = [];

            foreach ($modes[$mode] as $token) {
                $byRole[Naming::camel(array_slice($token->localSegments(), 1))] = $token;
            }

            $missing = array_diff($roles, array_keys($byRole));
            $extra = array_diff(array_keys($byRole), $roles);

            if ($missing !== [] || $extra !== []) {
                throw new Failure(
                    sprintf('The `%s` mode does not carry the same roles as the light mode.', $mode),
                    array_merge(
                        array_map(static fn (string $r): string => 'missing: ' . $r, $missing),
                        array_map(static fn (string $r): string => 'only in ' . $mode . ': ' . $r, $extra),
                    ),
                );
            }

            $assignments = [];

            foreach ($roles as $role) {
                $assignments[] = sprintf('    %s = %s,', $role, $this->kotlinValue($byRole[$role], $source));
            }

            $out .= sprintf("\nval %s = BdTheme(\n", $name) . rtrim(implode("\n", $assignments), ',') . "\n)\n";
            $declarations += count($assignments);
        }

        $this->record('theme', $declarations);

        return $out;
    }

    private function kotlinType(Token $token): string
    {
        return match ($token->type) {
            'color' => 'Color',
            'dimension' => 'Dp',
            'shadow' => 'List<BdShadowLayer>',
            default => throw new Failure(sprintf('Theme token `%s` is typed `%s`, which the Kotlin emitter has no rule for.', $token->path, $token->type)),
        };
    }

    private function kotlinValue(Token $token, TokenSource $source): string
    {
        if ($token->type === 'color') {
            // The whole chain, not the first hop: a role pointing at another role still has to compile to the
            // palette entry behind it rather than to a copy of its value.
            $alias = $source->finalAliasOf($token);

            if ($alias !== null && str_starts_with($alias, 'color.')) {
                return 'BdColors.' . Naming::camel(array_slice(explode('.', $alias), 1));
            }

            return sprintf('Color(%s)', Colour::parse($token->resolved, $token->path)->argb());
        }

        if ($token->type === 'dimension') {
            return Naming::number(Value::scalar($token->resolved, $token->path)) . '.dp';
        }

        return $this->shadowList($token);
    }

    private function dimensions(TokenSource $source, string $group, string $objectName): string
    {
        $tokens = $source->group($group);

        if ($tokens === []) {
            return '';
        }

        $lines = [];

        foreach ($tokens as $token) {
            $lines[] = sprintf(
                '    val %s: Dp = %s.dp',
                $this->identifier($token),
                Naming::number(Value::scalar($token->resolved, $token->path)),
            );
        }

        $this->record($group, count($lines));

        return sprintf("\nobject %s {\n", $objectName) . implode("\n", $lines) . "\n}\n";
    }

    private function families(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('font-family') as $token) {
            $stack = is_array($token->resolved) ? $token->resolved : [$token->resolved];
            $rendered = implode(', ', array_map(static fn (mixed $n): string => sprintf('"%s"', (string) $n), $stack));
            $lines[] = sprintf('    val %s: List<String> = listOf(%s)', $this->identifier($token), $rendered);
        }

        $this->record('font-family', count($lines));

        return "\n/**\n * The stacks, most specific first. Resolving a name to a bundled resource is the application's\n * job; what belongs to the design system is which name comes first and what falls back to what.\n */\nobject BdFontFamily {\n"
            . implode("\n", $lines) . "\n}\n";
    }

    private function type(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('type') as $token) {
            $style = Value::typography($token);
            $family = $this->familyReference($style['familyWritten'], $style['family']);

            $lines[] = sprintf(
                "    val %s = BdTextStyle(\n        fontSize = %s.sp,\n        lineHeight = %s.sp,\n        fontWeight = FontWeight(%d),\n        fontFamily = %s,\n        letterSpacing = %s.em,\n        fontFeatureSettings = %s,\n    )",
                $this->identifier($token),
                Naming::number($style['size']),
                Naming::number($style['lineHeight']),
                $style['weight'],
                $family,
                // Parenthesised when negative: `-0.015.em` reads as a negation of a TextUnit rather than as
                // a negative TextUnit, and the two are only accidentally the same.
                $style['letterSpacingEm'] < 0 ? '(' . Naming::number($style['letterSpacingEm']) . ')' : Naming::number($style['letterSpacingEm']),
                $style['features'] === null ? 'null' : sprintf('"%s"', addcslashes($style['features'], '"\\')),
            );
        }

        $this->record('type', count($lines));

        return "\n/**\n * A type style, complete. `fontFeatureSettings` carries the tabular and lining figure request:\n * it is what makes a column of salaries line up, and it survived the previous handoff only as a comment.\n */\ndata class BdTextStyle(\n"
            . "    val fontSize: TextUnit,\n    val lineHeight: TextUnit,\n    val fontWeight: FontWeight,\n    val fontFamily: List<String>,\n    val letterSpacing: TextUnit,\n    val fontFeatureSettings: String?\n)\n"
            . "\nobject BdType {\n" . implode("\n\n", $lines) . "\n}\n";
    }

    private function familyReference(mixed $written, mixed $resolved): string
    {
        if (is_string($written) && preg_match('/^\{font\.family\.([^}]+)\}$/', $written, $m) === 1) {
            return 'BdFontFamily.' . Naming::camel(explode('.', $m[1]));
        }

        $stack = is_array($resolved) ? $resolved : [$resolved];

        return 'listOf(' . implode(', ', array_map(static fn (mixed $n): string => sprintf('"%s"', (string) $n), $stack)) . ')';
    }

    /**
     * The per-surface type floor, in `sp` rather than `dp`.
     *
     * A floor is compared against a font size, and a font size on Android is a scaled pixel: expressed in
     * `dp` it would stop scaling with the reader's own text-size setting, which is the one thing a minimum
     * legible size must not do.
     */
    private function typeFloor(TokenSource $source): string
    {
        $tokens = $source->group('type-floor');

        if ($tokens === []) {
            return '';
        }

        $lines = [];

        foreach ($tokens as $token) {
            $lines[] = sprintf(
                '    val %s: TextUnit = %s.sp',
                $this->identifier($token),
                Naming::number(Value::scalar($token->resolved, $token->path)),
            );
        }

        $this->record('type-floor', count($lines));

        return "\n/**\n * The smallest size each surface may set, at a system font scale of one. `absolute` holds everywhere\n * and has no exception; the others are the floor for the surface they name.\n */\nobject BdTypeFloor {\n"
            . implode("\n", $lines) . "\n}\n";
    }

    private function gesture(TokenSource $source): string
    {
        $tokens = $source->group('motion-gesture');

        if ($tokens === []) {
            return '';
        }

        $lines = [];

        foreach ($tokens as $token) {
            $lines[] = match ($token->type) {
                'number' => sprintf('    const val %s: Float = %sf', $this->identifier($token), Naming::number((float) $token->resolved)),
                'dimension' => sprintf('    val %s: Dp = %s.dp', $this->identifier($token), Naming::number(Value::scalar($token->resolved, $token->path))),
                default => throw new Failure(sprintf(
                    'Token `%s` is typed `%s`; a gesture constant is either a `number` or a `dimension`.',
                    $token->path,
                    $token->type,
                )),
            };
        }

        $this->record('motion-gesture', count($lines));

        return "\n/**\n * The deck's drag transfer function. Four implementations draw this gesture, and a constant that lives\n * in each of them agrees with the others by luck rather than by construction.\n */\nobject BdGesture {\n"
            . implode("\n", $lines) . "\n}\n";
    }

    private function elevation(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('elevation') as $token) {
            $lines[] = sprintf('    val %s: List<BdShadowLayer> = %s', $this->identifier($token), $this->shadowList($token));
        }

        $this->record('elevation', count($lines));

        return "\n/**\n * A shadow layer as the source states it. Compose's own elevation parameter cannot express two\n * layers with different alphas, so the values are carried and the drawing is left to the caller.\n */\ndata class BdShadowLayer(\n"
            . "    val offsetX: Dp,\n    val offsetY: Dp,\n    val blur: Dp,\n    val spread: Dp,\n    val color: Color\n)\n"
            . "\nobject BdElevation {\n" . implode("\n", $lines) . "\n}\n";
    }

    private function shadowList(Token $token): string
    {
        $layers = Value::shadow($token->resolved, $token->path);

        if ($layers === []) {
            return 'emptyList()';
        }

        $rendered = array_map(
            static fn (array $l): string => sprintf(
                'BdShadowLayer(%s.dp, %s.dp, %s.dp, %s.dp, Color(%s))',
                Naming::number($l['dx']),
                Naming::number($l['dy']),
                Naming::number($l['blur']),
                Naming::number($l['spread']),
                $l['colour']->argb(),
            ),
            $layers,
        );

        return 'listOf(' . implode(', ', $rendered) . ')';
    }

    private function easing(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('motion-easing') as $token) {
            $p = Value::cubicBezier($token->resolved, $token->path);
            $lines[] = sprintf(
                '    val %s: Easing = CubicBezierEasing(%sf, %sf, %sf, %sf)',
                $this->identifier($token),
                Naming::number($p[0]),
                Naming::number($p[1]),
                Naming::number($p[2]),
                Naming::number($p[3]),
            );
        }

        $this->record('motion-easing', count($lines));

        return "\nobject BdEasing {\n" . implode("\n", $lines) . "\n}\n";
    }

    private function springs(TokenSource $source): string
    {
        $lines = [];
        $reduced = [];

        foreach (Spring::all($source) as $spring) {
            $lines[] = sprintf(
                '    val %s = BdSpring(stiffness = %sf, dampingRatio = %sf)',
                $spring->identifier(),
                Naming::number($spring->stiffness),
                Naming::number($spring->dampingRatio, 4),
            );
            $reduced[] = sprintf(
                '    const val %sMs: Int = %d',
                $spring->identifier(),
                (int) round($spring->reducedDurationMs),
            );
        }

        $this->record('motion-spring', count($lines) + count($reduced));

        return "\n/**\n * `dampingRatio` is the ratio Compose asks for, computed from the source's mass, stiffness and\n * damping coefficient. The coefficient itself is not exposed: passing it where a ratio is expected is a\n * silent, order-of-magnitude mistake.\n */\ndata class BdSpring(val stiffness: Float, val dampingRatio: Float)\n"
            . "\nobject BdSprings {\n" . implode("\n", $lines) . "\n}\n"
            . "\n/**\n * How long the fade that replaces each spring lasts when the system asks for reduced motion. A spring\n * cannot be made accessible by slowing it down, because what has to go is the overshoot rather than the\n * speed, so the animation is substituted outright and this is the length of what replaces it.\n */\nobject BdSpringsReduced {\n"
            . implode("\n", $reduced) . "\n}\n";
    }

    private function motion(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('motion-duration') as $token) {
            $lines[] = sprintf(
                '    const val %s: Int = %d',
                $this->identifier($token) . 'Ms',
                (int) round(Value::duration($token->resolved, $token->path)),
            );
        }

        $count = count($lines);
        $out = "\nobject BdMotion {\n" . implode("\n", $lines) . "\n}\n";

        $map = ReducedMotion::map($source);

        if ($map !== []) {
            $substitutions = [];

            foreach ($map as $name => $substitute) {
                $substitutions[] = sprintf(
                    '    const val %sMs: Int = BdMotion.%sMs',
                    Naming::camel([$name]),
                    Naming::camel([$substitute]),
                );
            }

            $out .= "\n/**\n * What each duration becomes when the system asks for reduced motion. The web has carried this\n * substitution since the first stylesheet; Android and iOS never received it in any form.\n */\nobject BdMotionReduced {\n"
                . implode("\n", $substitutions) . "\n}\n";

            $count += count($substitutions);

            if ($source->hasGroup('motion-reduced')) {
                $this->record('motion-reduced', count($substitutions));
            }
        }

        $this->record('motion-duration', $count);

        return $out;
    }

    private function haptics(TokenSource $source): string
    {
        $events = $source->extensionEntries('haptic');

        if ($events === []) {
            return '';
        }

        $lines = [];

        foreach ($events as $name => $event) {
            $path = 'haptic.' . $name;

            if (!is_array($event) || !array_key_exists('android', $event)) {
                throw new Failure(sprintf('Haptic event `%s` must name an `android` constant.', $path));
            }

            $lines[] = sprintf(
                '    val %s: BdHaptic? = %s',
                Naming::identifier(Naming::camel([(string) $name]), 'haptic'),
                $this->androidHaptic((string) $event['android'], $path),
            );
        }

        $this->record('haptic', count($lines));

        return "\n/**\n * A haptic event. `null` means the event is deliberately silent: a pass and a snap-back are silent by\n * design, and a silence that is written down is not the same thing as a silence nobody implemented.\n */\nsealed interface BdHaptic {\n"
            . "    /** A view-level feedback constant, played through `View.performHapticFeedback`. */\n    @JvmInline value class Feedback(val constant: Int) : BdHaptic\n\n"
            . "    /** A predefined vibration effect, played through a `Vibrator`. */\n    @JvmInline value class Effect(val id: Int) : BdHaptic\n}\n"
            . "\nobject BdHaptics {\n" . implode("\n", $lines) . "\n}\n";
    }

    private function androidHaptic(string $constant, string $path): string
    {
        if ($constant === 'none') {
            return 'null';
        }

        if (preg_match('/^EFFECT_[A-Z_]+$/', $constant) === 1) {
            return sprintf('BdHaptic.Effect(VibrationEffect.%s)', $constant);
        }

        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $constant) === 1) {
            return sprintf('BdHaptic.Feedback(HapticFeedbackConstants.%s)', $constant);
        }

        throw new Failure(
            sprintf('Haptic event `%s` names the Android constant `%s`, which the generator does not recognise.', $path, $constant),
            ['Use `none`, an `EFFECT_*` name from `VibrationEffect`, or a screaming-snake-case name from `HapticFeedbackConstants`.'],
        );
    }

    private function identifier(Token $token): string
    {
        return Naming::identifier(Naming::camel($token->localSegments()), $token->group);
    }
}
