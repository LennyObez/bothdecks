<?php

declare(strict_types=1);

namespace BothDecks\Design;

/**
 * Custom properties for the web.
 *
 * The dark theme is selected by `prefers-color-scheme` as well as by `data-theme`: system preference by
 * default, explicit choice winning in both directions. A theme nothing ever selects is not a delivered theme.
 *
 * Faces are declared with `@font-face` against self-hosted files, and the emitter refuses to produce an
 * `@import`: a stylesheet that pulls a face from a third party sends every visitor's address there before a
 * word is drawn.
 */
final class CssEmitter extends Emitter
{
    /** Where the self-hosted faces sit, relative to the emitted stylesheet. */
    private const FONT_DIRECTORY = '../fonts/';

    /** The custom-property prefix each group's names are built from. */
    private const PREFIXES = [
        'color' => 'color',
        'font-family' => 'font',
        'type' => 'type',
        'type-floor' => 'type-floor',
        'motion-gesture' => 'gesture',
        'space' => 'space',
        'radius' => 'radius',
        'elevation' => 'elevation',
        'border-width' => 'border-width',
        'motion-duration' => 'duration',
        'motion-easing' => 'easing',
        'motion-spring' => 'spring',
        'dash' => 'dash',
        'icon' => 'icon',
        'control' => 'control',
        'safe-area' => 'safe-area',
    ];

    /** @var array<string, string> token path => custom property name */
    private array $names = [];

    public function target(): string
    {
        return 'css';
    }

    public function emit(TokenSource $source): array
    {
        $this->names = [];

        foreach ($source->tokens() as $token) {
            if (isset(self::PREFIXES[$token->group])) {
                $this->names[$token->path] = self::PREFIXES[$token->group] . '-' . Naming::kebab($token->localSegments());

                continue;
            }

            // Theme roles are named too, and for the same reason. A role that points at another role of the
            // same theme has to compile to a `var()` naming it; without this it fell through to writing the
            // colour out, which is a second copy of the role it names and one that stops following it the
            // moment that role changes. The property that says a value lives in one place does not have an
            // exception for the case where the value it names is itself a role.
            if ($token->group === 'theme' && count($token->localSegments()) >= 2) {
                $this->names[$token->path] = $this->roleName($token);
            }
        }

        return [
            'colors.css' => $this->colours($source),
            'foundations.css' => $this->foundations($source),
        ];
    }

    private function colours(TokenSource $source): string
    {
        $out = "/*\n" . $this->header(' * ') . " */\n\n";

        $primitives = [];

        foreach ($source->group('color') as $token) {
            $primitives[] = sprintf('  --%s: %s;', $this->names[$token->path], $this->colourValue($token));
        }

        $this->record('color', count($primitives));

        $modes = $this->modes($source);
        $roles = [];

        foreach ($modes as $mode => $tokens) {
            $lines = [];

            foreach ($tokens as $token) {
                $lines[] = sprintf('  --%s: %s;', $this->roleName($token), $this->roleValue($token));
            }

            $roles[$mode] = $lines;
            $this->record('theme', count($lines));
        }

        $out .= ":root {\n";
        $out .= "  /* Primitives. Nothing outside this file may restate one of these values. */\n";
        $out .= implode("\n", $primitives) . "\n\n";
        $out .= "  /* Light theme, the default when the visitor has expressed no preference. */\n";
        $out .= implode("\n", $roles['light']) . "\n";
        $out .= "}\n";

        // The system preference, honoured unless the visitor has explicitly chosen light. Without this block
        // the dark theme exists and is never selected.
        $out .= "\n@media (prefers-color-scheme: dark) {\n";
        $out .= "  :root:not([data-theme=\"light\"]) {\n";
        $out .= implode("\n", array_map(static fn (string $l): string => '  ' . $l, $roles['dark'])) . "\n";
        $out .= "  }\n}\n";

        // An explicit choice wins over the system, in both directions.
        $out .= "\n:root[data-theme=\"light\"] {\n" . implode("\n", $roles['light']) . "\n}\n";

        foreach ($modes as $mode => $tokens) {
            if ($mode === 'light') {
                continue;
            }

            $out .= sprintf("\n:root[data-theme=\"%s\"] {\n", $mode) . implode("\n", $roles[$mode]) . "\n}\n";
        }

        return $out;
    }

    private function foundations(TokenSource $source): string
    {
        $out = "/*\n" . $this->header(' * ') . " */\n";
        $out .= $this->fontFaces($source);
        $out .= "\n:root {\n";

        $blocks = [];
        $blocks[] = $this->block('Families', $this->familyLines($source));
        $blocks[] = $this->block('Type scale', $this->typeLines($source));
        $blocks[] = $this->block('The smallest size each surface may set, at a system font scale of one', $this->simpleLines($source, 'type-floor'));
        $blocks[] = $this->block('Space', $this->simpleLines($source, 'space'));
        $blocks[] = $this->block('Radius', $this->simpleLines($source, 'radius'));
        $blocks[] = $this->block('Border widths, named apart from the border colours so the two cannot collide', $this->simpleLines($source, 'border-width'));
        $blocks[] = $this->block('The dash, drawn from two lengths so every target draws the same one', $this->simpleLines($source, 'dash'));
        $blocks[] = $this->block('Icons', $this->simpleLines($source, 'icon'));
        $blocks[] = $this->block('Controls', $this->simpleLines($source, 'control'));
        $blocks[] = $this->block('Elevation', $this->elevationLines($source));
        $blocks[] = $this->block('Duration', $this->durationLines($source));
        $blocks[] = $this->block('Easing', $this->easingLines($source));
        $blocks[] = $this->block('Springs, for the script that animates a card rather than for a CSS transition', $this->springLines($source));
        $blocks[] = $this->block('The deck\'s drag transfer function, read by the script that moves the card', $this->gestureLines($source));

        if ($source->hasGroup('safe-area')) {
            $blocks[] = $this->block('Safe areas', $this->simpleLines($source, 'safe-area'));
        }

        $out .= implode("\n\n", array_filter($blocks, static fn (string $b): bool => $b !== ''));
        $out .= "\n}\n";
        $out .= $this->reducedMotion($source);

        return $out;
    }

    /** @param list<string> $lines */
    private function block(string $title, array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        return '  /* ' . $title . " */\n" . implode("\n", $lines);
    }

    private function fontFaces(TokenSource $source): string
    {
        $faces = [];

        foreach ($source->group('font-family') as $token) {
            $descriptors = $token->extension('bothdecks.face');

            if (!is_array($descriptors)) {
                throw new Failure(
                    sprintf('Font family `%s` declares no self-hosted face.', $token->path),
                    [
                        'Add `$extensions."bothdecks.face"` to the token: a list of descriptors, each with at least `file` and `weight`.',
                        'An empty list is accepted and means the stack is deliberately resolved by the platform.',
                        'What is not accepted is silence, because the only other way to obtain a face is to fetch it from somebody else\'s server.',
                    ],
                );
            }

            $family = $this->primaryFamily($token);

            foreach ($descriptors as $index => $descriptor) {
                $faces[] = $this->fontFace($family, $descriptor, sprintf('%s[%d]', $token->path, $index));
            }
        }

        if ($faces === []) {
            return '';
        }

        return "\n" . implode("\n", $faces);
    }

    private function fontFace(string $family, mixed $descriptor, string $where): string
    {
        if (!is_array($descriptor) || !isset($descriptor['file'], $descriptor['weight'])) {
            throw new Failure(sprintf('Face descriptor `%s` needs at least `file` and `weight`.', $where));
        }

        $file = (string) $descriptor['file'];

        if (str_contains($file, '://')) {
            throw new Failure(
                sprintf('Face descriptor `%s` points at `%s`.', $where, $file),
                ['Faces are self-hosted. A remote URL here would send every visitor\'s address to a third party before the first word is drawn.'],
            );
        }

        // A face file has to be named after the family it carries. Without this, replacing the display face
        // renames the family and leaves the descriptors pointing at the old binaries, and the stylesheet
        // still validates while every display string falls back to the platform serif.
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', (string) ($descriptor['family'] ?? $family)));

        if (!str_starts_with(strtolower(basename($file)), $slug)) {
            throw new Failure(
                sprintf('Face descriptor `%s` points at `%s`, which is not named after the family `%s`.', $where, $file, $descriptor['family'] ?? $family),
                ['A family and its binaries are replaced together. Rename the files, or correct the family name.'],
            );
        }

        $format = (string) ($descriptor['format'] ?? 'woff2');
        $lines = [
            '@font-face {',
            sprintf('  font-family: "%s";', (string) ($descriptor['family'] ?? $family)),
            sprintf('  src: url("%s%s") format("%s");', self::FONT_DIRECTORY, $file, $format),
            sprintf('  font-weight: %s;', (string) $descriptor['weight']),
            sprintf('  font-style: %s;', (string) ($descriptor['style'] ?? 'normal')),
            sprintf('  font-display: %s;', (string) ($descriptor['display'] ?? 'swap')),
        ];

        if (isset($descriptor['unicodeRange'])) {
            $lines[] = sprintf('  unicode-range: %s;', (string) $descriptor['unicodeRange']);
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function primaryFamily(Token $token): string
    {
        $stack = $token->resolved;

        if (is_string($stack)) {
            return $stack;
        }

        if (!is_array($stack) || $stack === []) {
            throw new Failure(sprintf('Font family `%s` is empty.', $token->path));
        }

        return (string) $stack[array_key_first($stack)];
    }

    /** @return list<string> */
    private function familyLines(TokenSource $source): array
    {
        $lines = [];

        foreach ($source->group('font-family') as $token) {
            $lines[] = sprintf('  --%s: %s;', $this->names[$token->path], self::familyStack($token->resolved));
        }

        $this->record('font-family', count($lines));

        return $lines;
    }

    /** @return list<string> */
    private function typeLines(TokenSource $source): array
    {
        $lines = [];

        foreach ($source->group('type') as $token) {
            $style = Value::typography($token);
            $name = $this->names[$token->path];
            $familyRef = $this->reference(
                $style['familyWritten'],
                $style['family'],
                static fn (mixed $v): string => self::familyStack($v),
            );

            $lines[] = sprintf('  --%s-family: %s;', $name, $familyRef);
            $lines[] = sprintf('  --%s-size: %spx;', $name, Naming::number($style['size']));
            // Unitless, as the source states it: a line height in pixels stops scaling the moment a reader
            // enlarges the text, which is the one thing it must keep doing.
            $lines[] = sprintf('  --%s-line-height: %s;', $name, Naming::number($style['lineHeightRatio']));
            $lines[] = sprintf('  --%s-weight: %d;', $name, $style['weight']);
            $lines[] = sprintf('  --%s-tracking: %sem;', $name, Naming::number($style['letterSpacingEm']));
            // The shorthand the previous hand-written file exposed, kept so existing call sites still work.
            $lines[] = sprintf(
                '  --%s-font: %d %spx/%s %s;',
                $name,
                $style['weight'],
                Naming::number($style['size']),
                Naming::number($style['lineHeightRatio']),
                $familyRef,
            );
            // Emitted as a declaration rather than described in a comment. Tabular figures are the most
            // functional typographic decision in the system: a column of salaries or of codes only aligns
            // because of them, and a setting a target has to be told about by hand is a setting one target
            // will be missing.
            $lines[] = sprintf('  --%s-features: %s;', $name, $style['features'] ?? 'normal');
        }

        $this->record('type', count($lines));

        return $lines;
    }

    /** @return list<string> */
    private function simpleLines(TokenSource $source, string $group): array
    {
        $lines = [];

        foreach ($source->group($group) as $token) {
            $lines[] = sprintf('  --%s: %s;', $this->names[$token->path], Value::dimensionCss($token->resolved, $token->path));
        }

        $this->record($group, count($lines));

        return $lines;
    }

    /**
     * The gesture constants, each rendered as what it is.
     *
     * A commit threshold is a fraction of the card's width and a rotation cap is in degrees; neither is a
     * length, and `Value::dimensionCss` would put `px` after both. So the token's own `$type` decides: a
     * `dimension` is emitted with its unit, a `number` bare. That is the whole reason the source types these
     * eleven values individually rather than dropping them into one object.
     *
     * @return list<string>
     */
    private function gestureLines(TokenSource $source): array
    {
        $lines = [];

        foreach ($source->group('motion-gesture') as $token) {
            $lines[] = sprintf('  --%s: %s;', $this->names[$token->path], $this->scalarOrDimension($token));
        }

        $this->record('motion-gesture', count($lines));

        return $lines;
    }

    private function scalarOrDimension(Token $token): string
    {
        return match ($token->type) {
            'number' => Naming::number((float) $token->resolved),
            'dimension' => Value::dimensionCss($token->resolved, $token->path),
            default => throw new Failure(sprintf(
                'Token `%s` is typed `%s`; a gesture constant is either a `number` or a `dimension`.',
                $token->path,
                $token->type,
            )),
        };
    }

    /** @return list<string> */
    private function elevationLines(TokenSource $source): array
    {
        $lines = [];

        foreach ($source->group('elevation') as $token) {
            $lines[] = sprintf('  --%s: %s;', $this->names[$token->path], Value::shadowCss($token->resolved, $token->path));
        }

        $this->record('elevation', count($lines));

        return $lines;
    }

    /** @return list<string> */
    private function durationLines(TokenSource $source): array
    {
        $lines = [];

        foreach ($source->group('motion-duration') as $token) {
            $lines[] = sprintf('  --%s: %sms;', $this->names[$token->path], Naming::number(Value::duration($token->resolved, $token->path)));
        }

        $this->record('motion-duration', count($lines));

        return $lines;
    }

    /** @return list<string> */
    private function easingLines(TokenSource $source): array
    {
        $lines = [];

        foreach ($source->group('motion-easing') as $token) {
            $p = Value::cubicBezier($token->resolved, $token->path);
            $lines[] = sprintf(
                '  --%s: cubic-bezier(%s, %s, %s, %s);',
                $this->names[$token->path],
                Naming::number($p[0]),
                Naming::number($p[1]),
                Naming::number($p[2]),
                Naming::number($p[3]),
            );
        }

        $this->record('motion-easing', count($lines));

        return $lines;
    }

    /** @return list<string> */
    private function springLines(TokenSource $source): array
    {
        $lines = [];

        foreach (Spring::all($source) as $spring) {
            $name = self::PREFIXES['motion-spring'] . '-' . $spring->kebab();

            $lines[] = sprintf('  --%s-mass: %s;', $name, Naming::number($spring->mass));
            $lines[] = sprintf('  --%s-stiffness: %s;', $name, Naming::number($spring->stiffness));
            // The ratio, not the coefficient. Every animation runtime that takes one of the two takes this
            // one, and the two differ by a factor large enough to look like a different animation.
            $lines[] = sprintf('  --%s-damping-ratio: %s;', $name, Naming::number($spring->dampingRatio, 4));
            $lines[] = sprintf('  --%s-response: %ss;', $name, Naming::number($spring->responseSeconds, 4));
        }

        $this->record('motion-spring', count($lines));

        return $lines;
    }

    private function reducedMotion(TokenSource $source): string
    {
        $map = ReducedMotion::map($source);

        if ($map === []) {
            return '';
        }

        $lines = [];

        foreach ($map as $name => $substitute) {
            // A duration the map leaves alone is left alone here too. `--duration-instant:
            // var(--duration-instant)` is a cycle, and a custom property that refers to itself is invalid at
            // computed-value time: the declaration would not restate the value, it would destroy it.
            if ($name === $substitute) {
                continue;
            }

            $lines[] = sprintf('    --duration-%s: var(--duration-%s);', Naming::kebab([$name]), Naming::kebab([$substitute]));
        }

        $this->record('motion-duration', count($lines));

        if ($source->hasGroup('motion-reduced')) {
            $this->record('motion-reduced', count($lines));
        }

        // The springs are replaced rather than slowed: what the reader gets instead is a fade, and this is how
        // long it lasts. The name is its own rather than an override of `--spring-*-response`, because a
        // response of zero would tell a script to run the spring instantly, which is still a spring.
        $springLines = [];

        foreach (Spring::all($source) as $spring) {
            $springLines[] = sprintf(
                '    --%s-%s-reduced-duration: %sms;',
                self::PREFIXES['motion-spring'],
                $spring->kebab(),
                Naming::number($spring->reducedDurationMs),
            );
        }

        $this->record('motion-spring', count($springLines));

        if ($lines === [] && $springLines === []) {
            return '';
        }

        return "\n@media (prefers-reduced-motion: reduce) {\n  :root {\n"
            . implode("\n", [...$lines, ...$springLines])
            . "\n  }\n}\n";
    }

    /** @return array<string, array<string, Token>> mode => tokens */
    private function modes(TokenSource $source): array
    {
        $modes = [];

        foreach ($source->group('theme') as $token) {
            $local = $token->localSegments();

            if (count($local) < 2) {
                throw new Failure(sprintf('Theme token `%s` has no mode and role.', $token->path));
            }

            $modes[$local[0]][$token->path] = $token;
        }

        foreach (['light', 'dark'] as $required) {
            if (!isset($modes[$required])) {
                throw new Failure(sprintf('The theme declares no `%s` mode.', $required));
            }
        }

        // Light first, dark second, then anything else, so the emitted order does not depend on the order
        // the modes happen to appear in the JSON.
        $ordered = ['light' => $modes['light'], 'dark' => $modes['dark']];

        foreach ($modes as $mode => $tokens) {
            if (!isset($ordered[$mode])) {
                $ordered[$mode] = $tokens;
            }
        }

        return $ordered;
    }

    private function roleName(Token $token): string
    {
        return Naming::kebab(array_slice($token->localSegments(), 1));
    }

    private function roleValue(Token $token): string
    {
        return match ($token->type) {
            'color' => $this->reference($token->written, $token->resolved, fn (mixed $v): string => Colour::parse($v, $token->path)->css()),
            'shadow' => Value::shadowCss($token->resolved, $token->path),
            'dimension' => Value::dimensionCss($token->resolved, $token->path),
            default => throw new Failure(sprintf('Theme token `%s` is typed `%s`, which the CSS emitter has no rule for.', $token->path, $token->type)),
        };
    }

    private function colourValue(Token $token): string
    {
        return $this->reference($token->written, $token->resolved, fn (mixed $v): string => Colour::parse($v, $token->path)->css());
    }

    /**
     * A `var()` pointing at the token this value aliases, or the literal when it aliases nothing.
     *
     * This is what keeps one value in one place: a semantic role compiles to a reference to a primitive,
     * so changing the primitive changes the role, and no file holds a second copy of the number.
     */
    private function reference(mixed $written, mixed $resolved, ?\Closure $literal = null): string
    {
        if (is_string($written) && preg_match('/^\{([^}]+)\}$/', $written, $m) === 1 && isset($this->names[$m[1]])) {
            return sprintf('var(--%s)', $this->names[$m[1]]);
        }

        if ($literal !== null) {
            return $literal($resolved);
        }

        if (is_array($resolved)) {
            return implode(', ', array_map(static fn (mixed $v): string => (string) $v, $resolved));
        }

        return (string) $resolved;
    }

    /** @return array<string, string> token path => custom property name */
    public function names(): array
    {
        return $this->names;
    }

    /** A font stack rendered for CSS: generic keywords bare, real family names quoted. */
    public static function familyStack(mixed $stack): string
    {
        $generics = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', 'math', 'emoji', 'fangsong'];
        $names = is_array($stack) ? $stack : [$stack];

        return implode(', ', array_map(
            static fn (mixed $name): string => in_array((string) $name, $generics, true) ? (string) $name : sprintf('"%s"', (string) $name),
            $names,
        ));
    }
}
