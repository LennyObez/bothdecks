<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * The SwiftUI theme.
 *
 * The type scale is the headline: the previous Swift file had none at all. Ten styles reached the web and
 * Android and stopped, leaving one line of comment naming the two families on iOS, so the surface that
 * carries the strictest typographic expectations was the one with no typography in it.
 *
 * Curves and springs are emitted as small value types rather than as `Animation` constants, so the file
 * compiles against any SwiftUI version rather than requiring the newest curve API.
 */
final class SwiftEmitter extends Emitter
{
    private const WEIGHTS = [
        100 => '.ultraLight',
        200 => '.thin',
        300 => '.light',
        400 => '.regular',
        500 => '.medium',
        600 => '.semibold',
        700 => '.bold',
        800 => '.heavy',
        900 => '.black',
    ];

    public function target(): string
    {
        return 'swift';
    }

    public function emit(TokenSource $source): array
    {
        $parts = [
            '//' . str_replace("\n", "\n//", rtrim($this->header(' '))) . "\n",
            'import SwiftUI',
            $source->extensionEntries('haptic') === [] ? '' : "#if canImport(UIKit)\nimport UIKit\n#endif",
            '',
            $this->colourHelper(),
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

        return ['BothDecksTheme.swift' => rtrim((string) preg_replace("/\n{3,}/", "\n\n", $body)) . "\n"];
    }

    private function colourHelper(): string
    {
        return "\nextension Color {\n"
            . "    /// An ARGB literal, alpha included: the scrim is the one role in the system that needs it.\n"
            . "    init(bdARGB argb: UInt32) {\n"
            . "        self.init(\n"
            . "            .sRGB,\n"
            . "            red: Double((argb >> 16) & 0xFF) / 255,\n"
            . "            green: Double((argb >> 8) & 0xFF) / 255,\n"
            . "            blue: Double(argb & 0xFF) / 255,\n"
            . "            opacity: Double((argb >> 24) & 0xFF) / 255\n"
            . "        )\n"
            . "    }\n}\n";
    }

    private function colours(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('color') as $token) {
            $lines[] = sprintf(
                '    static let %s = Color(bdARGB: %s)',
                $this->identifier($token),
                Colour::parse($token->resolved, $token->path)->argb(),
            );
        }

        $this->record('color', count($lines));

        return "\n/// The palette. Every value below appears exactly once in the product.\nenum BdColors {\n"
            . implode("\n", $lines) . "\n}\n";
    }

    private function theme(TokenSource $source): string
    {
        $modes = [];

        foreach ($source->group('theme') as $token) {
            $modes[$token->localSegments()[0]][] = $token;
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
            $fields[] = sprintf('    let %s: %s', $role, $this->swiftType($token));
        }

        $out = "\n/// The roles, one property per role, so a screen names a role and never a palette entry.\nstruct BdTheme {\n"
            . implode("\n", $fields) . "\n}\n";

        $declarations = count($fields);
        $blocks = [];

        foreach (['light', 'dark'] as $mode) {
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
                $assignments[] = sprintf('        %s: %s,', $role, $this->swiftValue($byRole[$role], $source));
            }

            $blocks[] = sprintf("    static let %s = BdTheme(\n", $mode) . rtrim(implode("\n", $assignments), ',') . "\n    )";
            $declarations += count($assignments);
        }

        $this->record('theme', $declarations);

        return $out . "\nextension BdTheme {\n" . implode("\n\n", $blocks) . "\n}\n";
    }

    private function swiftType(Token $token): string
    {
        return match ($token->type) {
            'color' => 'Color',
            'dimension' => 'CGFloat',
            'shadow' => '[BdShadowLayer]',
            default => throw new Failure(sprintf('Theme token `%s` is typed `%s`, which the Swift emitter has no rule for.', $token->path, $token->type)),
        };
    }

    private function swiftValue(Token $token, TokenSource $source): string
    {
        if ($token->type === 'color') {
            // The whole chain, not the first hop: a role pointing at another role still has to compile to the
            // palette entry behind it rather than to a copy of its value.
            $alias = $source->finalAliasOf($token);

            if ($alias !== null && str_starts_with($alias, 'color.')) {
                return 'BdColors.' . Naming::camel(array_slice(explode('.', $alias), 1));
            }

            return sprintf('Color(bdARGB: %s)', Colour::parse($token->resolved, $token->path)->argb());
        }

        if ($token->type === 'dimension') {
            return Naming::number(Value::scalar($token->resolved, $token->path));
        }

        return $this->shadowList($token);
    }

    private function dimensions(TokenSource $source, string $group, string $enumName): string
    {
        $tokens = $source->group($group);

        if ($tokens === []) {
            return '';
        }

        $lines = [];

        foreach ($tokens as $token) {
            $lines[] = sprintf(
                '    static let %s: CGFloat = %s',
                $this->identifier($token),
                Naming::number(Value::scalar($token->resolved, $token->path)),
            );
        }

        $this->record($group, count($lines));

        return sprintf("\nenum %s {\n", $enumName) . implode("\n", $lines) . "\n}\n";
    }

    private function families(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('font-family') as $token) {
            $stack = is_array($token->resolved) ? $token->resolved : [$token->resolved];
            $rendered = implode(', ', array_map(static fn (mixed $n): string => sprintf('"%s"', (string) $n), $stack));
            $lines[] = sprintf('    static let %s: [String] = [%s]', $this->identifier($token), $rendered);
        }

        $this->record('font-family', count($lines));

        return "\n/// The stacks, most specific first. On iOS a variable face is addressed by its PostScript name;\n"
            . "/// resolving these names to a bundled resource is the application's job.\nenum BdFontFamily {\n"
            . implode("\n", $lines) . "\n}\n";
    }

    private function type(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('type') as $token) {
            $style = Value::typography($token);

            if (!isset(self::WEIGHTS[$style['weight']])) {
                throw new Failure(sprintf(
                    'Type style `%s` asks for weight %d, which is not one of the nine SwiftUI weights.',
                    $token->path,
                    $style['weight'],
                ));
            }

            $lines[] = sprintf(
                "    static let %s = BdTextStyle(\n        fontSize: %s,\n        lineHeight: %s,\n        weight: %s,\n        fontFamily: %s,\n        tracking: %s,\n        fontFeatureSettings: %s\n    )",
                $this->identifier($token),
                Naming::number($style['size']),
                Naming::number($style['lineHeight']),
                self::WEIGHTS[$style['weight']],
                $this->familyReference($style['familyWritten'], $style['family']),
                // SwiftUI's `tracking` is in points, not em, so the conversion happens here rather than at
                // every call site, which is where it would otherwise be forgotten.
                Naming::number($style['letterSpacingEm'] * $style['size'], 4),
                $style['features'] === null ? 'nil' : sprintf('"%s"', addcslashes($style['features'], '"\\')),
            );
        }

        $this->record('type', count($lines));

        return "\n/// A type style, complete.\n///\n/// `lineSpacing` is what SwiftUI's `.lineSpacing` takes: the gap between lines, not the line box.\n"
            . "/// `fontFeatureSettings` carries the tabular and lining figure request that makes a column of\n"
            . "/// salaries line up; it is applied through a `UIFontDescriptor` feature setting.\nstruct BdTextStyle {\n"
            . "    let fontSize: CGFloat\n    let lineHeight: CGFloat\n    let weight: Font.Weight\n    let fontFamily: [String]\n    let tracking: CGFloat\n    let fontFeatureSettings: String?\n\n"
            . "    var lineSpacing: CGFloat { lineHeight - fontSize }\n}\n"
            . "\nenum BdType {\n" . implode("\n\n", $lines) . "\n}\n";
    }

    private function familyReference(mixed $written, mixed $resolved): string
    {
        if (is_string($written) && preg_match('/^\{font\.family\.([^}]+)\}$/', $written, $m) === 1) {
            return 'BdFontFamily.' . Naming::camel(explode('.', $m[1]));
        }

        $stack = is_array($resolved) ? $resolved : [$resolved];

        return '[' . implode(', ', array_map(static fn (mixed $n): string => sprintf('"%s"', (string) $n), $stack)) . ']';
    }

    /**
     * The per-surface type floor.
     *
     * A floor is compared against a font size, so it is measured in the same points a `Font` is, and it is
     * the size before Dynamic Type scales it: a minimum legible size that stopped scaling with the reader's
     * own setting would defeat the purpose of having one.
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
                '    static let %s: CGFloat = %s',
                $this->identifier($token),
                Naming::number(Value::scalar($token->resolved, $token->path)),
            );
        }

        $this->record('type-floor', count($lines));

        return "\n/// The smallest size each surface may set, at a system font scale of one. `absolute` holds\n"
            . "/// everywhere and has no exception; the others are the floor for the surface they name.\nenum BdTypeFloor {\n"
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
                'number' => sprintf('    static let %s: Double = %s', $this->identifier($token), Naming::number((float) $token->resolved)),
                'dimension' => sprintf('    static let %s: CGFloat = %s', $this->identifier($token), Naming::number(Value::scalar($token->resolved, $token->path))),
                default => throw new Failure(sprintf(
                    'Token `%s` is typed `%s`; a gesture constant is either a `number` or a `dimension`.',
                    $token->path,
                    $token->type,
                )),
            };
        }

        $this->record('motion-gesture', count($lines));

        return "\n/// The deck's drag transfer function. Four implementations draw this gesture, and a constant that\n"
            . "/// lives in each of them agrees with the others by luck rather than by construction.\nenum BdGesture {\n"
            . implode("\n", $lines) . "\n}\n";
    }

    private function elevation(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('elevation') as $token) {
            $lines[] = sprintf('    static let %s: [BdShadowLayer] = %s', $this->identifier($token), $this->shadowList($token));
        }

        $this->record('elevation', count($lines));

        return "\n/// A shadow layer as the source states it. SwiftUI's `.shadow` draws one layer at a time, so a\n"
            . "/// two-layer elevation is two modifiers and the values have to survive the trip.\nstruct BdShadowLayer {\n"
            . "    let offsetX: CGFloat\n    let offsetY: CGFloat\n    let blur: CGFloat\n    let spread: CGFloat\n    let color: Color\n}\n"
            . "\nenum BdElevation {\n" . implode("\n", $lines) . "\n}\n";
    }

    private function shadowList(Token $token): string
    {
        $layers = Value::shadow($token->resolved, $token->path);

        if ($layers === []) {
            return '[]';
        }

        $rendered = array_map(
            static fn (array $l): string => sprintf(
                'BdShadowLayer(offsetX: %s, offsetY: %s, blur: %s, spread: %s, color: Color(bdARGB: %s))',
                Naming::number($l['dx']),
                Naming::number($l['dy']),
                Naming::number($l['blur']),
                Naming::number($l['spread']),
                $l['colour']->argb(),
            ),
            $layers,
        );

        return "[\n        " . implode(",\n        ", $rendered) . "\n    ]";
    }

    private function easing(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('motion-easing') as $token) {
            $p = Value::cubicBezier($token->resolved, $token->path);
            $lines[] = sprintf(
                '    static let %s = BdEasingCurve(x1: %s, y1: %s, x2: %s, y2: %s)',
                $this->identifier($token),
                Naming::number($p[0]),
                Naming::number($p[1]),
                Naming::number($p[2]),
                Naming::number($p[3]),
            );
        }

        $this->record('motion-easing', count($lines));

        return "\nstruct BdEasingCurve {\n    let x1: Double\n    let y1: Double\n    let x2: Double\n    let y2: Double\n\n"
            . "    func animation(duration: TimeInterval) -> Animation {\n        .timingCurve(x1, y1, x2, y2, duration: duration)\n    }\n}\n"
            . "\nenum BdEasing {\n" . implode("\n", $lines) . "\n}\n";
    }

    private function springs(TokenSource $source): string
    {
        $lines = [];
        $reduced = [];

        foreach (Spring::all($source) as $spring) {
            $lines[] = sprintf(
                '    static let %s = BdSpring(response: %s, dampingFraction: %s)',
                $spring->identifier(),
                Naming::number($spring->responseSeconds, 4),
                Naming::number($spring->dampingRatio, 4),
            );
            $reduced[] = sprintf(
                '    static let %s: TimeInterval = %s',
                $spring->identifier(),
                Naming::number($spring->reducedDurationMs / 1000, 4),
            );
        }

        $this->record('motion-spring', count($lines) + count($reduced));

        return "\n/// `response` and `dampingFraction` are what SwiftUI asks for, computed from the source's mass,\n"
            . "/// stiffness and damping coefficient rather than transcribed from it.\nstruct BdSpring {\n"
            . "    let response: TimeInterval\n    let dampingFraction: Double\n\n"
            . "    var animation: Animation {\n        .spring(response: response, dampingFraction: dampingFraction)\n    }\n}\n"
            . "\nenum BdSprings {\n" . implode("\n", $lines) . "\n}\n"
            . "\n/// How long the fade that replaces each spring lasts when the system asks for reduced motion. A spring\n"
            . "/// cannot be made accessible by slowing it down, because what has to go is the overshoot rather than\n"
            . "/// the speed, so the animation is substituted outright and this is the length of what replaces it.\n"
            . "enum BdSpringsReduced {\n" . implode("\n", $reduced) . "\n}\n";
    }

    private function motion(TokenSource $source): string
    {
        $lines = [];

        foreach ($source->group('motion-duration') as $token) {
            $lines[] = sprintf(
                '    static let %s: TimeInterval = %s',
                $this->identifier($token),
                Naming::number(Value::duration($token->resolved, $token->path) / 1000, 4),
            );
        }

        $count = count($lines);
        $out = "\nenum BdMotion {\n" . implode("\n", $lines) . "\n}\n";

        $map = ReducedMotion::map($source);

        if ($map !== []) {
            $substitutions = [];

            foreach ($map as $name => $substitute) {
                $substitutions[] = sprintf(
                    '    static let %s: TimeInterval = BdMotion.%s',
                    Naming::camel([$name]),
                    Naming::camel([$substitute]),
                );
            }

            $out .= "\n/// What each duration becomes when the system asks for reduced motion.\nenum BdMotionReduced {\n"
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

            if (!is_array($event) || !array_key_exists('ios', $event)) {
                throw new Failure(sprintf('Haptic event `%s` must name an `ios` generator.', $path));
            }

            $lines[] = sprintf(
                '    static let %s: BdHaptic? = %s',
                Naming::identifier(Naming::camel([(string) $name]), 'haptic'),
                $this->iosHaptic((string) $event['ios'], $path),
            );
        }

        $this->record('haptic', count($lines));

        return "\n#if canImport(UIKit)\n"
            . "/// A haptic event. `nil` means the event is deliberately silent: a pass and a snap-back are silent\n"
            . "/// by design, and a silence that is written down is not a silence nobody implemented.\nenum BdHaptic {\n"
            . "    case selection\n    case impact(UIImpactFeedbackGenerator.FeedbackStyle)\n    case notification(UINotificationFeedbackGenerator.FeedbackType)\n}\n"
            . "\nenum BdHaptics {\n" . implode("\n", $lines) . "\n}\n#endif\n";
    }

    private function iosHaptic(string $value, string $path): string
    {
        if ($value === 'none') {
            return 'nil';
        }

        if ($value === 'selectionChanged') {
            return '.selection';
        }

        if (preg_match('/^impact\(\.(light|medium|heavy|soft|rigid)\)$/', $value, $m) === 1) {
            return sprintf('.impact(.%s)', $m[1]);
        }

        if (preg_match('/^notification\(\.(success|warning|error)\)$/', $value, $m) === 1) {
            return sprintf('.notification(.%s)', $m[1]);
        }

        throw new Failure(
            sprintf('Haptic event `%s` names the iOS feedback `%s`, which the generator does not recognise.', $path, $value),
            ['Use `none`, `selectionChanged`, `impact(.light|.medium|.heavy|.soft|.rigid)` or `notification(.success|.warning|.error)`.'],
        );
    }

    private function identifier(Token $token): string
    {
        return Naming::identifier(Naming::camel($token->localSegments()), $token->group);
    }
}
