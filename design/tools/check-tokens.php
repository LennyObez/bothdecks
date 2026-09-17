<?php

declare(strict_types=1);

/**
 * The gate.
 *
 *   php design/tools/check-tokens.php
 *
 * Twelve checks, each failing on its own. Every one prints what it measured rather than whether it passed: a
 * gate that reports "ok" and nothing else cannot be told from a gate that ran nothing. Nothing here reaches
 * the network and nothing is optional, because a check that degrades to a note when a package is missing
 * reports a pass for having been unable to run.
 */

require __DIR__ . '/bootstrap.php';

use BothDecks\Design\Colour;
use BothDecks\Design\Failure;
use BothDecks\Design\FigmaExport;
use BothDecks\Design\Generator;
use BothDecks\Design\Manifest;
use BothDecks\Design\Naming;
use BothDecks\Design\Oklch;
use BothDecks\Design\Token;
use BothDecks\Design\TokenSource;
use BothDecks\Design\Value;

/**
 * Every way a colour can be written down.
 *
 * The narrow version of this pattern was six hexadecimal digits. It let through the three- and four-digit
 * shorthands, the eight-digit `0xAARRGGBB` form this generator itself emits into Kotlin and Swift, and every
 * functional notation. A pattern that misses the shape its own output takes is not a check.
 *
 * A functional notation has to contain a digit to count. That is not an exemption but the definition: a
 * colour literal states numbers, so `rgba(r, g, b, a)` naming its arguments and a format string spelling out
 * the same call are descriptions of the notation, while the same call with numbers in it is a colour.
 */
const COLOUR_LITERAL = '/#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})\b'
    . '|\b0x(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6})\b'
    . '|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\([^)]*[0-9][^)]*\)/';

/**
 * The one marker that exempts a line, spelled in full.
 *
 * A line carrying it is skipped and reported, so an exemption is a thing a reader sees in the diff that adds
 * it and in the gate's own output afterwards. What this replaces was an exemption keyed on the substring
 * `sprintf`, which silenced every line that happened to format a string, including any that formatted a
 * colour, and which nobody had to write down or defend.
 */
const COLOUR_LITERAL_EXEMPTION = 'bd-colour-literal-ok';

/**
 * A hexadecimal literal in the form `Colour::parse` reads.
 *
 * `0xAARRGGBB` is alpha-first, which is what Compose and the Swift helper take; `#rrggbbaa` is alpha-last.
 * Reordering rather than truncating matters: dropping the alpha would let a translucent literal match an
 * opaque token and pass.
 */
function normaliseLiteral(string $literal): string
{
    if (!str_starts_with($literal, '0x')) {
        return $literal;
    }

    $digits = substr($literal, 2);

    if (strlen($digits) === 6) {
        return '#' . $digits;
    }

    return '#' . substr($digits, 2) . substr($digits, 0, 2);
}

/**
 * What is on disk in the output directory, keyed by the name a reader would use.
 *
 * @return array<string, string>
 */
function committedArtefacts(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $files[substr($file->getPathname(), strlen($directory) + 1)] = (string) file_get_contents($file->getPathname());
    }

    // Sorted so the report reads the same on every machine, whatever order the filesystem hands them back.
    ksort($files);

    return $files;
}

/** @var list<array{name: string, failures: list<string>, notes: list<string>}> $results */
$results = [];

$check = static function (string $name, callable $body) use (&$results): void {
    $failures = [];
    $notes = [];

    try {
        $body($failures, $notes);
    } catch (Failure $failure) {
        $failures[] = $failure->getMessage();
    }

    $results[] = ['name' => $name, 'failures' => $failures, 'notes' => $notes];
};

$manifest = new Manifest();
$source = TokenSource::load(BD_TOKEN_SOURCE, $manifest);
$generator = new Generator(BD_TOKEN_SOURCE, BD_OUTPUT_DIRECTORY);
$build = $generator->build();

// ---------------------------------------------------------------------------------------------------------
// 1. The committed artefacts are the ones the source produces.
// ---------------------------------------------------------------------------------------------------------

$check('committed artefacts match the source', static function (array &$failures, array &$notes) use ($generator, $build): void {
    foreach ($generator->differences($build['files']) as $difference) {
        $failures[] = $difference;
    }

    $notes[] = sprintf('%d artefact%s compared', count($build['files']), count($build['files']) === 1 ? '' : 's');
});

// ---------------------------------------------------------------------------------------------------------
// 2. Two runs produce the same bytes. Without this, `--check` in a pipeline is noise.
// ---------------------------------------------------------------------------------------------------------

$check('the generator is idempotent', static function (array &$failures, array &$notes) use ($build): void {
    $second = (new Generator(BD_TOKEN_SOURCE, BD_OUTPUT_DIRECTORY))->build();

    foreach ($build['files'] as $name => $contents) {
        if (($second['files'][$name] ?? null) !== $contents) {
            $failures[] = sprintf('%s differs between two runs of the same source', $name);
        }
    }

    $notes[] = sprintf(
        'total %s bytes, identical on the second run',
        number_format(array_sum(array_map('strlen', $build['files'])), 0, '.', ' '),
    );
});

// ---------------------------------------------------------------------------------------------------------
// 3. Every colour in an artefact came from the source.
//
// This is the check that turns "one palette" from an intention into a property. The handoff had five
// palettes: the source, and four dictionaries of hexadecimals typed by hand into screens, which had drifted
// apart on eight values including a dark match screen with its foreground and background swapped.
// ---------------------------------------------------------------------------------------------------------

$check('every colour in an artefact exists in the source', static function (array &$failures, array &$notes) use ($source): void {
    $known = [];

    foreach ($source->tokens() as $token) {
        if ($token->type !== 'color') {
            continue;
        }

        // Keyed by the eight-digit ARGB form, because that quantises alpha to the eight bits every target
        // ships. An alpha of 0.45 in the source reaches Kotlin as 0x73 and comes back as 0.451, and comparing
        // the decimals would report a colour as foreign for having made the round trip it is meant to make.
        $known[Colour::parse($token->resolved, $token->path)->argb()] = true;
    }

    // Shadow colours are part of the elevation tokens rather than of the palette, so they count as known too.
    foreach ($source->tokens() as $token) {
        if ($token->type !== 'shadow') {
            continue;
        }

        foreach (Value::shadow($token->resolved, $token->path) as $layer) {
            $known[$layer['colour']->argb()] = true;
        }
    }

    // Read from disk, not from the build held in memory.
    //
    // What this check is for is the artefact a consumer actually opens. Scanning the bytes the generator just
    // produced answers a question nobody asked, since the generator emits what the source says by
    // construction, and it answers it with a pass while a foreign colour sits in the committed file. Every file in the
    // output directory is read, including any the current source no longer produces, because a stale
    // artefact left behind is read by a consumer exactly like a current one.
    $found = 0;
    $files = committedArtefacts(BD_OUTPUT_DIRECTORY);

    if ($files === []) {
        $failures[] = sprintf('there is nothing in %s to check', BD_OUTPUT_DIRECTORY);
    }

    foreach ($files as $name => $contents) {
        preg_match_all(COLOUR_LITERAL, $contents, $matches);

        foreach ($matches[0] as $literal) {
            ++$found;

            try {
                $normalised = Colour::parse(normaliseLiteral($literal), $name)->argb();
            } catch (Failure $unreadable) {
                $failures[] = sprintf('%s carries %s, which is not a colour the generator can read', $name, $literal);

                continue;
            }

            if (!isset($known[$normalised])) {
                $failures[] = sprintf('%s carries %s, which no token defines', $name, $literal);
            }
        }
    }

    $notes[] = sprintf(
        '%d colour literals across %d committed artefact%s, %d distinct colours in the source',
        $found,
        count($files),
        count($files) === 1 ? '' : 's',
        count($known),
    );
});

// ---------------------------------------------------------------------------------------------------------
// 4. No colour literal outside the token source.
// ---------------------------------------------------------------------------------------------------------

$check('no colour literal outside tokens/', static function (array &$failures, array &$notes): void {
    $root = dirname(__DIR__);
    $scanned = 0;
    $exempted = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $paths = [];

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    foreach ($paths as $path) {
        $relative = substr($path, strlen($root) + 1);

        // The source is where colours live; the generated directory is produced from it and is covered by
        // check 3, which reads what is committed and is stricter than this one.
        if (str_starts_with($relative, 'tokens/')) {
            continue;
        }

        if (!in_array(pathinfo($path, PATHINFO_EXTENSION), ['php', 'css', 'kt', 'swift', 'md', 'json', 'html', 'js', 'ts', 'xml', 'yml', 'yaml'], true)) {
            continue;
        }

        ++$scanned;

        foreach (explode("\n", (string) file_get_contents($path)) as $number => $line) {
            if (preg_match(COLOUR_LITERAL, $line, $m) !== 1) {
                continue;
            }

            // An exemption is a thing somebody wrote on the line, not a shape the checker guessed at. The
            // generator's own documentation shows the literal forms it emits, and showing one is not
            // defining a colour, but saying so is the author's job, in the file, where a reviewer sees it.
            if (str_contains($line, COLOUR_LITERAL_EXEMPTION)) {
                $exempted[] = sprintf('%s line %d carries %s', $relative, $number + 1, $m[0]);

                continue;
            }

            $failures[] = sprintf('%s line %d: %s', $relative, $number + 1, trim($line));
        }
    }

    $notes[] = sprintf('%d hand-written files scanned', $scanned);
    $notes[] = sprintf('%d line%s exempted by name', count($exempted), count($exempted) === 1 ? '' : 's');

    foreach ($exempted as $line) {
        $notes[] = '  ' . $line;
    }
});

// ---------------------------------------------------------------------------------------------------------
// 5. The design file has a contract, and the repository holds it.
//
// Everything a target compiles is generated and cannot drift. A design file is edited by hand, elsewhere, by
// somebody who is not reading this at the time. What the repository can do is publish what that file must
// contain, keep it current, and refuse a copy the source would not produce; comparing the file against it is
// then a mechanical question, and its answer names the variables that disagree.
// ---------------------------------------------------------------------------------------------------------

$check('the design-file contract matches the source', static function (array &$failures, array &$notes) use ($source): void {
    $expected = FigmaExport::build($source);
    $path = dirname(BD_TOKEN_SOURCE) . '/figma-expected.json';

    if (!is_file($path)) {
        $failures[] = 'design/tokens/figma-expected.json is absent, so nothing states what the design file must carry. Run `php design/tools/export-figma.php`.';

        return;
    }

    $committed = file_get_contents($path);

    if ($committed !== $expected) {
        $failures[] = 'design/tokens/figma-expected.json is not what this source produces, so the design file '
            . 'would be measured against a contract nobody wrote. Run `php design/tools/export-figma.php`.';

        return;
    }

    foreach (FigmaExport::counts($expected) as $collection => $count) {
        $notes[] = sprintf('%s: %d variables the design file must carry', $collection, $count);
    }
});

// ---------------------------------------------------------------------------------------------------------
// 6. The perceptual components a colour declares are the colour that will be painted.
//
// A palette entry carries two spellings of one colour: the lightness, chroma and hue it was placed with, and
// the hexadecimal fallback every target emits. Only the second is generated, so the first is documentation,
// and documentation nothing measures drifts. Once it has drifted, the next step added to the ramp is placed
// against a curve that no longer describes the ramp.
// ---------------------------------------------------------------------------------------------------------

$check('every colour renders the components it declares', static function (array &$failures, array &$notes) use ($source): void {
    $checked = 0;
    $widest = 0;

    foreach ($source->tokens() as $token) {
        if ($token->type !== 'color' || !is_array($token->resolved)) {
            continue;
        }

        $components = $token->resolved['components'] ?? null;
        $hex = $token->resolved['hex'] ?? null;

        if (!is_array($components) || !is_string($hex)) {
            continue;
        }

        $space = $token->resolved['colorSpace'] ?? null;

        if ($space !== 'oklch') {
            $failures[] = sprintf('%s declares the colour space `%s`, which this check cannot render.', $token->path, is_string($space) ? $space : gettype($space));

            continue;
        }

        ++$checked;

        $perceptual = Oklch::fromComponents(array_values($components), $token->path);
        $rendered = $perceptual->channels();
        $declared = Colour::parse($hex, $token->path);

        $difference = max(
            abs($rendered[0] - $declared->red),
            abs($rendered[1] - $declared->green),
            abs($rendered[2] - $declared->blue),
        );
        $widest = max($widest, $difference);

        // Exactly, not nearly. Every colour in this palette renders its components to the channel, so a
        // tolerance here would only be room for a fallback to be edited without its components following.
        if ($difference !== 0) {
            $failures[] = sprintf(
                '%s declares oklch(%s %s %s), which renders %s, but its fallback is %s: %d step%s apart on one channel.',
                $token->path,
                $components[0],
                $components[1],
                $components[2],
                $perceptual->hex(),
                strtolower($hex),
                $difference,
                $difference === 1 ? '' : 's',
            );
        }
    }

    $notes[] = sprintf('%d colour%s rendered from their components', $checked, $checked === 1 ? '' : 's');
    $notes[] = sprintf('widest disagreement on any channel: %d of 255', $widest);
});

// ---------------------------------------------------------------------------------------------------------
// 6. A semantic role points at a primitive; it does not restate one.
// ---------------------------------------------------------------------------------------------------------

$check('every theme role resolves to a palette entry', static function (array &$failures, array &$notes) use ($source): void {
    $roles = 0;
    $chained = 0;

    foreach ($source->group('theme') as $token) {
        if ($token->type !== 'color') {
            continue;
        }

        ++$roles;
        $alias = $token->aliasTarget();

        if ($alias === null) {
            $failures[] = sprintf('%s carries a colour of its own instead of pointing at a palette entry', $token->path);

            continue;
        }

        // A role may point at another role of the same theme. That is how the source says "deliberately the
        // same as", and it is the opposite of a duplicated value: the two cannot drift, because there is only
        // one of them. What is followed here is the whole chain, and what it has to end at is the palette.
        $steps = 0;

        while ($source->has($alias) && $source->token($alias)->aliasTarget() !== null) {
            $alias = (string) $source->token($alias)->aliasTarget();

            if (++$steps > $roles) {
                break;
            }
        }

        if ($steps > 0) {
            ++$chained;
        }

        if (!str_starts_with($alias, 'color.')) {
            $failures[] = sprintf('%s resolves to %s, which is not a palette entry', $token->path, $alias);
        }
    }

    $notes[] = sprintf('%d colour roles checked, %d of them through another role', $roles, $chained);
});

// ---------------------------------------------------------------------------------------------------------
// 6. Contrast, on every pair the role names themselves imply.
//
// The pair set is derived from the naming, not chosen: `x.onFill` is by definition drawn on `x.fill`, and a
// focus ring is by definition drawn on whatever is behind the control. Choosing the pairs by hand is how a
// 62-row audit table came to contain no row at all for `surface.inverse`, and how the focus ring was
// declared to pass at 7.03:1 on the one surface where it does.
// ---------------------------------------------------------------------------------------------------------

$check('contrast', static function (array &$failures, array &$notes) use ($source): void {
    $themes = [];

    foreach ($source->group('theme') as $token) {
        if ($token->type !== 'color') {
            continue;
        }

        $local = $token->localSegments();
        $themes[$local[0]][implode('.', array_slice($local, 1))] = Colour::parse($token->resolved, $token->path);
    }

    $pairs = 0;

    // Two facts a role name cannot carry: which ring goes on which ground, and which fills are painted as
    // whole surfaces. Both are declared. Absent, the stricter reading applies, so deleting a declaration
    // can only add pairs.
    $document = $source->rawDocument();
    $extensions = is_array($document['$extensions'] ?? null) ? $document['$extensions'] : [];
    $composition = is_array($extensions['bothdecks.composition'] ?? null) ? $extensions['bothdecks.composition'] : [];
    $focusRingMap = is_array($extensions['bothdecks.focusRing'] ?? null) ? $extensions['bothdecks.focusRing'] : [];

    $fullBleed = [];

    foreach (is_array($composition['fullBleedSurfaces'] ?? null) ? $composition['fullBleedSurfaces'] : [] as $name) {
        if (is_string($name)) {
            $fullBleed[$name] = true;
        }
    }

    foreach ($themes as $mode => $roles) {
        $rings = is_array($focusRingMap[$mode] ?? null) ? $focusRingMap[$mode] : [];

        $ringFor = static function (string $ground) use ($rings): string {
            $declared = $rings[$ground] ?? null;

            return is_string($declared) ? $declared : 'border.focus';
        };

        $surfaces = [];
        $fills = [];

        foreach ($roles as $name => $colour) {
            if (str_starts_with($name, 'surface.') && !str_ends_with($name, '.scrim')) {
                $surfaces[$name] = $colour;
            }

            if (str_ends_with($name, '.fill')) {
                $fills[$name] = $colour;
            }
        }

        $assert = static function (string $foreground, string $background, float $threshold) use ($mode, $roles, &$failures, &$pairs): void {
            if (!isset($roles[$foreground], $roles[$background])) {
                return;
            }

            ++$pairs;
            $ratio = $roles[$foreground]->contrastAgainst($roles[$background]);

            if ($ratio < $threshold) {
                $failures[] = sprintf(
                    '%s: %s on %s is %.2f:1, below %.1f:1',
                    $mode,
                    $foreground,
                    $background,
                    $ratio,
                    $threshold,
                );
            }
        };

        // Body text on every surface it can be drawn on. `text.disabled` is left out on purpose: WCAG 2.2
        // exempts an inactive control, and holding it to 4.5:1 would make "disabled" indistinguishable.
        foreach (['text.primary', 'text.secondary', 'text.tertiary', 'text.link'] as $foreground) {
            foreach ($surfaces as $surface => $ignored) {
                if ($surface === 'surface.inverse') {
                    continue;
                }

                $assert($foreground, $surface, 4.5);
            }
        }

        $assert('text.onInverse', 'surface.inverse', 4.5);

        // Every family's own foreground on its own fill and its own tint.
        foreach ($roles as $name => $ignored) {
            if (!str_ends_with($name, '.onFill')) {
                continue;
            }

            $family = substr($name, 0, -strlen('.onFill'));
            $assert($name, $family . '.fill', 4.5);
        }

        foreach ($roles as $name => $ignored) {
            if (!str_ends_with($name, '.text')) {
                continue;
            }

            $family = substr($name, 0, -strlen('.text'));
            $assert($name, $family . '.tint', 4.5);
            $assert($name, 'surface.canvas', 4.5);
            $assert($name, 'surface.card', 4.5);
        }

        // A functional boundary has to be visible: WCAG 2.2 SC 1.4.11. Not on the inverse surface, where a
        // control is bounded by the surface itself.
        foreach ($surfaces as $surface => $ignored) {
            if ($surface === 'surface.inverse') {
                continue;
            }

            $assert('border.strong', $surface, 3.0);
        }

        // A filled control has to be distinguishable from the page it sits on. A full-bleed fill is the
        // page, so it is measured as a ground instead.
        foreach ($fills as $fill => $ignored) {
            if (isset($fullBleed[$fill])) {
                continue;
            }

            $assert($fill, 'surface.canvas', 3.0);
            $assert($fill, 'surface.card', 3.0);
        }

        // The focus ring, on every ground a focusable control can sit on, fills and inverse surface included.
        foreach ($surfaces as $surface => $ignored) {
            $assert($ringFor($surface), $surface, 3.0);
        }

        foreach ($fills as $fill => $ignored) {
            $assert($ringFor($fill), $fill, 3.0);
        }
    }

    $notes[] = sprintf('%d pairs measured across %d themes', $pairs, count($themes));
});

// ---------------------------------------------------------------------------------------------------------
// 7. Two surfaces that mean different things must not be the same colour.
// ---------------------------------------------------------------------------------------------------------

$check('surfaces and hairlines are distinguishable', static function (array &$failures, array &$notes) use ($source): void {
    $themes = [];

    foreach ($source->group('theme') as $token) {
        if ($token->type !== 'color') {
            continue;
        }

        $local = $token->localSegments();
        $themes[$local[0]][implode('.', array_slice($local, 1))] = Colour::parse($token->resolved, $token->path);
    }

    // The pairs and the minimum come from the source's own `surfaceSeparation` declaration rather than from a
    // list kept here. One property answers to one rule: a threshold written in a tool as well as in the
    // source is a threshold two tools can disagree about, and the disagreement reads as a pass in whichever
    // one is asked first.
    //
    // The rule is a ratio and not equality. Two colours that merely differ can still be a boundary nobody can
    // see, and the name of this check is "distinguishable".
    $composition = $source->rawDocument()['$extensions']['bothdecks.composition'] ?? null;
    $separation = is_array($composition) ? ($composition['surfaceSeparation'] ?? null) : null;

    if (!is_array($separation) || !isset($separation['minimum']) || !is_numeric($separation['minimum'])) {
        $failures[] = 'the source declares no `$extensions."bothdecks.composition".surfaceSeparation.minimum`, so there is no rule to measure against';

        return;
    }

    if (!isset($separation['boundaryMinimum']) || !is_numeric($separation['boundaryMinimum'])) {
        $failures[] = 'the source declares no `$extensions."bothdecks.composition".surfaceSeparation.boundaryMinimum`, so a border-separated pair would have nothing to clear';

        return;
    }

    $minimum = (float) $separation['minimum'];
    $boundaryMinimum = (float) $separation['boundaryMinimum'];
    $colourSeparated = is_array($separation['colourSeparated'] ?? null) ? $separation['colourSeparated'] : [];
    $elevationSeparated = is_array($separation['elevationSeparated'] ?? null) ? $separation['elevationSeparated'] : [];
    $borderSeparated = is_array($separation['borderSeparated'] ?? null) ? $separation['borderSeparated'] : [];

    if ($colourSeparated === []) {
        $failures[] = '`surfaceSeparation.colourSeparated` names no pair, so this check would measure nothing';
    }

    $measured = 0;

    foreach ($themes as $mode => $roles) {
        foreach ($colourSeparated as $pair) {
            if (!is_array($pair) || count($pair) !== 2) {
                $failures[] = sprintf('`surfaceSeparation.colourSeparated` holds an entry that is not a pair: %s', json_encode($pair));

                continue;
            }

            [$a, $b] = array_values($pair);

            if (!isset($roles[$a], $roles[$b])) {
                $failures[] = sprintf('%s: `surfaceSeparation` names %s against %s, and one of the two is not a role of this theme', $mode, $a, $b);

                continue;
            }

            ++$measured;
            $ratio = $roles[$a]->contrastAgainst($roles[$b]);

            if ($ratio < $minimum) {
                $failures[] = sprintf(
                    '%s: %s against %s is %.3f:1, below the %.2f:1 the source sets, and nothing but colour tells these two apart',
                    $mode,
                    $a,
                    $b,
                    $ratio,
                    $minimum,
                );

                continue;
            }

            $notes[] = sprintf('%s: %s against %s is %.3f:1', $mode, $a, $b, $ratio);
        }
    }

    // A pair told apart by a shadow instead of by colour is allowed to share a colour, on the condition the
    // shadow exists. Without this the exemption would be the loophole: two surfaces declared elevation-
    // separated, and an elevation of `none` behind them.
    foreach ($elevationSeparated as $entry) {
        if (!is_array($entry) || count($entry) !== 3) {
            $failures[] = sprintf('`surfaceSeparation.elevationSeparated` holds an entry that is not a triple: %s', json_encode($entry));

            continue;
        }

        [$a, $b, $elevation] = array_values($entry);

        // An elevation is stated per theme, so the pair has to hold in every theme rather than in one. A
        // shadow present in the light theme and flat in the dark one is two surfaces that merge after dusk.
        foreach (array_keys($themes) as $mode) {
            $path = $source->has($elevation . '.' . $mode) ? $elevation . '.' . $mode : (string) $elevation;

            if (!$source->has($path)) {
                $failures[] = sprintf('%s: %s and %s are declared to be told apart by `%s`, which is not a token', $mode, $a, $b, $elevation);

                continue;
            }

            ++$measured;
            $token = $source->token($path);
            $visible = array_filter(
                Value::shadow($token->resolved, $token->path),
                // A layer that is transparent, or that has no offset, blur or spread, paints nothing.
                static fn (array $l): bool => $l['colour']->alpha > 0.0
                    && abs($l['dx']) + abs($l['dy']) + abs($l['blur']) + abs($l['spread']) > 0.0,
            );

            if ($visible === []) {
                $failures[] = sprintf('%s: %s and %s are told apart by `%s` alone, and it draws no shadow', $mode, $a, $b, $path);

                continue;
            }

            $notes[] = sprintf('%s: %s against %s is separated by %s, %d layer%s', $mode, $a, $b, $path, count($visible), count($visible) === 1 ? '' : 's');
        }
    }

    // A pair told apart by a border instead of by colour is allowed to sit close in fill, on the condition
    // the border earns it. The condition is stricter than the fill rule it replaces: the boundary has to
    // clear the 3:1 a non-text edge owes a reader, and it has to clear it against BOTH surfaces, because a
    // border that reads against the well and vanishes into the page around it draws only half an edge.
    foreach ($borderSeparated as $entry) {
        if (!is_array($entry) || count($entry) !== 3) {
            $failures[] = sprintf('`surfaceSeparation.borderSeparated` holds an entry that is not a triple: %s', json_encode($entry));

            continue;
        }

        [$a, $b, $border] = array_values($entry);

        foreach ($themes as $mode => $roles) {
            if (!isset($roles[$a], $roles[$b], $roles[$border])) {
                $failures[] = sprintf('%s: %s and %s are declared to be told apart by %s, and one of the three is not a role of this theme', $mode, $a, $b, $border);

                continue;
            }

            if ($roles[$border]->alpha <= 0.0) {
                $failures[] = sprintf('%s: %s and %s are told apart by %s alone, and it is transparent', $mode, $a, $b, $border);

                continue;
            }

            foreach ([$a, $b] as $surface) {
                ++$measured;
                $ratio = $roles[$border]->contrastAgainst($roles[$surface]);

                if ($ratio < $boundaryMinimum) {
                    $failures[] = sprintf(
                        '%s: %s draws the edge between %s and %s and is %.3f:1 on %s, below the %.2f:1 a boundary owes a reader',
                        $mode,
                        $border,
                        $a,
                        $b,
                        $ratio,
                        $surface,
                        $boundaryMinimum,
                    );

                    continue;
                }

                $notes[] = sprintf('%s: %s on %s is %.3f:1', $mode, $border, $surface, $ratio);
            }

            $notes[] = sprintf('%s: %s against %s is %.3f:1 in fill, and told apart by %s', $mode, $a, $b, $roles[$a]->contrastAgainst($roles[$b]), $border);
        }
    }

    $notes[] = sprintf('%d separations measured, fills against %.2f:1 and boundaries against %.2f:1', $measured, $minimum, $boundaryMinimum);
});

// ---------------------------------------------------------------------------------------------------------
// 8. The type floor.
//
// Nothing below 12px anywhere, per the decision on the floor being stated per surface with 12 as the hard
// bottom. The token scale is one scale, so this is the bottom it has to respect.
// ---------------------------------------------------------------------------------------------------------

$check('no type style is smaller than 12px', static function (array &$failures, array &$notes) use ($source): void {
    $smallest = null;
    $smallestName = '';

    foreach ($source->group('type') as $token) {
        $style = Value::typography($token);

        if ($smallest === null || $style['size'] < $smallest) {
            $smallest = $style['size'];
            $smallestName = $token->path;
        }

        if ($style['size'] < 12.0) {
            $failures[] = sprintf('%s is %spx', $token->path, Naming::number($style['size']));
        }
    }

    if ($smallest !== null) {
        $notes[] = sprintf('smallest style: %s at %spx', $smallestName, Naming::number($smallest));
    }
});

// ---------------------------------------------------------------------------------------------------------
// 9. The line-height floor, on the styles it was decided to apply to.
//
// Body and label styles only. Display styles take the value the measurement requires to clear the Greek and
// Bulgarian ascenders and descenders, which is a measured number and not a ratio chosen by taste, so their
// ratios are reported here rather than asserted.
// ---------------------------------------------------------------------------------------------------------

$check('body and label line heights clear 1.35', static function (array &$failures, array &$notes) use ($source): void {
    foreach ($source->group('type') as $token) {
        $style = Value::typography($token);
        $ratio = $style['lineHeight'] / $style['size'];
        $name = implode('.', $token->localSegments());
        $isBodyOrLabel = str_starts_with($name, 'body') || str_starts_with($name, 'label');

        if ($isBodyOrLabel && $ratio < 1.35) {
            $failures[] = sprintf('%s is %.4f, below the 1.35 floor', $token->path, $ratio);

            continue;
        }

        if (!$isBodyOrLabel) {
            $notes[] = sprintf('%s: %.4f (reported, not asserted)', $name, $ratio);
        }
    }
});

// ---------------------------------------------------------------------------------------------------------
// 10. The source conforms to the format it names.
//
// This check runs on the file, with no network and no optional package, and that is the point of it. A
// conformance step that fetches a schema, or that imports a validator when one happens to be installed,
// reports a pass in a container with no egress and on a machine where the package is missing, and a pass
// that means "could not run" is worse than no check, because it occupies the place where a check would go.
//
// What it verifies is the structure the format defines, which is the part a schema would check anyway:
// which `$`-prefixed keys a node may carry, that every token is typed, and that anything the format does not
// define is namespaced rather than invented at the top level.
// ---------------------------------------------------------------------------------------------------------

$check('the source conforms to the token format', static function (array &$failures, array &$notes) use ($source): void {
    // The types the Design Tokens Format Module defines. A type outside this list has to be namespaced, so
    // that a reader can tell a format type from one this product added.
    $formatTypes = [
        'color', 'dimension', 'fontFamily', 'fontWeight', 'duration', 'cubicBezier', 'number',
        'strokeStyle', 'border', 'transition', 'shadow', 'gradient', 'typography',
    ];

    $document = $source->rawDocument();
    $schema = $document['$schema'] ?? null;

    if (!is_string($schema) || !str_starts_with($schema, 'https://') || !str_ends_with($schema, '.json')) {
        $failures[] = sprintf(
            'the source names `$schema` as %s, which is not an https URL to a JSON Schema document',
            $schema === null ? 'nothing' : json_encode($schema),
        );
    }

    $tokens = 0;
    $groups = 0;
    $namespaced = [];

    $walk = static function (array $node, string $path, bool $isRoot) use (&$walk, &$failures, &$tokens, &$groups, &$namespaced, $formatTypes): void {
        $isToken = array_key_exists('$value', $node);
        $isToken ? ++$tokens : ++$groups;

        $allowed = $isRoot
            ? ['$schema', '$description', '$extensions', '$type', '$deprecated']
            : ['$description', '$extensions', '$type', '$deprecated', ...($isToken ? ['$value'] : [])];

        foreach ($node as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, '$')) {
                continue;
            }

            if (!in_array($key, $allowed, true)) {
                $failures[] = sprintf(
                    '%s carries `%s`, which is not a member the format gives a %s',
                    $path === '' ? 'the root' : $path,
                    $key,
                    $isToken ? 'token' : 'group',
                );
            }
        }

        if (isset($node['$description']) && !is_string($node['$description'])) {
            $failures[] = sprintf('%s has a `$description` that is not a string', $path === '' ? 'the root' : $path);
        }

        if (isset($node['$type'])) {
            $type = $node['$type'];

            if (!is_string($type)) {
                $failures[] = sprintf('%s has a `$type` that is not a string', $path === '' ? 'the root' : $path);
            } elseif (!in_array($type, $formatTypes, true) && !str_contains($type, '.')) {
                $failures[] = sprintf(
                    '%s is typed `%s`, which the format does not define and which is not namespaced',
                    $path === '' ? 'the root' : $path,
                    $type,
                );
            }
        }

        if (isset($node['$extensions'])) {
            if (!is_array($node['$extensions'])) {
                $failures[] = sprintf('%s has `$extensions` that is not an object', $path === '' ? 'the root' : $path);
            } else {
                foreach (array_keys($node['$extensions']) as $key) {
                    // The format asks for a reverse-domain-style key so two vendors' extensions cannot
                    // collide. A bare word is the collision waiting to happen.
                    if (!is_string($key) || !str_contains($key, '.')) {
                        $failures[] = sprintf('%s carries the extension `%s`, which is not namespaced', $path === '' ? 'the root' : $path, (string) $key);

                        continue;
                    }

                    $namespaced[$key] = true;
                }
            }
        }

        if ($isToken) {
            return;
        }

        foreach ($node as $key => $child) {
            if (is_string($key) && str_starts_with($key, '$')) {
                continue;
            }

            $name = (string) $key;

            // `.` separates a path and `{}` delimits a reference, so a name carrying either makes the
            // reference syntax ambiguous.
            if (preg_match('/[.{}]/', $name) === 1) {
                $failures[] = sprintf('`%s` in %s uses a character the reference syntax reserves', $name, $path === '' ? 'the root' : $path);
            }

            if (!is_array($child)) {
                $failures[] = sprintf('`%s` in %s is neither a token nor a group', $name, $path === '' ? 'the root' : $path);

                continue;
            }

            $walk($child, $path === '' ? $name : $path . '.' . $name, false);
        }
    };

    $walk($document, '', true);

    foreach ($source->tokens() as $token) {
        // Enforced here as well as in the loader, because the loader will accept a type the manifest supplies
        // for a whole group, and the file itself is what a validator downstream would be handed.
        if (!in_array($token->type, $formatTypes, true) && !str_contains($token->type, '.')) {
            $failures[] = sprintf('%s ends up typed `%s`, which is neither a format type nor namespaced', $token->path, $token->type);
        }
    }

    ksort($namespaced);

    $notes[] = sprintf('%d tokens and %d groups walked', $tokens, $groups);
    $notes[] = sprintf('%d namespaced extension%s: %s', count($namespaced), count($namespaced) === 1 ? '' : 's', implode(', ', array_keys($namespaced)));
});

// ---------------------------------------------------------------------------------------------------------

$failed = 0;

foreach ($results as $result) {
    $status = $result['failures'] === [] ? 'pass' : 'FAIL';
    fwrite(STDOUT, sprintf("[%s] %s\n", $status, $result['name']));

    foreach ($result['notes'] as $note) {
        fwrite(STDOUT, '       ' . $note . "\n");
    }

    foreach ($result['failures'] as $failure) {
        fwrite(STDOUT, '       ' . str_replace("\n", "\n       ", $failure) . "\n");
    }

    if ($result['failures'] !== []) {
        ++$failed;
    }
}

fwrite(STDOUT, sprintf("\n%d of %d checks passed.\n", count($results) - $failed, count($results)));

exit($failed === 0 ? 0 : 1);
